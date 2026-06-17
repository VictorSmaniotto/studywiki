#!/usr/bin/env bash
# Stop hook — grava snapshot cumulativo de tokens por (sessão, modelo).
# Cada Stop adiciona N linhas em .harness/tokens.jsonl (uma por modelo usado).
# Os valores são CUMULATIVOS para a sessão — agregadores devem pegar o MAX por (session, model).

set -euo pipefail

input=$(cat)
transcript=$(echo "$input" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('transcript_path',''))")
session=$(echo "$input"    | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('session_id',''))")

[[ -z "$transcript" || ! -f "$transcript" ]] && exit 0

mkdir -p .harness
ts=$(date -u +%Y-%m-%dT%H:%M:%S.%3NZ)

# Lê o transcript JSONL, filtra mensagens assistant com usage,
# agrupa por modelo e soma tokens. Cada modelo vira uma linha em tokens.jsonl.
python3 - "$transcript" "$ts" "$session" << 'PYEOF' >> .harness/tokens.jsonl
import json, sys
from collections import defaultdict

transcript_path, ts, session = sys.argv[1], sys.argv[2], sys.argv[3]

groups = defaultdict(lambda: {"messages": 0, "input": 0, "output": 0, "cache_creation": 0, "cache_read": 0})

with open(transcript_path) as f:
    for line in f:
        line = line.strip()
        if not line:
            continue
        try:
            entry = json.loads(line)
        except json.JSONDecodeError:
            continue
        if entry.get("type") != "assistant":
            continue
        usage = entry.get("message", {}).get("usage")
        if not usage:
            continue
        model = entry.get("message", {}).get("model", "unknown")
        g = groups[model]
        g["messages"] += 1
        g["input"]          += usage.get("input_tokens", 0)
        g["output"]         += usage.get("output_tokens", 0)
        g["cache_creation"] += usage.get("cache_creation_input_tokens", 0)
        g["cache_read"]     += usage.get("cache_read_input_tokens", 0)

for model, g in groups.items():
    print(json.dumps({"ts": ts, "session_id": session, "model": model, **g}))
PYEOF

exit 0
