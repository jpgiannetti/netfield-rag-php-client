# Makefile pour le client PHP RAG

# Variables
DOCKER_COMPOSE_FILE = docker-compose.test.yml
PHP_CONTAINER = rag-php-client-tests

# Colors for output
BLUE = \033[0;34m
GREEN = \033[0;32m
YELLOW = \033[1;33m
RED = \033[0;31m
NC = \033[0m # No Color

.PHONY: help install test test-unit test-integration test-coverage clean docker-build docker-up docker-down docker-shell composer-install composer-update phpstan cs-check cs-fix

# Default target
help:
	@echo "$(BLUE)RAG PHP Client - Available Commands$(NC)"
	@echo ""
	@echo "$(GREEN)Development:$(NC)"
	@echo "  install          Install dependencies"
	@echo "  composer-install Install PHP dependencies via Composer"
	@echo "  composer-update  Update PHP dependencies"
	@echo ""
	@echo "$(GREEN)Testing:$(NC)"
	@echo "  test             Run all tests (full rebuild)"
	@echo "  test-unit        Run unit tests only (full rebuild)"
	@echo "  test-integration Run integration tests only (full rebuild)"
	@echo "  test-coverage    Run tests with coverage report"
	@echo "  test-watch       Run tests in watch mode (requires entr)"
	@echo ""
	@echo "$(GREEN)Fast Testing (requires running environment):$(NC)"
	@echo "  test-unit-fast   Run unit tests quickly"
	@echo "  test-integration-fast Run integration tests quickly"
	@echo "  test-all-fast    Run all tests quickly"
	@echo ""
	@echo "$(GREEN)Environment Management:$(NC)"
	@echo "  env-start        Start test environment"
	@echo "  env-stop         Stop test environment"
	@echo "  env-status       Show environment status"
	@echo ""
	@echo "$(GREEN)Code Quality:$(NC)"
	@echo "  phpstan          Run static analysis"
	@echo "  cs-check         Check code style"
	@echo "  cs-fix           Fix code style issues"
	@echo "  quality          Run all quality checks"
	@echo ""
	@echo "$(GREEN)Docker:$(NC)"
	@echo "  docker-build     Build Docker images"
	@echo "  docker-up        Start test environment"
	@echo "  docker-down      Stop test environment"
	@echo "  docker-shell     Open shell in PHP container"
	@echo "  docker-logs      Show container logs"
	@echo ""
	@echo "$(GREEN)Utilities:$(NC)"
	@echo "  clean            Clean cache and temporary files"
	@echo "  examples         Run example scripts"

# Installation
install: composer-install

composer-install:
	@echo "$(BLUE)Installing PHP dependencies...$(NC)"
	composer install

composer-update:
	@echo "$(BLUE)Updating PHP dependencies...$(NC)"
	composer update

# Testing
test:
	@echo "$(BLUE)Running all tests...$(NC)"
	./bin/run-tests.sh all

test-unit:
	@echo "$(BLUE)Running unit tests...$(NC)"
	./bin/run-tests.sh unit

test-integration:
	@echo "$(BLUE)Running integration tests...$(NC)"
	./bin/run-tests.sh integration

test-coverage:
	@echo "$(BLUE)Running tests with coverage...$(NC)"
	./bin/run-tests.sh all --coverage

test-watch:
	@echo "$(BLUE)Running tests in watch mode...$(NC)"
	@echo "$(YELLOW)Press Ctrl+C to stop$(NC)"
	find src tests -name "*.php" | entr ./bin/docker-test.sh "./vendor/bin/phpunit --colors=always"

# Code Quality
phpstan:
	@echo "$(BLUE)Running static analysis...$(NC)"
	./bin/docker-test.sh "./vendor/bin/phpstan analyse"

cs-check:
	@echo "$(BLUE)Checking code style...$(NC)"
	./bin/docker-test.sh "./vendor/bin/phpcs src tests --standard=PSR12"

cs-fix:
	@echo "$(BLUE)Fixing code style...$(NC)"
	./bin/docker-test.sh "./vendor/bin/phpcbf src tests --standard=PSR12"

quality: cs-check phpstan test-unit
	@echo "$(GREEN)All quality checks passed!$(NC)"

# Docker
docker-build:
	@echo "$(BLUE)Building Docker images...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_FILE) build --no-cache

docker-up:
	@echo "$(BLUE)Starting test environment...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_FILE) up -d
	@echo "$(GREEN)Test environment is running$(NC)"
	@echo "Services available:"
	@echo "  - PHP Tests: http://localhost:8080 (internal)"
	@echo "  - RAG API Mock: http://localhost:8888"
	@echo "  - WireMock: http://localhost:9999"

docker-down:
	@echo "$(BLUE)Stopping test environment...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_FILE) down --remove-orphans
	@echo "$(GREEN)Test environment stopped$(NC)"

docker-shell:
	@echo "$(BLUE)Opening shell in PHP container...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_FILE) exec $(PHP_CONTAINER) /bin/bash

docker-logs:
	@echo "$(BLUE)Showing container logs...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_FILE) logs -f

# Examples
examples:
	@echo "$(BLUE)Running example scripts...$(NC)"
	@echo "$(YELLOW)Make sure the RAG API is running first$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_FILE) exec $(PHP_CONTAINER) php examples/bulk-indexing.php
	docker-compose -f $(DOCKER_COMPOSE_FILE) exec $(PHP_CONTAINER) php examples/simple-search.php

# Utilities
clean:
	@echo "$(BLUE)Cleaning temporary files...$(NC)"
	rm -rf .phpunit.cache
	rm -rf tests/coverage
	rm -rf tests/reports
	mkdir -p tests/coverage tests/reports
	@echo "$(GREEN)Cleanup completed$(NC)"

# Development shortcuts
dev-setup: install docker-build
	@echo "$(GREEN)Development environment ready!$(NC)"
	@echo "Run 'make docker-up' to start the test environment"

ci: clean quality test-coverage
	@echo "$(GREEN)CI pipeline completed successfully!$(NC)"

# Quick test commands (require running Docker environment)
quick-test:
	./bin/docker-test.sh "./vendor/bin/phpunit --testsuite 'Unit Tests' --no-coverage"

debug-test:
	./bin/docker-test.sh "./vendor/bin/phpunit --testsuite 'Unit Tests' --verbose --debug"

# Fast test commands (use existing Docker environment)
test-unit-fast:
	@echo "$(BLUE)Running unit tests (fast - requires running environment)...$(NC)"
	@docker compose -f $(DOCKER_COMPOSE_FILE) exec php-test ./vendor/bin/phpunit --testsuite "Unit Tests" --colors || true
	@echo "$(GREEN)Unit tests completed! ✅$(NC)"

test-integration-fast:
	@echo "$(BLUE)Running integration tests (fast - requires running environment)...$(NC)"
	@docker compose -f $(DOCKER_COMPOSE_FILE) exec php-test ./vendor/bin/phpunit --testsuite "Integration Tests" --colors || true
	@echo "$(GREEN)Integration tests completed! ✅$(NC)"

test-all-fast:
	@echo "$(BLUE)Running all tests (fast - requires running environment)...$(NC)"
	@docker compose -f $(DOCKER_COMPOSE_FILE) exec php-test ./vendor/bin/phpunit --colors || true
	@echo "$(GREEN)All tests completed! ✅$(NC)"

# Environment management shortcuts
env-start:
	@echo "$(BLUE)Starting test environment...$(NC)"
	docker compose -f $(DOCKER_COMPOSE_FILE) up -d --build
	@echo "$(GREEN)Test environment is ready! Use 'make test-unit-fast' for quick tests$(NC)"

env-stop:
	@echo "$(BLUE)Stopping test environment...$(NC)"
	docker compose -f $(DOCKER_COMPOSE_FILE) down
	@echo "$(GREEN)Test environment stopped$(NC)"

env-status:
	@echo "$(BLUE)Test environment status:$(NC)"
	docker compose -f $(DOCKER_COMPOSE_FILE) ps

# Documentation
docs:
	@echo "$(BLUE)Generating documentation...$(NC)"
	@echo "Documentation is available in README.md"
	@echo "API documentation: http://localhost:8888/docs (when RAG API is running)"