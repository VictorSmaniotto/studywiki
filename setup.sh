#!/usr/bin/env bash
# StudyWiki — setup automático para WSL/Linux (backend web via Laravel Sail)
# Uso: bash setup.sh
set -euo pipefail

# ── Cores ────────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${BLUE}▶${NC} $1"; }
success() { echo -e "${GREEN}✓${NC} $1"; }
warn()    { echo -e "${YELLOW}!${NC} $1"; }
error()   { echo -e "${RED}✗ ERRO:${NC} $1"; exit 1; }
header()  { echo -e "\n${BOLD}$1${NC}"; echo "$(printf '─%.0s' {1..50})"; }

# Nomes dos arquivos de configuração (definidos como variável para flexibilidade)
CF=".env"
CF_EXAMPLE="${CF}.example"

# ── Banner ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║      StudyWiki — Setup automático        ║${NC}"
echo -e "${BOLD}║      Backend Web (WSL + Docker Sail)     ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════╝${NC}"
echo ""

# ── Pré-requisitos ───────────────────────────────────────────────────────────
header "1. Verificando pré-requisitos"

command -v docker &>/dev/null || error "Docker não encontrado no PATH."
docker info &>/dev/null       || error "Docker não está rodando. Inicie o Docker Desktop."
success "Docker OK"

command -v git &>/dev/null || error "Git não encontrado. Rode: sudo apt install git"
success "Git OK"

# ── Arquivo de configuração ──────────────────────────────────────────────────
header "2. Configurando variáveis de ambiente"

if [ ! -f "$CF" ]; then
    [ -f "$CF_EXAMPLE" ] || error "$CF_EXAMPLE não encontrado. Verifique se está na raiz do projeto."
    cp "$CF_EXAMPLE" "$CF"
    info "Arquivo de configuração criado a partir do exemplo"

    echo ""
    echo -e "${YELLOW}Informe as variáveis obrigatórias:${NC}"
    echo ""

    read -rp "  ANTHROPIC_API_KEY (sk-ant-...): " _api_key
    [ -n "$_api_key" ] || error "ANTHROPIC_API_KEY não pode ser vazia."
    sed -i "s|^ANTHROPIC_API_KEY=.*|ANTHROPIC_API_KEY=${_api_key}|" "$CF"

    echo ""
    echo "  Vault Obsidian:"
    echo "  → Se a vault está em C:\\Users\\Voce\\Documents\\vault, no WSL fica:"
    echo "    /mnt/c/Users/Voce/Documents/vault"
    echo ""
    read -rp "  OBSIDIAN_VAULT_PATH: " _vault
    [ -n "$_vault" ] || error "OBSIDIAN_VAULT_PATH não pode ser vazio."
    sed -i "s|^OBSIDIAN_VAULT_PATH=.*|OBSIDIAN_VAULT_PATH=${_vault}|" "$CF"

    echo ""
    read -rp "  Orçamento mensal Anthropic em USD (padrão 3.25): " _budget
    _budget="${_budget:-3.25}"
    sed -i "s|^ANTHROPIC_BUDGET_USD=.*|ANTHROPIC_BUDGET_USD=${_budget}|" "$CF"

    read -rp "  Alerta de saldo baixo em USD (padrão 0.50): " _alert
    _alert="${_alert:-0.50}"
    sed -i "s|^ANTHROPIC_BUDGET_ALERT_USD=.*|ANTHROPIC_BUDGET_ALERT_USD=${_alert}|" "$CF"

    # Fila assíncrona: driver database (necessário para o ChatResponseJob)
    sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|" "$CF"

    success "Configuração salva"
else
    warn "Arquivo de configuração já existe — pulando. Verifique ANTHROPIC_API_KEY e OBSIDIAN_VAULT_PATH."
fi

# ── Composer ─────────────────────────────────────────────────────────────────
header "3. Dependências PHP"

if [ ! -d vendor ]; then
    info "Instalando via imagem laravelsail/php84-composer..."
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php84-composer:latest \
        composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader
    success "Dependências PHP instaladas"
else
    warn "vendor/ já existe — rodando composer install incremental..."
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php84-composer:latest \
        composer install --no-interaction --no-progress --prefer-dist
    success "OK"
fi

# ── Sail up ──────────────────────────────────────────────────────────────────
header "4. Subindo containers (Sail)"

./vendor/bin/sail up -d
success "Containers no ar"

info "Aguardando PostgreSQL aceitar conexões..."
_retries=0
until ./vendor/bin/sail artisan db:show &>/dev/null; do
    _retries=$((_retries + 1))
    [ $_retries -ge 20 ] && error "PostgreSQL não respondeu após 40s. Verifique: sail logs pgsql"
    sleep 2
done
success "PostgreSQL pronto"

# ── App key ──────────────────────────────────────────────────────────────────
header "5. APP_KEY"

# --force para não pedir confirmação interativa; é seguro em setup inicial
./vendor/bin/sail artisan key:generate --force
success "APP_KEY gerada"

# ── Banco de dados ───────────────────────────────────────────────────────────
header "6. Banco de dados"

./vendor/bin/sail artisan migrate --force
success "Migrations aplicadas"

info "Garantindo tabela de filas (jobs)..."
./vendor/bin/sail artisan queue:table 2>/dev/null || true
./vendor/bin/sail artisan migrate --force
success "Tabela de filas OK"

# ── Assets JS ────────────────────────────────────────────────────────────────
header "7. Assets JavaScript"

info "Instalando dependências npm..."
./vendor/bin/sail npm install
info "Compilando para produção..."
./vendor/bin/sail npm run build
success "Assets compilados"

# ── Sync vault ───────────────────────────────────────────────────────────────
header "8. Sincronizando vault Obsidian"

./vendor/bin/sail artisan studywiki:sync
success "Vault sincronizada"

./vendor/bin/sail artisan storage:link &>/dev/null || true

# ── Resumo ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║         Setup concluído com sucesso!     ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${GREEN}Web:${NC}   http://localhost"
echo -e "  ${GREEN}Admin:${NC} http://localhost/admin"
echo ""
echo -e "${BOLD}NativePHP (rodar no Windows, fora do WSL):${NC}"
echo ""
echo "  Desktop:"
echo "    git checkout feature/nativephp"
echo "    composer install && php artisan native:run"
echo ""
echo "  Android (requer Android Studio instalado):"
echo "    git checkout feature/nativephp-mobile"
echo "    composer install && php artisan native:run android"
echo ""
echo "  No arquivo de config do Windows, aponte o APP_URL para:"
echo "    http://10.0.2.2        (emulador Android)"
echo "    http://<IP-da-rede>    (dispositivo fisico — rode 'hostname -I' no WSL)"
echo ""
echo -e "  ${YELLOW}Embeddings vetoriais (opcional, Fase 4):${NC}"
echo "    ./vendor/bin/sail artisan studywiki:embed"
echo ""
