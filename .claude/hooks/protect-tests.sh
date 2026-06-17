#!/usr/bin/env bash
set -euo pipefail

input=$(cat)
cmd=$(echo "$input" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('tool_input',{}).get('command',''))")
[[ -z "$cmd" ]] && exit 0

# Bloqueia rm/mv em arquivos de teste
if echo "$cmd" | grep -qE '(rm|unlink|mv)\s.*tests/'; then
  cat >&2 <<EOF
Bloqueado: remoção ou renomeação de arquivos de teste requer aprovação explícita.

  Tentativa: $cmd

  Para deletar um teste, peça ao usuário confirmação antes de prosseguir.
EOF
  exit 2
fi
exit 0
