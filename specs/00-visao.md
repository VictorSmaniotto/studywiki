---
titulo: Visão (PRD-lite)
tipo: spec
nivel_de_rigor: leve
criado: 2026-06-16
---

# 00 — Visão

## Problema
Tenho uma vault Obsidian de estudos de Engenharia de Software (markdown + frontmatter, organizada em disciplinas/conceitos/fontes), mantida hoje por um agente no Claude Code. Quero consultá-la e gerar material de estudo (resumos, flashcards, simulados) por uma interface própria, com a IA **ancorada nas minhas anotações** — não no conhecimento genérico do modelo.

## Outcome desejado
Abrir o app, escolher uma disciplina ou tópico, e em um clique obter: um resumo, um baralho de flashcards, ou um simulado de prova no estilo universitário — tudo citando as páginas da vault de onde saiu. Fazer o simulado, ver o gabarito comentado com links para as fontes.

## Escopo
- **Uso pessoal, um usuário.** Sem multi-tenant, sem billing, sem auth complexa (login simples basta).
- Blast radius baixo: errar aqui custa minutos, não dinheiro/dado de terceiro. Por isso este projeto é **agentic + spec leve**, não SDD pesado. As únicas specs firmes são `01` (dados) e `02` (ancoragem) — porque reverter essas duas é caro.

## Não-objetivos
- Não é editor de notas (isso é o Obsidian). O app **lê** a vault.
- Não é plataforma para outros alunos (por ora). Se virar, revisitar specs `01` e a camada de produção do harness.
- Não treina/fine-tuna modelo. Usa API.
- Não reescreve a vault nem "corrige" suas anotações.

## Critério de sucesso (o teste honesto)
Se eu apagar este app amanhã, eu perco: a interface de estudo e os geradores ancorados. **Não** perco minha vault (ela é a fonte da verdade e continua intacta). Esse é o desenho certo.
