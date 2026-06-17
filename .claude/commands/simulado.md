---
description: Gera um simulado ancorado para uma disciplina/tópico.
argument-hint: "<disciplina> [--n=5] [--dif=medio]"
allowed-tools: Bash
---
Gere um simulado para: **$ARGUMENTS** (ex: `compiladores --n=5 --dif=dificil`), via `./vendor/bin/sail artisan studywiki:simulado`.
Se o comando não existir, me avise que a T1.4 está pendente.
Mostre as questões **sem revelar as respostas** e confirme que cada questão traz `fontes` apontando para páginas reais da vault. Depois que eu responder, mostre o gabarito comentado com os links de fonte.
