#!/bin/sh
# Сборка и запуск bitrix-php (на сервере нет плагина docker compose).
# Запуск: cd /opt/bitrix/infra && sh run.sh
set -e
docker build -t bitrix-php:8.3 .
docker rm -f bitrix-php 2>/dev/null || true
docker run -d --name bitrix-php --restart unless-stopped --network host \
  -v /var/www/bitrix:/var/www/bitrix \
  -v /opt/bitrix/secrets:/opt/bitrix/secrets:ro \
  bitrix-php:8.3
