#!/usr/bin/env python3
# Builds lang/hi/messages.php from the scanned messages + translations.
#
#   scan-messages.py  /tmp/msgs.json   key(English) -> locations
#   apply-messages.py                  /tmp/msgs.out.json key -> Hindi
#
# The English sentence is the translation key ("source strings as keys", the
# same convention as lang/hi/common.php), so `__('Order placed successfully.')`
# returns Hindi under X-Locale: hi and falls back to the English key otherwise.
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
HI = ROOT / "lang" / "hi"
EN = ROOT / "lang" / "en"

resp = "/tmp/msgs.json"
out = "/tmp/msgs.out.json"

scanned = json.load(open(resp))
outmap = json.load(open(out))

problems = []
for key in scanned:
    if key not in outmap:
        problems.append(f"missing translation: {key}")
for key, value in outmap.items():
    if not isinstance(value, str) or not value.strip():
        problems.append(f"empty translation: {key}")

# Extra keys beyond the scanned set are intentional: enum label() wrappers and
# notification/dispatcher literals (Email + in-app notification surfaces) that
# were localised by hand. Warn, don't fail.
extras = [key for key in outmap if key not in scanned]
if extras:
    print(f"  note: {len(extras)} extra key(s) beyond the scanned set accepted")

if problems:
    for p in problems:
        print("  " + p)
    print(f"{len(problems)} problem(s) - nothing written")
    raise SystemExit(1)

# --- lang/hi.json: dotless English-sentence keys resolve via the JSON loader
# (Translator::get checks lang/{locale}.json before group files), so `__('Order
# placed successfully.')` returns Hindi under X-Locale: hi and falls back to the
# English key otherwise - no lang/en file needed.
import json as _json

HI.mkdir(parents=True, exist_ok=True)
(HI.parent / "hi.json").write_text(
    _json.dumps(dict(sorted(outmap.items())), ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
)

# Legacy PHP group files are no longer needed (dotless keys only resolve through
# JSON); remove them if a previous run created them.
for legacy in (HI / "messages.php", EN / "messages.php"):
    if legacy.exists():
        legacy.unlink()

print(f"lang/hi.json written ({len(outmap)} keys)")