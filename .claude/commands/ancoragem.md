---
description: Audita a ancoragem de uma Geracao — toda peça de IA tem fonte rastreável?
argument-hint: "[id da Geracao ou vazio p/ a última]"
allowed-tools: Bash, Read
---
Audite a ancoragem da Geracao **$ARGUMENTS** (ou a última, se vazio). Para cada item gerado (bullet de resumo / card / questão):
1. Confirme que referencia ≥1 `pagina_id`/`chunk_id` existente e que estava no escopo recuperado.
2. Sinalize **fonte fantasma** (referência a página inexistente).
3. Em simulado: confirme que **nenhum distrator também é correto**.

Apresente as violações em tabela (item · problema · fonte citada). Havendo violação, é bug do verificador (`specs/02`) — proponha a correção; não maquie o resultado.
