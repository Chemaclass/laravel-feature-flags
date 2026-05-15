.DEFAULT_GOAL := help
.PHONY: help up down restart logs shell tinker test test-unit test-feature stan seed reset rebuild ps

DC ?= docker compose
APP := app

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | \
		awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Boot the demo app and wait until it answers on :8000
	$(DC) up -d --wait
	@echo ""
	@echo "  Demo:  http://localhost:8000/admin/feature-flags"
	@echo "  Login: demo@example.com (auto)"
	@echo ""

down: ## Stop and remove containers (keeps the DB volume)
	$(DC) down

restart: ## Restart the running container
	$(DC) restart

logs: ## Tail the demo container logs
	$(DC) logs -f $(APP)

shell: ## Open a shell inside the demo container
	$(DC) exec $(APP) sh

tinker: ## Open Tinker REPL against the demo app
	$(DC) exec $(APP) vendor/bin/testbench tinker

test: ## Run the full pest test suite (Unit + Feature)
	$(DC) run --rm test

test-unit: ## Run only the Unit suite
	$(DC) run --rm test sh -c "vendor/bin/pest --testsuite=Unit"

test-feature: ## Run only the Feature suite
	$(DC) run --rm test sh -c "vendor/bin/pest --testsuite=Feature"

stan: ## Run phpstan
	$(DC) run --rm stan

seed: ## Re-seed the demo DB with the example flags
	$(DC) exec $(APP) vendor/bin/testbench db:seed --class='Workbench\Database\Seeders\DatabaseSeeder'

reset: ## Wipe DB volume and rebuild from scratch
	$(DC) down -v
	$(DC) up -d --wait
	@echo ""
	@echo "  Fresh demo:  http://localhost:8000/admin/feature-flags"
	@echo ""

rebuild: ## Rebuild the image (use after editing Dockerfile or composer.json)
	$(DC) build --no-cache
	$(DC) up -d --wait

ps: ## Show container status
	$(DC) ps
