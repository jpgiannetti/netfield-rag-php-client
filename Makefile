# Makefile pour le client PHP RAG
# Usage: make [target]

.PHONY: help install test lint format clean docker-test

# Variables
COMPOSER := composer
DOCKER_COMPOSE := docker compose -f docker-compose.test.yml

# Couleurs
RED := \033[31m
GREEN := \033[32m
YELLOW := \033[33m
BLUE := \033[34m
RESET := \033[0m

help: ## Afficher cette aide
	@echo "$(BLUE)Client PHP RAG - Commandes disponibles:$(RESET)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-20s$(RESET) %s\n", $$1, $$2}'

##@ Installation

install: ## Installer les dépendances PHP
	@echo "$(BLUE)Installation des dépendances PHP...$(RESET)"
	$(COMPOSER) install
	@echo "$(GREEN)✓ Dépendances installées$(RESET)"

install-dev: ## Installer dépendances de développement
	@echo "$(BLUE)Installation des dépendances de développement...$(RESET)"
	$(COMPOSER) install --dev
	@echo "$(GREEN)✓ Environnement de développement configuré$(RESET)"

##@ Tests (Docker)

docker-test-up: ## Démarrer l'environnement de test Docker
	@echo "$(BLUE)Démarrage de l'environnement de test...$(RESET)"
	$(DOCKER_COMPOSE) up -d --build
	@echo "$(GREEN)✓ Environnement de test démarré$(RESET)"

docker-test-down: ## Arrêter l'environnement de test Docker
	@echo "$(BLUE)Arrêt de l'environnement de test...$(RESET)"
	$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✓ Environnement de test arrêté$(RESET)"

docker-test-status: ## Vérifier le statut de l'environnement de test
	@echo "$(BLUE)Statut de l'environnement de test:$(RESET)"
	$(DOCKER_COMPOSE) ps

test: ## Exécuter tous les tests dans Docker
	@echo "$(BLUE)Exécution de tous les tests...$(RESET)"
	$(DOCKER_COMPOSE) exec php-test ./vendor/bin/phpunit --colors

test-unit: ## Exécuter les tests unitaires dans Docker
	@echo "$(BLUE)Exécution des tests unitaires...$(RESET)"
	$(DOCKER_COMPOSE) exec php-test ./vendor/bin/phpunit --testsuite "Unit Tests" --colors

test-integration: ## Exécuter les tests d'intégration dans Docker
	@echo "$(BLUE)Exécution des tests d'intégration...$(RESET)"
	$(DOCKER_COMPOSE) exec php-test ./vendor/bin/phpunit --testsuite "Integration Tests" --colors

test-coverage: ## Exécuter les tests avec couverture
	@echo "$(BLUE)Exécution des tests avec couverture...$(RESET)"
	$(DOCKER_COMPOSE) exec php-test ./vendor/bin/phpunit --coverage-html tests/coverage --colors

test-logs: ## Afficher les logs de l'environnement de test
	@echo "$(BLUE)Logs de l'environnement de test:$(RESET)"
	$(DOCKER_COMPOSE) logs -f

##@ Qualité du code

lint: ## Vérifier la qualité du code (PHPStan)
	@echo "$(BLUE)Vérification de la qualité du code...$(RESET)"
	$(COMPOSER) phpstan
	@echo "$(GREEN)✓ Code quality OK$(RESET)"

format: ## Formater le code automatiquement
	@echo "$(BLUE)Formatage du code...$(RESET)"
	$(COMPOSER) cs-fix
	@echo "$(GREEN)✓ Code formaté$(RESET)"

format-check: ## Vérifier le formatage du code
	@echo "$(BLUE)Vérification du formatage...$(RESET)"
	$(COMPOSER) cs-check

##@ Nettoyage

clean: ## Nettoyer les fichiers temporaires
	@echo "$(BLUE)Nettoyage...$(RESET)"
	rm -rf vendor/ composer.lock .phpunit.cache/ tests/coverage/
	@echo "$(GREEN)✓ Nettoyage terminé$(RESET)"

clean-docker: ## Nettoyer l'environnement de test Docker
	@echo "$(BLUE)Nettoyage Docker...$(RESET)"
	$(DOCKER_COMPOSE) down --volumes --remove-orphans
	@echo "$(GREEN)✓ Environnement Docker nettoyé$(RESET)"

.DEFAULT_GOAL := help
