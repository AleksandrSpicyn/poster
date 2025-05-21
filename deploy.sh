#!/bin/bash

# Остановка при ошибке
set -euo pipefail

# Цвета
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Загружаем .env переменные (если есть)
if [ -f .env ]; then
    set -a
    source .env
    set +a
fi

echo -e "${GREEN}🚀 Начинаем деплой проекта ${PWD}...${NC}"

# 1. Обновление кода
echo -e "${GREEN}📥 Обновляем код из Git...${NC}"
git pull origin main

# 2. Установка зависимостей
echo -e "${GREEN}📦 Устанавливаем Composer-зависимости...${NC}"
composer install --no-interaction --prefer-dist --optimize-autoloader

# 3. Миграции и сиды
echo -e "${GREEN}🛠️ Выполняем миграции и сиды...${NC}"
php artisan migrate --force
php artisan db:seed --force || echo -e "${RED}⚠️ Seed не выполнен или необязателен${NC}"

# 4. Очистка кешей
echo -e "${GREEN}♻️ Очистка и кеширование...${NC}"
php artisan cache:clear
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Права
echo -e "${GREEN}🔑 Настройка прав...${NC}"
chown -R "${WWW_USER:-www-data}":"${WWW_GROUP:-www-data}" .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 storage bootstrap/cache

# 6. Очереди
echo -e "${GREEN}🔁 Перезапуск очередей...${NC}"
php artisan queue:restart || echo -e "${RED}⚠️ Очередь не настроена? Пропущено.${NC}"

# 7. Фронт (если передан флаг --with-frontend)
if [[ "${1:-}" == "--with-frontend" ]]; then
    echo -e "${GREEN}🌐 Устанавливаем frontend-зависимости и билдим...${NC}"
    npm install
    npm run build
fi

echo -e "${GREEN}✅ Деплой завершен!${NC}"
