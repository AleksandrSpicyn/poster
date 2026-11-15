FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg git unzip libzip-dev libicu-dev libonig-dev libsqlite3-dev curl \
    && docker-php-ext-install pdo_mysql pdo_sqlite bcmath intl zip pcntl exif \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY deploy/install-codex.sh /usr/local/bin/install-poster-codex
RUN bash /usr/local/bin/install-poster-codex
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN groupadd -g ${GROUP_ID} appuser && useradd -m -u ${USER_ID} -g appuser appuser
RUN sed -i 's/user = www-data/user = appuser/; s/group = www-data/group = appuser/' /usr/local/etc/php-fpm.d/www.conf
WORKDIR /var/www
