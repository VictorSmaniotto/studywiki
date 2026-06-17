---
description: Checa se o código diverge das specs e ADRs (drift check, estilo LINT).
allowed-tools: Read, Bash, Grep
---
Compare o estado do código com @specs/ e @docs/adr/. Procure por:
- regra do CLAUDE.md violada (escrita na vault, gerador sem verificador, vetor antes da Fase 4);
- decisão de ADR contrariada no código;
- item de spec firme (`01`, `02`) ainda não refletido no código.

Liste as divergências e proponha: ajustar o código, **ou** — se a decisão mudou de propósito — abrir um ADR novo que supersede o antigo. Não altere spec aceita sem registrar.
