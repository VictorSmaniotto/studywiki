#!/usr/bin/env bash
set -euo pipefail

input=$(cat)
mkdir -p .harness

ts=$(date -u +%Y-%m-%dT%H:%M:%S.%3NZ)
branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "?")

# enriquece o evento original com timestamp e branch
echo "$input" | python3 -c "
import json, sys
ts = sys.argv[1]; branch = sys.argv[2]
d = json.load(sys.stdin)
print(json.dumps({'ts': ts, 'branch': branch, **d}))
" "$ts" "$branch" >> .harness/events.jsonl
exit 0
