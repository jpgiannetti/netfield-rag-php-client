# AGENTS.md - PHP Client

Instructions spécifiques pour les modifications dans le sous-projet **PHP Client** (Composer package).

## Project Scope

**Netfield RAG PHP Client** : Client PHP Composer pour l'API Netfield RAG, disponible sur Packagist.

**Stack** : PHP 8.0+, Guzzle HTTP, Firebase JWT, PHPUnit, PHPStan, PHP-CS-Fixer

**Package** : `composer require netfield/rag-client`

## Code Style - PHP

### ✅ Dos
- **PSR-12** : Standards Composer strictement respectés
- **PHP 8.0+ features** : Typed properties, named arguments, match expressions, attributes
- **Guzzle HTTP** : Client HTTP async avec retry logic et timeout
- **JWT validation** : Utiliser `firebase/php-jwt` pour génération et validation
- **Monolog** : Logs structurés avec contexte (`tenant_id`, `trace_id`)
- **Type safety** : Typed properties partout, pas de mixed ou null non documenté
- **Dependency Injection** : Via constructeur, pas de global state

### ❌ Don'ts
- **Pas de variables non typées** : Typed properties obligatoires (PHP 8.0+)
- **Éviter global state** : Dependency injection via constructeur
- **Pas de `@` error suppression** : Gestion d'erreurs explicite avec try/catch
- **Pas de hard-coded URLs** : Configuration via constructeur ou env vars
- **Éviter coupling fort** : Interfaces pour dépendances (Guzzle, Logger)

## Testing - Docker-Based

### Unit Tests (Rapides - Toujours OK)
```bash
# Démarrer environnement de test
docker compose -f docker-compose.test.yml up -d --build

# Tests unitaires (96 tests, ~1-2s)
docker compose -f docker-compose.test.yml exec php-test ./vendor/bin/phpunit --testsuite "Unit Tests" --colors

# Analyse statique PHPStan
docker compose -f docker-compose.test.yml exec php-test composer phpstan

# Code style check
docker compose -f docker-compose.test.yml exec php-test composer cs-check

# Auto-fix code style
docker compose -f docker-compose.test.yml exec php-test composer cs-fix
```

### Integration Tests (Nécessite WireMock)
```bash
# Tests d'intégration (2/12 passent actuellement - WireMock incomplet)
docker compose -f docker-compose.test.yml exec php-test ./vendor/bin/phpunit --testsuite "Integration Tests" --colors
```

### Cleanup
```bash
# Arrêter et nettoyer
docker compose -f docker-compose.test.yml down --volumes --remove-orphans
```

## Pre-Commit Checks

```bash
# Workflow complet avant commit
docker compose -f docker-compose.test.yml exec php-test bash -c "composer cs-fix && composer phpstan && ./vendor/bin/phpunit --testsuite 'Unit Tests'"
```

## Architecture Patterns

### Factory Pattern
```php
// ✅ Bon : Factory pour création client
use Netfield\RagClient\NetfieldClientFactory;

$client = NetfieldClientFactory::create(
    baseUrl: 'http://localhost:8888/api/v1',
    jwtToken: $token
);

// OU avec configuration env
$client = NetfieldClientFactory::createFromEnv();

// ❌ Mauvais : Instanciation directe complexe
$httpClient = new \GuzzleHttp\Client(['timeout' => 30]);
$client = new NetfieldClient($baseUrl, $token, $httpClient);
```

### Typed Properties (PHP 8.0+)
```php
// ✅ Bon : Typed properties
class IndexDocumentRequest
{
    public function __construct(
        public readonly string $document_id,
        public readonly string $client_id,
        public readonly string $content,
        public readonly ?DocumentInfo $document_info = null,
        public readonly ?array $metadata = null
    ) {}
}

// ❌ Mauvais : Properties non typées
class IndexDocumentRequest
{
    public $document_id;
    public $client_id;
    // ...
}
```

### Exception Handling
```php
// ✅ Bon : Exceptions spécifiques avec codes d'erreur
use Netfield\RagClient\Exception\NetfieldApiException;
use Netfield\RagClient\Exception\AuthenticationException;

try {
    $response = $client->ask($question);
} catch (AuthenticationException $e) {
    // Token invalide ou expiré
    logger->error('Auth failed', ['error' => $e->getMessage()]);
} catch (NetfieldApiException $e) {
    // Erreur API avec code standardisé
    logger->error('API error', [
        'code' => $e->getCode(),
        'message' => $e->getMessage()
    ]);
}

// ❌ Mauvais : Catch générique
try {
    $response = $client->ask($question);
} catch (\Exception $e) {
    echo $e->getMessage();
}
```

## Key Files & Locations

### Core Client
- `src/Client/NetfieldClient.php` : Client principal avec méthodes API
- `src/NetfieldClientFactory.php` : Factory pour création client

