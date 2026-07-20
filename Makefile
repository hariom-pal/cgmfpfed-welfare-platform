.PHONY: help check lint fix health test

help:
	@echo "Available commands:"
	@echo "  make check   - Run all quality checks"
	@echo "  make lint    - Run PHPStan"
	@echo "  make fix     - Run Laravel Pint"
	@echo "  make health  - Run application health check"
	@echo "  make test    - Run PHPUnit tests"

check:
	./vendor/bin/pint
	vendor/bin/phpstan analyse
	php artisan app:health

lint:
	vendor/bin/phpstan analyse

fix:
	./vendor/bin/pint

health:
	php artisan app:health

test:
	php artisan test

migrate:
	php artisan migrate

seed:
	php artisan db:seed

refresh:
	php artisan migrate:fresh --seed
