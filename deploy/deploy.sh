#!/bin/bash
set -e

#=============================================================================
# 🌊 OCEAN E-COMMERCE — AUTOMATED DEPLOYMENT SCRIPT
# VPS: 103.90.225.118
# Domain: ocean.pro.vn / api.ocean.pro.vn
# Hướng dẫn: Chạy script này trên VPS (root user)
#=============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

DOMAIN="ocean.pro.vn"
API_DOMAIN="api.ocean.pro.vn"
VPS_IP="103.90.225.118"
PROJECT_DIR="/home/ocean/ocean"
GIT_REPO="https://github.com/Bongdepchaii/PHP3-OCEAN.git"

echo -e "${CYAN}${BOLD}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║         🌊 OCEAN E-COMMERCE DEPLOY SCRIPT               ║"
echo "║         Domain: ${DOMAIN}                        ║"
echo "║         VPS IP: ${VPS_IP}                    ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# ============================================
# STEP 1: System Update
# ============================================
echo -e "\n${BLUE}[1/9]${NC} ${BOLD}Cập nhật hệ thống...${NC}"
apt update && apt upgrade -y
echo -e "${GREEN}✔ Hệ thống đã cập nhật${NC}"

# ============================================
# STEP 2: Create user (nếu chưa có)
# ============================================
echo -e "\n${BLUE}[2/9]${NC} ${BOLD}Tạo user ocean...${NC}"
if id "ocean" &>/dev/null; then
    echo -e "${YELLOW}→ User 'ocean' đã tồn tại, bỏ qua${NC}"
else
    adduser --disabled-password --gecos "" ocean
    usermod -aG sudo ocean
    echo -e "${GREEN}✔ User 'ocean' đã tạo${NC}"
fi

# ============================================
# STEP 3: Install Docker
# ============================================
echo -e "\n${BLUE}[3/9]${NC} ${BOLD}Cài Docker + Docker Compose...${NC}"
if command -v docker &> /dev/null; then
    echo -e "${YELLOW}→ Docker đã cài, version: $(docker --version)${NC}"
else
    apt install -y apt-transport-https ca-certificates curl software-properties-common
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    apt update
    apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
    usermod -aG docker ocean
    echo -e "${GREEN}✔ Docker đã cài thành công${NC}"
fi

# ============================================
# STEP 4: Install Nginx + Certbot
# ============================================
echo -e "\n${BLUE}[4/9]${NC} ${BOLD}Cài Nginx + Certbot...${NC}"
apt install -y nginx certbot python3-certbot-nginx
systemctl enable nginx
systemctl start nginx
echo -e "${GREEN}✔ Nginx + Certbot đã cài${NC}"

# ============================================
# STEP 5: Clone source code
# ============================================
GIT_BRANCH="daiduong"

echo -e "\n${BLUE}[5/9]${NC} ${BOLD}Clone source code...${NC}"
if [ -d "$PROJECT_DIR" ]; then
    echo -e "${YELLOW}→ Source code đã tồn tại tại ${PROJECT_DIR}${NC}"
    echo -e "${YELLOW}→ Pull latest code từ branch ${GIT_BRANCH}...${NC}"
    cd $PROJECT_DIR && git fetch origin && git checkout $GIT_BRANCH && git pull origin $GIT_BRANCH || true
else
    mkdir -p /home/ocean
    git clone -b $GIT_BRANCH $GIT_REPO $PROJECT_DIR
    chown -R ocean:ocean /home/ocean
    echo -e "${GREEN}✔ Source code đã clone (branch: ${GIT_BRANCH})${NC}"
fi
cd $PROJECT_DIR

# ============================================
# STEP 6: Setup .env
# ============================================
echo -e "\n${BLUE}[6/9]${NC} ${BOLD}Thiết lập file .env...${NC}"

if [ -f "$PROJECT_DIR/deploy/.env.production" ]; then
    cp $PROJECT_DIR/deploy/.env.production $PROJECT_DIR/.env
    echo -e "${GREEN}✔ Đã copy .env.production → .env${NC}"
else
    echo -e "${RED}✘ Không tìm thấy deploy/.env.production${NC}"
    echo -e "${YELLOW}→ Sử dụng .env.example nếu có...${NC}"
    cp $PROJECT_DIR/.env.example $PROJECT_DIR/.env 2>/dev/null || true
fi

# Tạo frontend .env cho VITE_* variables (cần cho build time)
echo -e "${YELLOW}→ Tạo frontend/.env cho build...${NC}"
cat > $PROJECT_DIR/frontend/.env << 'FRONTEND_ENV'
VITE_BASE_URL=https://api.ocean.pro.vn
VITE_API_URL=https://api.ocean.pro.vn/api
VITE_API_STORAGE=https://api.ocean.pro.vn/storage
VITE_TURNSTILE_SITE_KEY=0x4AAAAAACtb_s7UV7YfOvwg
VITE_GOOGLE_CLIENT_ID=69477374031-v9nattjdc51dj9hb20qntpq6dkqedem8.apps.googleusercontent.com
VITE_TOKEN_GHN=a715129f-37d0-11f1-84b6-de18d5436de6
VITE_SHOPID_GHN=5278356
VITE_REVERB_APP_KEY=ocean_realtime_key_2024
VITE_REVERB_HOST=api.ocean.pro.vn
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
FRONTEND_ENV
echo -e "${GREEN}✔ frontend/.env đã tạo${NC}"

