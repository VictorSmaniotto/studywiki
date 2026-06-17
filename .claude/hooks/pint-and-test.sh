#!/usr/bin/env bash
set -euo pipefail

input=$(cat)
file=$(echo "$input" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('tool_input',{}).get('file_path',''))")

[[ "$file" == *.php ]] || exit 0
[[ "$file" == */vendor/* || "$file" == */node_modules/* ]] && exit 0

proj="${CLAUDE_PROJECT_DIR:-$PWD}"
container_path="${file#$proj/}"

sail_up() { "$proj/vendor/bin/sail" ps 2>/dev/null | grep -q "Up"; }

# 1) Formata com Pint
if sail_up; then
    "$proj/vendor/bin/sail" bin pint "$container_path" --format=agent >&2 || true
elif [ -x "$proj/vendor/bin/pint" ]; then
    "$proj/vendor/bin/pint" "$file" >/dev/null 2>&1 || true
fi

# 2) Se for arquivo de teste, roda só ele
if [[ "$file" == *"/tests/"*.php ]]; then
    test_name=$(basename "$file" .php)
    echo "→ Rodando $test_name" >&2
    if sail_up; then
        if ! "$proj/vendor/bin/sail" artisan test --compact --filter="$test_name" >&2; then
            echo "↑ Teste falhou — corrija antes de prosseguir." >&2
            exit 2
        fi
    else
        echo "↑ Sail não está rodando — inicie com ./vendor/bin/sail up -d antes de continuar." >&2
        exit 2
    fi
fi
exit 0
