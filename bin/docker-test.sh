#!/bin/bash

# Script simplifié pour exécuter des commandes de test dans Docker
# Usage: ./bin/docker-test.sh [command]

set -e

DOCKER_COMPOSE_FILE="docker-compose.test.yml"
PHP_CONTAINER="rag-php-client-tests"

# Colors
BLUE='\033[0;34m'
GREEN='\033[0;32m'
NC='\033[0m'

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Default command
COMMAND=${1:-"./vendor/bin/phpunit"}

# Check if services are running
if ! docker-compose -f $DOCKER_COMPOSE_FILE ps | grep -q "Up"; then
    print_info "Starting test environment..."
    docker-compose -f $DOCKER_COMPOSE_FILE up -d
    sleep 5
fi

print_info "Executing: $COMMAND"

# Execute the command
if docker-compose -f $DOCKER_COMPOSE_FILE exec -T $PHP_CONTAINER $COMMAND; then
    print_success "Command executed successfully!"
else
    echo "Command failed!"
    exit 1
fi