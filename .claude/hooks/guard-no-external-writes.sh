#!/usr/bin/env bash
# PreToolUse Write|Edit — bloqueia qualquer escrita fora do diretório do projeto.
# Garante a regra 2 do CLAUDE.md: a vault Obsidian (e tudo externo) é read-only.
input=$(cat)
file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')
[ -z "$file" ] && exit 0
proj="${CLAUDE_PROJECT_DIR:-$PWD}"
case "$file" in
  /*) abs="$file" ;;
  *)  abs="$proj/$file" ;;
esac
dir=$(cd "$(dirname "$abs")" 2>/dev/null && pwd) || dir=""
[ -n "$dir" ] && abs="$dir/$(basename "$abs")"
case "$abs" in
  "$proj"/*|"$proj") exit 0 ;;
  *) echo "BLOQUEADO: escrita fora do projeto não é permitida (vault é read-only). Alvo: $abs" >&2; exit 2 ;;
esac