# ============================================
# STEP 7: Add swap (quan trọng cho VPS 2GB RAM)
# ============================================
echo -e "\n${BLUE}[7/9]${NC} ${BOLD}Thiết lập swap (tránh hết RAM khi build)...${NC}"
if [ -f /swapfile ]; then
    echo -e "${YELLOW}→ Swap đã tồn tại, bỏ qua${NC}"
else
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo -e "${GREEN}✔ Đã thêm 2GB swap${NC}"
fi

# ============================================
# STEP 8: Build & Run Docker Compose
# ============================================
echo -e "\n${BLUE}[8/9]${NC} ${BOLD}Build và khởi chạy Docker Compose...${NC}"
cd $PROJECT_DIR

# Dùng production docker-compose nếu có, không thì dùng file gốc
if [ -f "deploy/docker-compose.prod.yml" ]; then
    echo -e "${YELLOW}→ Sử dụng docker-compose.prod.yml...${NC}"
    docker compose -f deploy/docker-compose.prod.yml build --no-cache
    docker compose -f deploy/docker-compose.prod.yml up -d
else
    echo -e "${YELLOW}→ Sử dụng docker-compose.yml gốc...${NC}"
    docker compose build --no-cache
    docker compose up -d
fi

echo -e "${YELLOW}→ Đợi services khởi động (30s)...${NC}"
sleep 30

# Post-deploy commands
echo -e "${YELLOW}→ Chạy migrations + setup...${NC}"
docker compose exec -T backend php artisan key:generate --force 2>/dev/null || true
docker compose exec -T backend php artisan jwt:secret --force 2>/dev/null || true
docker compose exec -T backend php artisan migrate --force 2>/dev/null || true
docker compose exec -T backend php artisan storage:link 2>/dev/null || true
docker compose exec -T backend php artisan config:cache 2>/dev/null || true
docker compose exec -T backend php artisan route:cache 2>/dev/null || true

echo -e "${GREEN}✔ Docker Compose đã chạy${NC}"

# ============================================
# STEP 9: Setup Host Nginx (SSL Reverse Proxy)
# ============================================
echo -e "\n${BLUE}[9/9]${NC} ${BOLD}Cấu hình Nginx reverse proxy...${NC}"

# Copy nginx config
cp $PROJECT_DIR/deploy/nginx-host.conf /etc/nginx/sites-available/ocean

# Enable site
ln -sf /etc/nginx/sites-available/ocean /etc/nginx/sites-enabled/ocean
rm -f /etc/nginx/sites-enabled/default

# Test + reload
nginx -t && systemctl reload nginx
echo -e "${GREEN}✔ Nginx đã cấu hình${NC}"

# ============================================
# Setup Firewall
# ============================================
echo -e "\n${BOLD}Cấu hình Firewall...${NC}"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
echo -e "${GREEN}✔ Firewall đã bật${NC}"

# ============================================
# SSL Certificate
# ============================================
echo -e "\n${BOLD}Cài SSL Certificate (Let's Encrypt)...${NC}"
echo -e "${YELLOW}⚠️  Đảm bảo DNS đã trỏ ${DOMAIN} và ${API_DOMAIN} về ${VPS_IP}${NC}"
echo -e "${YELLOW}   Nếu chưa trỏ DNS, script sẽ tự động thử lại sau.${NC}"
echo ""

read -p "DNS đã trỏ xong chưa? (y/n): " dns_ready
if [ "$dns_ready" == "y" ] || [ "$dns_ready" == "Y" ]; then
    certbot --nginx -d $DOMAIN -d www.$DOMAIN -d $API_DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN || {
        echo -e "${RED}✘ Certbot failed. Có thể DNS chưa propagate. Thử lại sau bằng lệnh:${NC}"
        echo -e "   sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} -d ${API_DOMAIN}"
    }
else
    echo -e "${YELLOW}→ Bỏ qua SSL. Chạy lệnh sau khi DNS đã trỏ:${NC}"
    echo -e "   sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} -d ${API_DOMAIN}"
fi

# ============================================
# Summary
# ============================================
echo -e "\n${CYAN}${BOLD}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║          🎉 DEPLOY HOÀN TẤT!                           ║"
echo "╠══════════════════════════════════════════════════════════╣"
echo "║                                                          ║"
echo "║  Frontend: https://${DOMAIN}                     ║"
echo "║  API:      https://${API_DOMAIN}                 ║"
echo "║                                                          ║"
echo "║  Docker:   docker compose ps                              ║"
echo "║  Logs:     docker compose logs -f                         ║"
echo "║                                                          ║"
echo "║  ⚠️  Nhớ:                                                ║"
echo "║  1. Trỏ DNS ocean.pro.vn → ${VPS_IP}        ║"
echo "║  2. Trỏ DNS api.ocean.pro.vn → ${VPS_IP}    ║"
echo "║  3. Chạy certbot nếu chưa cài SSL                       ║"
echo "║  4. Kiểm tra .env có đúng secrets chưa                   ║"
echo "║                                                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
