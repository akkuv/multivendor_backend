# Collects user-facing English message literals from the API layer.
#
# Only the syntactic positions that reach the client are considered, so enum
# values, route names, column names and test fixtures are never touched:
#
#   $this->success('...')              first argument
#   $this->error('...')                first argument
#   $this->validationError($e, '...')  second argument
#   $this->withResource($r, '...')     second argument
#   ValidationException::withMessages([...])  every message string
#   abort($code, '...')                second argument
#   throw new DomainException('...')   constructor message
#
# Output is JSON so it can be diffed and batched.
import json
import re
import sys
from pathlib import Path

APP = Path("app")

# First-argument message of the ApiResponse helpers.
FIRST_ARG = re.compile(
    r"\$this->(?:success|error|notFound|forbidden|unauthorized|paginated)\(\s*'((?:[^'\\]|\\.)*)'"
)
# Second-argument message of the ApiResponse helpers.
SECOND_ARG = re.compile(
    r"\$this->(?:validationError|withResource|created|updated|deleted)\([^()]*?,\s*'((?:[^'\\]|\\.)*)'"
)
# ValidationException::withMessages([...]) blocks (whole file, once).
WITH_MESSAGES = re.compile(r"withMessages\((\[.*?\])\),?", re.S)
# abort($code, '...')
ABORT = re.compile(r"\babort\(\s*\d{3}\s*,\s*'((?:[^'\\]|\\.)*)'")
# throw new SomeException('...')
THROW = re.compile(r"throw new (?:[A-Z]\w*)?(?:Exception|Error)\(\s*'((?:[^'\\]|\\.)*)'")
# audit trail descriptions, e.g. $this->audit->log($id, 'auth.login', 'User logged in.', $request)
AUDIT = re.compile(r"\$this->audit->log\([^,]+,\s*'[^']*',\s*'((?:[^'\\]|\\.)*)'")

# Any quoted string inside a withMessages array.
IN_MESSAGES = re.compile(r"'(?:[^'\\]|\\.)*'")


def unescape(s):
    return s.replace("\\'", "'").replace("\\\\", "\\")


def is_message(text):
    """Reject values that are not prose a user would ever read."""
    t = text.strip()
    if len(t) < 4:
        return False
    if not re.search(r"[A-Za-z]{2}", t):
        return False
    # A bare identifier, enum, or key.
    if re.fullmatch(r"[A-Z0-9_]+", t):
        return False
    if re.fullmatch(r"[a-z0-9]+([._-][a-z0-9]+)+", t):
        return False
    # A path, url, or storage key.
    if t.startswith("/") or "://" in t:
        return False
    if re.search(r"[{}%$\\]", t):
        return False
    if not re.match(r"[A-Z]", t):
        return False
    return True


def add(found, text, loc):
    if not is_message(text):
        return
    if text.startswith(("common.", "auth.", "validation.", "passwords.")):
        return
    found.setdefault(text, {})[loc] = True


found = {}
for path in sorted(APP.rglob("*.php")):
    src = path.read_text(encoding="utf-8")
    lines = src.split("\n")
    # withMessages blocks are multi-line: match them once over the whole file.
    for m in WITH_MESSAGES.finditer(src):
        block = m.group(1)
        for qm in IN_MESSAGES.finditer(block):
            if block[: qm.start()].rstrip().endswith("__("):
                continue  # already localised
            add(found, unescape(qm.group(0)[1:-1]), f"{path}:{src[:m.start()].count(chr(10)) + 1}")
    for i, line in enumerate(lines, 1):
        for pattern in (FIRST_ARG, SECOND_ARG, ABORT, THROW, AUDIT):
            for m in pattern.finditer(line):
                add(found, unescape(m.group(1)), f"{path}:{i}")

out = {k: sorted(v) for k, v in sorted(found.items())}
print(json.dumps(out, indent=1, ensure_ascii=False))
total = sum(len(v) for v in out.values())
print(f"--- {len(out)} distinct message(s), {total} site(s) ---", file=sys.stderr)