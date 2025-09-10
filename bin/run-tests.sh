#!/bin/bash

# Script pour exécuter les tests dans l'environnement Docker
# Usage: ./bin/run-tests.sh [unit|integration|all] [options]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
TEST_TYPE="all"
DOCKER_COMPOSE_FILE="docker-compose.test.yml"
PHP_CONTAINER="rag-php-client-tests"
COVERAGE=false
VERBOSE=false

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    cat << EOF
Usage: $0 [TEST_TYPE] [OPTIONS]

TEST_TYPES:
    unit            Run only unit tests
    integration     Run only integration tests
    all            Run all tests (default)

OPTIONS:
    --coverage     Generate code coverage report
    --verbose      Verbose output
    --help         Show this help message

EXAMPLES:
    $0                          # Run all tests
    $0 unit                     # Run only unit tests
    $0 integration --coverage   # Run integration tests with coverage
    $0 all --verbose           # Run all tests with verbose output

ENVIRONMENT:
    The tests run in Docker containers. Make sure Docker and Docker Compose are installed.
EOF
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        unit|integration|all)
            TEST_TYPE="$1"
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --help|-h)
            show_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

print_status "Starting RAG PHP Client Tests - Type: $TEST_TYPE"

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker first."
    exit 1
fi

# Check if docker-compose file exists
if [ ! -f "$DOCKER_COMPOSE_FILE" ]; then
    print_error "Docker Compose file '$DOCKER_COMPOSE_FILE' not found."
    exit 1
fi

print_status "Building and starting test environment..."

# Build and start the test environment
docker-compose -f $DOCKER_COMPOSE_FILE down --remove-orphans
docker-compose -f $DOCKER_COMPOSE_FILE build --no-cache
docker-compose -f $DOCKER_COMPOSE_FILE up -d

# Wait for services to be ready
print_status "Waiting for services to be ready..."
sleep 10

# Check if RAG API mock is responding
print_status "Checking RAG API mock service..."
for i in {1..30}; do
    if docker-compose -f $DOCKER_COMPOSE_FILE exec -T rag-api curl -f http://localhost:8080/__admin/health >/dev/null 2>&1; then
        print_success "RAG API mock is ready"
        break
    fi
    if [ $i -eq 30 ]; then
        print_error "RAG API mock failed to start"
        docker-compose -f $DOCKER_COMPOSE_FILE logs rag-api
        exit 1
    fi
    sleep 2
done

# Prepare test command
PHPUNIT_CMD="./vendor/bin/phpunit"

# Add coverage option
if [ "$COVERAGE" = true ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --coverage-html tests/coverage/html --coverage-text"
    print_status "Code coverage will be generated"
fi

# Add verbose option
if [ "$VERBOSE" = true ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --verbose"
fi

# Add test suite filter
case $TEST_TYPE in
    unit)
        PHPUNIT_CMD="$PHPUNIT_CMD --testsuite 'Unit Tests'"
        ;;
    integration)
        PHPUNIT_CMD="$PHPUNIT_CMD --testsuite 'Integration Tests'"
        ;;
    all)
        # Run all tests (default behavior)
        ;;
esac

print_status "Running tests: $PHPUNIT_CMD"

# Run the tests
if docker-compose -f $DOCKER_COMPOSE_FILE exec -T $PHP_CONTAINER $PHPUNIT_CMD; then
    print_success "All tests passed! ✅"
    TEST_SUCCESS=true
else
    print_error "Some tests failed! ❌"
    TEST_SUCCESS=false
fi

# Show coverage report location if generated
if [ "$COVERAGE" = true ] && [ "$TEST_SUCCESS" = true ]; then
    print_status "Coverage report generated:"
    print_status "  HTML: tests/coverage/html/index.html"
    print_status "  Text: tests/coverage/coverage.txt"
fi

# Show test reports
if docker-compose -f $DOCKER_COMPOSE_FILE exec -T $PHP_CONTAINER test -f tests/reports/junit.xml; then
    print_status "JUnit report: tests/reports/junit.xml"
fi

# Optional: Show logs if tests failed
if [ "$TEST_SUCCESS" = false ]; then
    print_warning "Showing container logs for debugging:"
    echo "=== PHP Test Container Logs ==="
    docker-compose -f $DOCKER_COMPOSE_FILE logs --tail=50 $PHP_CONTAINER
    echo "=== RAG API Mock Logs ==="
    docker-compose -f $DOCKER_COMPOSE_FILE logs --tail=50 rag-api
fi

# Clean up
print_status "Cleaning up test environment..."
docker-compose -f $DOCKER_COMPOSE_FILE down

# Exit with appropriate code
if [ "$TEST_SUCCESS" = true ]; then
    print_success "Test execution completed successfully!"
    exit 0
else
    print_error "Test execution failed!"
    exit 1
fi