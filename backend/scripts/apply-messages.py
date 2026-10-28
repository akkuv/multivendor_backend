#!/usr/bin/env python3
# Wraps the flagged API message literals in __('...') so they localise via
# SetLocale. Python drives the rewrite; a text diff against the scanner's
# locations is printed for a human eyeball pass.
#
# The patterns are the exact shapes scripts/scan-messages.py matches, so a
# literal that was never flagged is never touched. A value is only wrapped when
# its text is present in the flagged set.
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
APP = ROOT / "app"

scanned = json.load(open("/tmp/msgs.json"))
FLAG = set(scanned)

FIRST = re.compile(r"\$this->(?:success|error|notFound|forbidden|unauthorized|paginated)\(\s*'((?:[^'\\]|\\.)*)'")
SECOND = re.compile(r"\$this->(?:validationError|withResource)\(([^()]*?),\s*'((?:[^'\\]|\\.)*)'")
ABORT = re.compile(r"\babort\((\d{3}),\s*'((?:[^'\\]|\\.)*)'")
THROW = re.compile(r"throw new ((?:[A-Z]\w*)?(?:Exception|Error))\(\s*'((?:[^'\\]|\\.)*)'")
AUDIT = re.compile(r"(\$this->audit->log\([^,]+,\s*'[^']*',\s*)'((?:[^'\\]|\\.)*)'")
WM = re.compile(r"withMessages\((\[.*?\])\),?", re.S)
QM = re.compile(r"'((?:[^'\\]|\\.)*)'")


def unescape(s: str) -> str:
    return s.replace("\\'", "'").replace("\\\\", "\\")


def esc(s: str) -> str:
    return s.replace("\\", "\\\\").replace("'", "\\'")


def wrap(text: str) -> str:
    return "__('" + esc(text) + "')"


def fix_first(m):
    text = unescape(m.group(1))
    if text not in FLAG:
        return m.group(0)
    return "$this->" + m.group(0).split("(", 1)[0].rsplit("->", 1)[-1] + "(" + wrap(text)


def fix_second(m):
    text = unescape(m.group(2))
    if text not in FLAG:
        return m.group(0)
    return "$this->" + m.group(0).split("(", 1)[0].rsplit("->", 1)[-1] + "(" + m.group(1) + ", " + wrap(text)


def fix_abort(m):
    text = unescape(m.group(2))
    if text not in FLAG:
        return m.group(0)
    return "abort(" + m.group(1) + ", " + wrap(text)


def fix_throw(m):
    text = unescape(m.group(2))
    if text not in FLAG:
        return m.group(0)
    return "throw new " + m.group(1) + "(" + wrap(text)


def fix_audit(m):
    text = unescape(m.group(2))
    if text not in FLAG:
        return m.group(0)
    return m.group(1) + wrap(text)


def fix_wm(m):
    block = m.group(1)

    def inner(qm):
        text = unescape(qm.group(1))
        if text not in FLAG:
            return qm.group(0)
        before = block[: qm.start()]
        if before.rstrip().endswith("__("):
            return qm.group(0)
        return wrap(text)

    fixed = QM.sub(inner, block)
    return "withMessages(" + fixed + ")"


total = 0
changed_files = []
for path in sorted(APP.rglob("*.php")):
    src = path.read_text(encoding="utf-8")
    new = FIRST.sub(fix_first, src)
    new = SECOND.sub(fix_second, new)
    new = ABORT.sub(fix_abort, new)
    new = THROW.sub(fix_throw, new)
    new = WM.sub(fix_wm, new)
    new = AUDIT.sub(fix_audit, new)
    if new != src:
        path.write_text(new, encoding="utf-8")
        total += 1
        changed_files.append(path)

print(f"rewrote {total} file(s)")
for p in changed_files:
    print("  " + str(p.relative_to(ROOT)))