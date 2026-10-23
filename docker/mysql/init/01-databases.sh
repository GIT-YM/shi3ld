#!/bin/bash
set -euo pipefail
# Passwords are generated as hexadecimal strings by bin/init-env.
for value in "$MAGENTO_DB_PASSWORD" "$LARAVEL_DB_PASSWORD"; do
    [[ "$value" =~ ^[a-f0-9]{48}$ ]] || { echo 'Use bin/init-env to generate database passwords.' >&2; exit 1; }
done
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS magento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS laravel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'magento'@'%' IDENTIFIED BY '${MAGENTO_DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'laravel'@'%' IDENTIFIED BY '${LARAVEL_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON magento.* TO 'magento'@'%';
GRANT ALL PRIVILEGES ON laravel.* TO 'laravel'@'%';
SQL