### Authentication
- `src/Auth/JwtAuthenticator.php` : Génération et validation JWT

### Models
- `src/Models/Request/` : Modèles de requêtes (IndexDocumentRequest, AskRequest, etc.)
- `src/Models/Response/` : Modèles de réponses (IndexResponse, AskResponse, etc.)

### Exceptions
- `src/Exception/NetfieldApiException.php` : Exception API générique
- `src/Exception/AuthenticationException.php` : Erreurs d'authentification
- `src/Exception/ErrorCode.php` : Enum codes d'erreur standardisés

### Tests
- `tests/Unit/` : Tests unitaires (96 tests - tous passent)
- `tests/Integration/` : Tests d'intégration (2/12 passent - WireMock incomplet)

## Common Mistakes to Avoid

### ❌ Erreur 1 : Properties non typées
```php
// MAUVAIS
class AskRequest
{
    public $question;
    public $limit;
}

// BON
class AskRequest
{
    public function __construct(
        public readonly string $question,
        public readonly ?int $limit = null,
        public readonly ?array $filters = null
    ) {}
}
```

### ❌ Erreur 2 : Hard-coded configuration
```php
// MAUVAIS
$client = new NetfieldClient('http://localhost:8888/api/v1', 'token123');

// BON
$client = NetfieldClientFactory::createFromEnv();
// OU
$client = NetfieldClientFactory::create(
    baseUrl: getenv('NETFIELD_API_URL'),
    jwtToken: getenv('NETFIELD_JWT_TOKEN')
);
```

### ❌ Erreur 3 : Pas de retry logic
```php
// MAUVAIS : Pas de gestion d'erreurs temporaires
$response = $httpClient->post('/index', ['json' => $data]);

// BON : Retry logic avec Guzzle
$httpClient = new \GuzzleHttp\Client([
    'timeout' => 30,
    'retry_on_status' => [500, 502, 503, 504],
    'max_retry_attempts' => 3
]);
```

## Error Codes (Standardized)

### Format
- **Préfixe** : Catégorie d'erreur (`INDEX_*`, `RAG_*`, `AUTH_*`)
- **Suffix** : Description spécifique
- **Exemple** : `INDEX_DOCUMENT_NOT_FOUND`, `RAG_LLM_UNAVAILABLE`

### Usage
```php
use Netfield\RagClient\Exception\ErrorCode;

if ($error->getCode() === ErrorCode::INDEX_DOCUMENT_NOT_FOUND) {
    // Document n'existe pas, OK de réessayer indexation
} elseif ($error->getCode() === ErrorCode::RAG_LLM_UNAVAILABLE) {
    // LLM temporairement indisponible, retry recommandé
}
```

## JWT Token Generation

```php
use Netfield\RagClient\Auth\JwtAuthenticator;

// Générer un token de test
$token = JwtAuthenticator::generateTestToken(
    tenantId: 'my_client',
    secretKey: 'your-secret-key',
    scopes: ['read', 'write'],
    confidentialityLevels: ['public', 'internal'],
    expiresInHours: 24
);

// Utiliser dans le client
$client = NetfieldClientFactory::create(
    baseUrl: 'http://localhost:8888/api/v1',
    jwtToken: $token
);
```

## Environment Variables

```bash
# .env
NETFIELD_API_URL=http://localhost:8888/api/v1
NETFIELD_JWT_TOKEN=your-jwt-token

# OU pour génération automatique
NETFIELD_TENANT_ID=test_client
NETFIELD_JWT_SECRET=your-secret-key
```

## Commit Message Format

```
<type>(php-client): <description>

Types:
- feat: Nouvelle fonctionnalité client
- fix: Correction de bug
- test: Ajout/modification tests
- refactor: Refactoring code
- docs: Documentation
- perf: Optimisation performance
```

**Exemples** :
```
feat(php-client): add streaming support for ask endpoint
fix(php-client): correct JWT token expiration handling
test(php-client): add bulk index validation tests
refactor(php-client): improve error handling with specific exceptions
```

## Docker Test Environment

### Services
- **php-test** : PHP 8.2 container avec PHPUnit et dépendances
- **rag-api** : WireMock mock server (port 8080 → 8889 externe)
- **wiremock** : WireMock instance additionnelle (port 9999)
- **test-db** : MySQL 8.0 test database (port 3307)

### Known Issues
- **WireMock mappings incomplets** : Certains endpoints non mockés (stream, update, delete, stats)
- **Template interpolation** : Problèmes avec variables WireMock
- **Type casting** : `BulkIndexResponse` attend int mais reçoit string

## References

- **README complet** : `/clients/php/README.md`
- **Root AGENTS.md** : `/AGENTS.md`
- **Error codes** : `/clients/php/src/Exception/ErrorCode.php`
- **Examples** : `/clients/php/examples/`
