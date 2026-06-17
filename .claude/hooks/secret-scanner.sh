#!/usr/bin/env bash
set -euo pipefail

input=$(cat)
file=$(echo "$input" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('tool_input',{}).get('file_path',''))")
content=$(echo "$input" | python3 -c "import json,sys; d=json.load(sys.stdin); i=d.get('tool_input',{}); print(i.get('new_string','') or i.get('content',''))")

[[ -z "$content" ]] && exit 0

# Ignora arquivos de exemplo, testes, factories e documentação
echo "$file" | grep -qE '\.example$|\.md$|/tests/|/Factories/|/factories/|/database/seeders/' && exit 0

violations=()

# API key / secret hardcoded (valor com 16+ chars após =)
if echo "$content" | grep -qiE "(api[_-]?key|secret|password|bearer|token)\s*=\s*['\"][^'\"]{16,}"; then
    violations+=("Possível secret hardcoded — use config() ou env()")
fi

# sk-ant-api (Anthropic key pattern)
if echo "$content" | grep -qE "sk-ant-api[0-9]+-[A-Za-z0-9_-]{20,}"; then
    violations+=("Chave Anthropic hardcoded — use env('ANTHROPIC_API_KEY')")
fi

if [ ${#violations[@]} -gt 0 ]; then
    echo "Bloqueado: secret ou dado sensível hardcoded detectado." >&2
    echo "" >&2
    for v in "${violations[@]}"; do
        echo "  ✗ $v" >&2
    done
    echo "" >&2
    echo "  Use variáveis de ambiente via env() ou config()." >&2
    exit 2
fi

exit 0
