#!/usr/bin/env bash
# PreToolUse Bash — bloqueia comandos destrutivos, leitura de .env e secrets inline.
input=$(cat)
cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // empty')
[ -z "$cmd" ] && exit 0

# Comandos destrutivos
if printf '%s' "$cmd" | grep -qiE '(\brm[[:space:]]+-rf[[:space:]]+/|DROP[[:space:]]+(TABLE|DATABASE)|git[[:space:]]+push[[:space:]].*--force|>[[:space:]]*/dev/sd|mkfs)'; then
  echo "BLOQUEADO: comando potencialmente destrutivo — rode você mesmo se for intencional: $cmd" >&2
  exit 2
fi

# migrate:fresh/reset fora de teste
if printf '%s' "$cmd" | grep -qE 'artisan[[:space:]]+migrate:(fresh|reset)'; then
  if ! printf '%s' "$cmd" | grep -qE '\-\-env=testing'; then
    echo "BLOQUEADO: migrate:fresh/reset apaga dados. Use --env=testing nos testes, ou rode manualmente." >&2
    exit 2
  fi
fi

# Leitura de .env via Bash (exceto .env.example)
if printf '%s' "$cmd" | grep -qE '\.env(\.[a-zA-Z]+)?' && ! printf '%s' "$cmd" | grep -qE '\.env\.example'; then
  echo "BLOQUEADO: leitura de .env via Bash não é permitida — use config() ou env() no código." >&2
  exit 2
fi

# Chave Anthropic hardcoded no comando
if printf '%s' "$cmd" | grep -qE 'sk-ant-api[0-9]+-[A-Za-z0-9_-]{20,}'; then
  echo "BLOQUEADO: chave Anthropic hardcoded no comando — use env('ANTHROPIC_API_KEY')." >&2
  exit 2
fi

exit 0
