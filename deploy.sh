#!/bin/bash

set -euo pipefail

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

if [ -f .env ]; then
    set -a
    source .env
    set +a
fi

echo -e "${GREEN}🚀 We are starting to deploy the project ${PWD}...${NC}"

# 1. Updating the code
echo -e "${GREEN}📥 Updating code from Git...${NC}"
git pull origin main

# 2. Installing dependencies
echo -e "${GREEN}📦 Installing Composer dependencies...${NC}"
composer install --no-interaction --prefer-dist --optimize-autoloader

# 3. Migrations and Seeds
echo -e "${GREEN}🛠️ We perform migrations and seeds...${NC}"
php artisan migrate --force
php artisan db:seed --force || echo -e "${RED}⚠️ Seed is not filled or is optional${NC}"

# 4. Clearing caches
echo -e "${GREEN}♻️ Cleaning and caching...${NC}"
php artisan cache:clear
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Queues
echo -e "${GREEN}🔁 Restart queue...${NC}"
php artisan queue:restart || echo -e "${RED}⚠️ Queue not configured? Skipped.${NC}"

# 6. Front (if the --with-frontend flag is passed)
if [[ "${1:-}" == "--with-frontend" ]]; then
    echo -e "${GREEN}🌐 Install frontend dependencies and build...${NC}"
    npm install
    npm run build
fi

echo -e "${GREEN}✅ Deployment completed!${NC}"
