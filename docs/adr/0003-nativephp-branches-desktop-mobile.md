# ADR-0003: NativePHP desktop e mobile em branches separados

- Status: Accepted — 2026-06-22
- Supersede: a decisão de "monorepo NativePHP" registrada no `progress.md` (sessão 11), que assumia desktop + mobile instalados juntos no mesmo `studywiki-app`.

## Context
O objetivo é entregar o StudyWiki em três formatos a partir do mesmo código de telas (Livewire):
**Web** (navegador, via Sail/servidor), **Desktop** (Electron) e **Mobile** (Android/iOS).

Ao instalar as versões mais recentes descobrimos um impedimento de empacotamento:

- `nativephp/desktop` 2.2.1 declara no próprio `composer.json`:
  ```json
  "conflict": { "nativephp/mobile": "*", "nativephp/laravel": "*", "nativephp/electron": "*" }
  ```
  Ou seja, o desktop **recusa de propósito** conviver com **qualquer** versão do mobile no mesmo projeto. Não é colisão transitiva de versões — é uma regra intencional do NativePHP, porque cada pacote instala um runtime, comandos (`native:run`) e configs próprias que se sobrescreveriam.

Além disso, a stack Web/Desktop usa **Postgres + pgvector** (colunas `vector`, índices HNSW). O app mobile empacotado não tem Postgres nem pgvector; precisa de **SQLite local**, que não roda o retrieval vetorial.

## Decision
1. **Não instalar desktop e mobile no mesmo branch.** Cada plataforma nativa vive em seu branch:
   - `main` → **Web** (núcleo Laravel/Livewire, sem nenhuma dependência NativePHP).
   - `feature/nativephp` → **Desktop** = núcleo + `nativephp/desktop` ^2.
   - `feature/nativephp-mobile` → **Mobile** = núcleo + `nativephp/mobile` ^3.
2. **Núcleo compartilhado é a fonte da verdade.** Models, services (IA, retrieval), componentes Livewire e views vivem no núcleo e são reaproveitados pelas três cascas. Código específico de plataforma (imports `Native\Desktop\…` ou `Native\Mobile\…`, layout mobile, SQLite) fica **isolado no branch da plataforma**.
3. **O branch mobile nasce do commit da API REST (T7.1)**, antes do desktop, para herdar o núcleo limpo sem o pacote conflitante.
4. **Mobile usa SQLite só para dados offline** (flashcards SM-2, trilha/streak). Geração de IA e retrieval vetorial continuam no servidor, consumidos pela **API REST (T7.1)**.

## Consequences
- Positiva: cada branch resolve dependências sem conflito; o build de cada plataforma é previsível.
- Positiva: casa com a separação natural do NativePHP (você builda desktop **ou** mobile, nunca os dois ao mesmo tempo).
- Negativa: mudança no núcleo (tela, service, model) precisa ser propagada aos branches de plataforma (ver fluxo em `docs/nativephp.md`). É o custo de manter três cascas.
- Negativa: o mobile não faz RAG/embeddings localmente — depende da API/servidor para IA.
- Restrição de ambiente: o build Android do NativePHP **não roda sob WSL** (`native:install`/`native:run android` exigem Windows/macOS/Linux nativo). O desktop Electron exige GUI no host. Esses passos são executados pelo dono, fora desta sandbox.

## Compliance
- `docs/nativephp.md` descreve o fluxo de trabalho entre branches (onde alterar o quê e como propagar).
- `CLAUDE.md` tem a regra resumida (lida no início de toda sessão).
- `config/database.php` troca para SQLite quando `NATIVEPHP_RUNNING` está setado.
