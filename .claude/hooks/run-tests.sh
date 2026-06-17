#!/usr/bin/env bash
# Stop — roda a suíte ao tentar encerrar. Se vermelho, exit 2 força o Claude a continuar.
# Agressivo de propósito (ratchet). Para suavizar: troque 'exit 2' por 'exit 0'.
proj="${CLAUDE_PROJECT_DIR:-$PWD}"
cd "$proj" || exit 0
[ -f artisan ] || exit 0   # antes do scaffold (T0.1) não há suíte

sail_up() { "$proj/vendor/bin/sail" ps 2>/dev/null | grep -q "Up"; }

if sail_up; then
  if ! "$proj/vendor/bin/sail" artisan test >/tmp/studywiki-test.log 2>&1; then
    echo "Testes vermelhos — não finalize antes de corrigir:" >&2
    tail -n 30 /tmp/studywiki-test.log >&2
    exit 2
  fi
else
  echo "Sail não está rodando — inicie com ./vendor/bin/sail up -d e tente novamente." >&2
  exit 2
fi

echo "─ Testes verdes. Lembre de salvar decisões da sessão no mem0 (mcp__mem0__add_memory, agent_id: studywiki) antes de encerrar." >&2
exit 0
