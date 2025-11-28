# Makefile for CardsLite Bot

# Определяем окружение из .env или по умолчанию local
ifneq (,$(wildcard ./.env))
    include .env
    export
endif

APP_ENV ?= local
COMPOSE_FILE = $(if $(filter production,$(APP_ENV)),docker-compose.prod.yml,docker-compose.yml)

.PHONY: help
help: ## Показать эту справку
	@echo "CardsLite Bot - Управление проектом"
	@echo ""
	@echo "Текущее окружение: $(APP_ENV)"
	@echo "Docker Compose файл: $(COMPOSE_FILE)"
	@echo ""
	@echo "Доступные команды:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*##' $(MAKEFILE_LIST) | sed -E 's/^[^:]+:([a-zA-Z_-]+):.*##(.*)$$/  \1 | \2/' | column -t -s '|'

# ==================== DOCKER ====================

.PHONY: up
up: ## Запустить контейнеры
	docker compose -f $(COMPOSE_FILE) up -d

.PHONY: down
down: ## Остановить и удалить контейнеры
	docker compose -f $(COMPOSE_FILE) down

.PHONY: restart
restart: down up ## Перезапустить контейнеры

.PHONY: logs
logs: ## Показать логи бота
	docker compose -f $(COMPOSE_FILE) logs -f bot

.PHONY: logs-mysql
logs-mysql: ## Показать логи MySQL (только для local)
	@if [ "$(APP_ENV)" = "local" ]; then \
		docker compose -f $(COMPOSE_FILE) logs -f mysql; \
	else \
		echo "MySQL logs доступны только в local окружении"; \
	fi

.PHONY: ps
ps: ## Показать статус контейнеров
	docker compose -f $(COMPOSE_FILE) ps

.PHONY: build
build: ## Пересобрать Docker образ
	docker compose -f $(COMPOSE_FILE) build --no-cache

# ==================== SHELL ====================

.PHONY: shell
shell: ## Войти в shell контейнера бота
	docker exec -it cardslite-bot sh

.PHONY: mysql-shell
mysql-shell: ## Войти в MySQL shell (только для local)
	@if [ "$(APP_ENV)" = "local" ]; then \
		docker exec -it cardslite-mysql mysql -ucardslite -pcardslite_password cardslite; \
	else \
		echo "Используйте: mysql -h localhost -u $(DB_USER) -p $(DB_NAME)"; \
	fi

# ==================== MIGRATIONS ====================

.PHONY: migrate
migrate: ## Выполнить миграции
	docker exec cardslite-bot php migrate migrate

.PHONY: migrate-rollback
migrate-rollback: ## Откатить последнюю миграцию
	docker exec cardslite-bot php migrate rollback

.PHONY: migrate-fresh
migrate-fresh: ## Удалить все таблицы и выполнить миграции заново
	@echo "⚠️  ВНИМАНИЕ: Это удалит ВСЕ данные!"
	@read -p "Вы уверены? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker exec cardslite-bot php migrate fresh; \
	else \
		echo "Отменено."; \
	fi

.PHONY: migrate-status
migrate-status: ## Показать статус миграций
	docker exec cardslite-bot php migrate status

# ==================== DEVELOPMENT ====================

.PHONY: install
install: ## Установить зависимости Composer
	docker run --rm -v $(PWD):/app composer:latest install

.PHONY: update
update: ## Обновить зависимости Composer
	docker run --rm -v $(PWD):/app composer:latest update

.PHONY: env
env: ## Создать .env из .env.example
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "✅ Файл .env создан из .env.example"; \
		echo "⚠️  Не забудьте заполнить BOT_TOKEN и ADMIN_ID!"; \
	else \
		echo "❌ Файл .env уже существует"; \
	fi

# ==================== CLEANUP ====================

.PHONY: clean
clean: ## Очистить все данные и контейнеры
	@echo "⚠️  ВНИМАНИЕ: Это удалит ВСЕ контейнеры и volumes!"
	@read -p "Вы уверены? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker compose -f $(COMPOSE_FILE) down -v; \
		echo "✅ Все очищено"; \
	else \
		echo "Отменено."; \
	fi

.PHONY: clean-logs
clean-logs: ## Очистить логи Docker
	docker compose -f $(COMPOSE_FILE) logs --no-log-prefix > /dev/null 2>&1 || true

# ==================== PRODUCTION ====================

.PHONY: deploy
deploy: ## Развернуть на проде (pull + restart)
	git pull origin master
	$(MAKE) restart
	@echo "✅ Развертывание завершено"

.PHONY: prod-up
prod-up: ## Запустить на проде (APP_ENV=production)
	APP_ENV=production $(MAKE) up

.PHONY: prod-logs
prod-logs: ## Показать логи на проде
	APP_ENV=production $(MAKE) logs

# ==================== USEFUL ====================

.PHONY: setup
setup: env install up migrate ## Полная установка проекта (local)
	@echo ""
	@echo "✅ Проект настроен!"
	@echo "📝 Не забудьте заполнить BOT_TOKEN и ADMIN_ID в .env"
	@echo "🔄 После заполнения выполните: make restart"

.PHONY: dev
dev: ## Быстрый старт для разработки
	@echo "🚀 Запуск окружения разработки..."
	$(MAKE) up
	$(MAKE) logs

.DEFAULT_GOAL := help
