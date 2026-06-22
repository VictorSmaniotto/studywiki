# StudyWiki em 3 plataformas — Web · Desktop · Mobile

> Guia operacional. Leia antes de mexer em qualquer código que possa afetar mais de uma plataforma.
> A decisão por trás disto está no [ADR-0003](adr/0003-nativephp-branches-desktop-mobile.md).

## Visão geral

Um único núcleo Laravel/Livewire alimenta três "cascas":

```
        NÚCLEO COMPARTILHADO  (Laravel · Livewire · Services · Models · Views)
        as telas, a lógica de IA, retrieval, banco — tudo nasce aqui
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
      WEB                  DESKTOP                MOBILE
   navegador             Electron              Android/iOS
   Sail / servidor       nativephp/desktop ^2  nativephp/mobile ^3
   = branch `main`       = `feature/nativephp` = `feature/nativephp-mobile`
```

**Por que branches separados:** `nativephp/desktop` e `nativephp/mobile` têm `conflict` declarado no Composer — não podem ser instalados juntos. Detalhe no ADR-0003.

## Mapa dos branches

| Branch | Plataforma | Pacote nativo | Banco | Nasce de |
|---|---|---|---|---|
| `main` | Web | — (nenhum) | Postgres + pgvector | — |
| `feature/nativephp` | Desktop | `nativephp/desktop` ^2 | Postgres + pgvector | T7.1 (API REST) |
| `feature/nativephp-mobile` | Mobile | `nativephp/mobile` ^3 | SQLite local (offline) + API | T7.1 (API REST) |

A **API REST (T7.1)** é a fronteira comum: o mobile (e qualquer cliente externo) usa-a para IA/retrieval, que continuam no servidor.

## Onde alterar o quê — REGRA DE OURO

**Pergunte: "isto é do núcleo ou da plataforma?"**

- **Núcleo** (tela, componente Livewire, service de IA/retrieval, model, migration de domínio, view compartilhada, API):
  → altere na **base** e **propague para todos os branches** (ver fluxo abaixo).
  → Idealmente o commit de núcleo entra primeiro e os branches de plataforma fazem `rebase`/`merge`.

- **Específico de plataforma** (só faz sentido naquela casca):
  → altere **somente no branch da plataforma**. Nunca leve para `main`.
  - Desktop (`feature/nativephp`): `app/Providers/NativeAppServiceProvider.php` (janela, menu, atalhos), qualquer `use Native\Desktop\…`, o PDF via diálogo nativo em `SimuladoPage::salvarPdfNativo`.
  - Mobile (`feature/nativephp-mobile`): `app/Providers/NativeServiceProvider.php`, layout/bottom-nav mobile, troca para SQLite em `config/database.php`, qualquer `use Native\Mobile\…`.

> ⚠️ **Não importe `Native\Desktop\…` ou `Native\Mobile\…` em código do núcleo.** O pacote não existe nos outros branches e o autoload quebra (silenciosamente, inclusive — já aconteceu). Se precisar de um recurso nativo a partir de uma tela compartilhada, proteja com `config('nativephp-internal.running')` e resolva a classe via `app(...)` dentro do branch da plataforma.

## Fluxo para uma mudança de NÚCLEO (afeta as 3)

```bash
# 1. base: faça e teste a mudança no núcleo
git checkout main
# ...edita, ./vendor/bin/sail artisan test, commit...

# 2. desktop: traz a mudança do núcleo
git checkout feature/nativephp
git rebase main          # ou: git merge main
./vendor/bin/sail composer install   # sincroniza o desktop no vendor
./vendor/bin/sail artisan test

# 3. mobile: idem
git checkout feature/nativephp-mobile
git rebase main          # ou: git merge main
./vendor/bin/sail composer install   # sincroniza o mobile no vendor
./vendor/bin/sail artisan test
```

> Ao trocar de branch entre desktop e mobile, **sempre rode `composer install`** depois: o `vendor/` precisa refletir o pacote daquele branch (desktop sai, mobile entra, ou vice-versa).

## Rodar / buildar cada plataforma

| | Comando | Onde roda |
|---|---|---|
| Web | `./vendor/bin/sail up -d` → navegador | Sail (WSL ok) |
| Desktop | `composer native:dev` (ou `php artisan native:run`) | **Host com GUI** (WSLg/Windows). Não verificável nesta sandbox. |
| Mobile | `php artisan native:run android` | **Windows/macOS/Linux nativo** — **NÃO roda sob WSL** |

### Restrições de ambiente (importantes)
- **Mobile + WSL não combinam.** `native:install`/`native:run android` precisam ser executados a partir do **Windows** (CMD/PowerShell), não do WSL. O scaffolding PHP/Laravel (config, provider, telas, SQLite) foi feito no WSL; o build do APK e o emulador são do lado Windows.
- **Desktop precisa de display.** A janela Electron abre no host (WSLg no Windows 11). Testes Pest cobrem a lógica; a janela/atalhos são verificados manualmente.

## Pendências de ambiente do dono (mobile)

1. **`NATIVEPHP_APP_ID` no `.env`** — o `native:install` gravou um valor aleatório (`com.sail.stonewavebrave`). Troque por `com.rockandcode.studywiki` (o `.env` não é editado por automação aqui).
2. **Build Android** — rode `php artisan native:install` e `php artisan native:run android` a partir do **Windows**, com Android SDK/emulador configurados.
3. **Acesso de rede do emulador** (T7.4.1) — o emulador não enxerga `localhost` do host; aponte `APP_URL` para o IP da LAN (`192.168.x.x`) ou um túnel, para o app alcançar o servidor (API/IA).

## Banco no mobile
- Em runtime nativo (`NATIVEPHP_RUNNING` setado) o `config/database.php` usa **SQLite**.
- SQLite guarda só dados offline: **flashcards (SM-2)** e **trilha/streak**.
- **IA e retrieval vetorial não rodam no SQLite** (sem pgvector) — vão para o servidor via API REST.
