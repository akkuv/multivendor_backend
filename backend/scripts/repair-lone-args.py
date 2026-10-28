#!/usr/bin/env python3
# Repairs the lone-argument wraps that lost their helper-closing paren during
# the revert/apply churn:  helper(__('MSG');  ->  helper(__('MSG'));
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
APP = ROOT / "app"

H1 = re.compile(
    r"(\$this->(?:success|error|notFound|forbidden|unauthorized|paginated)\(__\('((?:[^'\\]|\\.)*)'\));"
)
H2 = re.compile(
    r"(\$this->(?:validationError|withResource)\([^()]*?,\s*__\('((?:[^'\\]|\\.)*)'\));"
)
AB = re.compile(r"(abort\(\s*\d{3}\s*,\s*__\('((?:[^'\\]|\\.)*)'\));")
TH = re.compile(
    r"(throw new (?:[A-Z]\w*)?(?:Exception|Error)\(__\('((?:[^'\\]|\\.)*)'\));"
)


def fix(m):
    return m.group(1) + ");"


total = 0
for path in sorted(APP.rglob("*.php")):
    src = path.read_text(encoding="utf-8")
    new = H1.sub(fix, src)
    new = H2.sub(fix, new)
    new = AB.sub(fix, new)
    new = TH.sub(fix, new)
    if new != src:
        path.write_text(new, encoding="utf-8")
        total += 1
        print("  " + str(path.relative_to(ROOT)))

print(f"fixed {total} file(s)")