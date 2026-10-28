#!/usr/bin/env python3
# Wraps enum label() literals in __('...') so status labels localise via
# SetLocale. Only `self::X => 'Label'` entries whose value looks like prose are
# touched.
import re
from pathlib import Path

APP = Path("app/Enums")

PAT = re.compile(r"(self::\w+\s*=>\s*)'((?:[^'\\]|\\.)*)'")


def is_label(text):
    t = text.strip()
    if len(t) < 2:
        return False
    if not re.search(r"[A-Za-z]{2}", t):
        return False
    if not re.match(r"^[A-Z]", t):
        return False
    # already localised
    return True


def repl(m):
    text = m.group(2).replace("\\'", "'").replace("\\\\", "\\")
    if not is_label(text):
        return m.group(0)
    esc = text.replace("\\", "\\\\").replace("'", "\\'")
    return f"{m.group(1)}__('{esc}')"


total = 0
for path in sorted(APP.rglob("*.php")):
    src = path.read_text(encoding="utf-8")
    new = PAT.sub(repl, src)
    if new != src:
        path.write_text(new, encoding="utf-8")
        total += 1
        print("  " + str(path))

print(f"wrapped {total} enum file(s)")