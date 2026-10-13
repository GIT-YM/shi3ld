.PHONY: env build up workers down migrate seed logs status integrate
env:
	./bin/init-env
build:
	docker compose build php-magento php-laravel
up:
	docker compose up -d db opensearch magento-cache laravel-cache mailpit php-magento php-laravel nginx
workers:
	docker compose --profile workers up -d magento-cron laravel-queue laravel-scheduler
down:
	docker compose --profile workers down
migrate:
	docker compose exec php-magento php bin/magento setup:upgrade
	docker compose exec php-laravel php artisan migrate --database=mysql
seed:
	docker compose exec php-laravel php artisan db:seed
logs:
	docker compose logs --tail=100
status:
	docker compose ps
integrate:
	./bin/integrate-site
