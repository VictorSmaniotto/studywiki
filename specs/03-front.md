---
titulo: Front-end (fino)
tipo: spec
nivel_de_rigor: leve
criado: 2026-06-16
---

# 03 — Front-end

> Spec **leve**: o front é casca sobre os geradores. Não invente UX complexa antes de os geradores funcionarem no CLI (Fase 1–2).

## Telas (Livewire 4, Tailwind; usar skill frontend-design quando construir)
1. **Biblioteca** — lista disciplinas e contagem de páginas (vem do `sync`). Busca por título/tag.
2. **Disciplina** — páginas da disciplina + botões: "Gerar resumo", "Gerar flashcards", "Gerar simulado".
3. **Resumo / Flashcards** — render do JSON da `Geracao`, com link de fonte em cada item (abre a página no app ou aponta o `path` na vault).
4. **Simulado** — uma questão por vez (ou lista), aluno marca a–e, ao enviar mostra **gabarito comentado** com fontes. Salva `N acertos de M` no histórico.
5. **Admin (Filament)** — disparar `sync`/`embed`, ver `Geracao` (status, custo, rejeições), inspecionar chunks. É o painel de observabilidade.

## Princípios
- Toda peça de conteúdo gerado mostra suas fontes (regra 1). Sem fonte visível na UI = bug.
- Loading states nos geradores (chamada de LLM leva segundos). Erro de geração rejeitada → mensagem clara, não tela branca.
