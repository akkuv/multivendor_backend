#!/usr/bin/env python3
# Un-wraps the __('...') messages that apply-messages.py inserted, so a clean
# pass can be redone after a bugfix. Only strings that are in the flag set
# (scanned English messages) are restored; legitimate __('common.*') etc. calls
# are left untouched. Handles both the correct `__('T')` and the paren-less
# corrupted `__('T'` forms.
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
APP = ROOT / "app"

FLAG = set(json.load(open("/tmp/msgs.json")))

FIRST = re.compile(
    r"(\$this->(?:success|error|notFound|forbidden|unauthorized|paginated)\(\s*)__\('((?:[^'\\]|\\.)*)'\)?"
)
SECOND = re.compile(
    r"(\$this->(?:validationError|withResource)\([^()]*?,\s*)__\('((?:[^'\\]|\\.)*)'\)?"
)
ABORT = re.compile(r"(\babort\(\s*\d{3}\s*,\s*)__\('((?:[^'\\]|\\.)*)'\)?")
THROW = re.compile(
    r"(throw new (?:[A-Z]\w*)?(?:Exception|Error)\(\s*)__\('((?:[^'\\]|\\.)*)'\)?"
)
AUDIT = re.compile(
    r"(\$this->audit->log\([^,]+,\s*'[^']*',\s*)__\('((?:[^'\\]|\\.)*)'\)?"
)
WM = re.compile(r"withMessages\((\[.*?\])\),?", re.S)
QM = re.compile(r"__\('((?:[^'\\]|\\.)*)'\)?")


def unescape(s):
    return s.replace("\\'", "'").replace("\\\\", "\\")


def revert(m):
    text = unescape(m.group(2))
    if text not in FLAG:
        return m.group(0)
    return m.group(1) + "'" + text + "'"


def revert_wm(m):
    block = m.group(1)

    def inner(qm):
        text = unescape(qm.group(1))
        if text not in FLAG:
            return qm.group(0)
        return "'" + text + "'"

    return "withMessages(" + QM.sub(inner, block) + ")"


total = 0
changed = []
for path in sorted(APP.rglob("*.php")):
    src = path.read_text(encoding="utf-8")
    new = FIRST.sub(revert, src)
    new = SECOND.sub(revert, new)
    new = ABORT.sub(revert, new)
    new = THROW.sub(revert, new)
    new = AUDIT.sub(revert, new)
    new = WM.sub(revert_wm, new)
    if new != src:
        path.write_text(new, encoding="utf-8")
        total += 1
        changed.append(path)

print(f"reverted {total} file(s)")
for p in changed:
    print("  " + str(p.relative_to(ROOT)))