# Netfield RAG PHP Client

[![Latest Stable Version](https://poser.pugx.org/netfield/rag-client/v/stable)](https://packagist.org/packages/netfield/rag-client)
[![License](https://poser.pugx.org/netfield/rag-client/license)](https://packagist.org/packages/netfield/rag-client)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)](https://packagist.org/packages/netfield/rag-client)

Un client PHP moderne pour l'API Netfield RAG - Système de Questions-Réponses intelligent sur documents.

## 🚀 Installation

### Via Composer (Recommandé)

```bash
composer require netfield/rag-client
```

### Manuel (pour développement)

```bash
git clone https://github.com/jpgiannetti/netfield-rag.git
cd netfield-rag/clients/php
composer install
```

## 📖 Usage Rapide

### 1. Configuration Simple

```php
<?php
require 'vendor/autoload.php';

use Netfield\RagClient\RagClientFactory;

// Créer le client avec un token JWT
$client = RagClientFactory::create(
    'http://localhost:8888/api/v1', 
    'your-jwt-token'
);

// Ou créer avec un token de test
$client = RagClientFactory::createWithTestToken(
    'http://localhost:8888/api/v1',
    'test_client'
);
```

### 2. Indexer un Document

```php
use Netfield\RagClient\Models\Request\IndexDocumentRequest;
use Netfield\RagClient\Models\Request\DocumentInfo;

$request = new IndexDocumentRequest(
    document_id: 'doc_001',
    client_id: 'my_client',
    content: 'Contenu du document à indexer...',
    document_info: new DocumentInfo(
        title: 'Mon Document', 
        creation_date: '2025-01-15 10:30:00'
    ),
    metadata: [
        'type' => 'guide',
        'category' => 'documentation'
    ]
);

try {
    $response = $client->indexDocument($request);
    echo "Document indexé: {$response->document_id}\n";
} catch (Exception $e) {
    echo "Erreur: {$e->getMessage()}\n";
}
```

### 3. Effectuer une Recherche

```php
use Netfield\RagClient\Models\Request\AskRequest;

$question = new AskRequest(
    question: 'Comment configurer le système ?',
    limit: 5,
    filters: ['type' => 'guide']
);

try {
    $response = $client->ask($question);
    
    echo "Réponse: {$response->answer}\n";
    echo "Confiance: {$response->confidence_score}\n";
    echo "Sources: " . count($response->sources) . " documents\n";
} catch (Exception $e) {
    echo "Erreur: {$e->getMessage()}\n";
}
```

### 4. Configuration via Variables d'Environnement

```php
// .env
RAG_API_URL=http://localhost:8888/api/v1
RAG_JWT_TOKEN=your-jwt-token
# OU
RAG_TENANT_ID=test_client
RAG_JWT_SECRET=your-secret-key

// PHP
$client = RagClientFactory::createFromEnv();
```

## 🔧 Fonctionnalités Avancées

### Configuration Personnalisée

```php
use GuzzleHttp\Client;
use Monolog\Logger;

$httpClient = new Client([
    'timeout' => 30,
    'verify' => false,  // Pour environnements de test
]);

$logger = new Logger('rag-client');

$client = RagClientFactory::createCustom(
    baseUrl: 'http://localhost:8888/api/v1',
    jwtToken: 'your-token',
    httpOptions: ['timeout' => 30],
    logger: $logger
);
```

### Indexation en Lot

```php
use Netfield\RagClient\Models\Request\BulkIndexRequest;

$documents = [
    new IndexDocumentRequest('doc1', 'client1', 'Contenu 1...', /* ... */),
    new IndexDocumentRequest('doc2', 'client1', 'Contenu 2...', /* ... */),
];

$bulkRequest = new BulkIndexRequest($documents);
$response = $client->bulkIndex($bulkRequest);

echo "Indexés: {$response->successful_count}/{$response->total_count}\n";
```

### Vérification de Santé

```php
$health = $client->healthCheck();

if ($health->status === 'healthy') {
    echo "API disponible ✅\n";
    echo "Version: {$health->version}\n";
} else {
    echo "API indisponible ❌\n";
}
```

## 🧪 Tests

### Lancer les Tests

```bash
# Tests unitaires (rapides)
composer test

# Tests avec couverture
composer test -- --coverage-html coverage/

# Analyse statique
composer phpstan

# Vérification du style
composer cs-check
composer cs-fix
```

### Tests avec Docker

```bash
# Environnement de test complet
docker compose -f docker-compose.test.yml up -d

# Tests unitaires
docker compose -f docker-compose.test.yml exec php-test ./vendor/bin/phpunit --testsuite "Unit Tests"

# Tests d'intégration (nécessite l'API)
docker compose -f docker-compose.test.yml exec php-test ./vendor/bin/phpunit --testsuite "Integration Tests"
```

## 🔒 Authentification

### Générer un Token JWT

```php
use Netfield\RagClient\Auth\JwtAuthenticator;

$token = JwtAuthenticator::generateTestToken(
    tenantId: 'my_client',
    secretKey: 'your-secret-key',
    scopes: ['read', 'write'],
    confidentialityLevels: ['public', 'internal']
);
```

### Configuration Avancée

```php
$client = new RagClient(
    baseUrl: 'https://api.example.com/rag',
    jwtToken: $token,
    httpClient: new Client([
        'headers' => [
            'User-Agent' => 'MyApp/1.0',
            'Accept' => 'application/json'
        ],
        'timeout' => 60,
        'connect_timeout' => 10
    ])
);
```

## 🛠️ Développement

### Structure du Projet

```
src/
├── Auth/              # Authentification JWT
├── Client/            # Client principal
├── Exception/         # Exceptions personnalisées
├── Models/            # Modèles de données
│   ├── Request/       # Requêtes API
│   └── Response/      # Réponses API
└── RagClientFactory.php  # Factory principal
```

### Contribuer

1. Fork le projet
2. Créer une branche (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Committer (`git commit -am 'Ajoute nouvelle fonctionnalité'`)
4. Pousser (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

## 📋 Configuration Requise

- **PHP**: 8.0 ou supérieur
- **Extensions**: json, curl, mbstring
- **Dépendances**: 
  - guzzlehttp/guzzle ^7.0
  - firebase/php-jwt ^6.0
  - psr/log ^1.0|^2.0|^3.0

## 🐛 Débogage

### Activer les Logs

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('rag-client');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$client = RagClientFactory::createCustom(
    baseUrl: 'http://localhost:8888/api/v1',
    jwtToken: $token,
    logger: $logger
);
```

### Gestion des Erreurs

Le client gère automatiquement les codes d'erreur standardisés de l'API RAG (format `UPPER_SNAKE_CASE`).

#### Gestion Simple

```php
use Netfield\RagClient\Exception\RagApiException;
use Netfield\RagClient\Exception\ErrorCode;

try {
    $response = $orgClient->createClientToken($request);
    echo "Token créé: {$response->jwt_token}\n";
} catch (RagApiException $e) {
    // Accès au code d'erreur standardisé
    echo "Erreur: {$e->getErrorCode()}\n";  // Ex: ORG_CLIENT_ALREADY_EXISTS
    echo "Message: {$e->getMessage()}\n";

    // Helpers de classification
    if ($e->isRetryable()) {
        echo "⚠️ Erreur temporaire - retry possible\n";
    }
    if ($e->needsAuthRefresh()) {
        echo "🔄 Token expiré - refresh nécessaire\n";
    }
    if ($e->isCritical()) {
        echo "🚨 Erreur critique - alerter l'équipe ops\n";
    }
}
```

#### Gestion Avancée avec Codes Spécifiques

```php
try {
    $response = $orgClient->createClientToken($request);
} catch (RagApiException $e) {
    // Traitement conditionnel selon le code d'erreur
    switch ($e->getErrorCode()) {
        case ErrorCode::ORG_CLIENT_ALREADY_EXISTS:
            return ['status' => 'exists', 'message' => 'Ce client existe déjà'];

        case ErrorCode::AUTH_TOKEN_EXPIRED:
            $newToken = refreshToken();
            return retry($request);

        case ErrorCode::INDEX_WEAVIATE_CONNECTION_ERROR:
            if ($e->isRetryable()) {
                sleep(2);
                return retry($request);
            }
            break;

        default:
            logError($e);
            throw $e;
    }
}
```

#### Sérialisation JSON pour le Front-End

```php
try {
    $response = $client->indexDocument($document);
} catch (RagApiException $e) {
    // Convertir en JSON structuré pour le front-end
    $errorData = $e->toArray();

    return response()->json($errorData, $e->getCode());

    /* Retourne:
    {
        "error_code": "INDEX_DUPLICATE_DOCUMENT_ID",
        "message": "Failed to index document: ID de document déjà existant",
        "details": {"document_id": "doc_123", "tenant_id": "client_abc"},
        "field": null,
        "timestamp": "2025-10-11T14:32:10.123Z",
        "trace_id": "abc-123-def-456",
        "http_status": 409,
        "is_retryable": false,
        "is_critical": false,
        "needs_auth_refresh": false
    }
    */
}
```

#### Informations Détaillées de l'Erreur

```php
try {
    $response = $client->ask($question);
} catch (RagApiException $e) {
    // Accès aux détails complets de l'erreur
    $errorCode = $e->getErrorCode();          // ORG_CLIENT_ALREADY_EXISTS
    $details = $e->getDetails();               // ['client_name' => 'test', ...]
    $field = $e->getField();                   // Champ concerné (validation)
    $timestamp = $e->getTimestamp();           // 2025-10-11T14:32:10.123Z
    $traceId = $e->getTraceId();               // Pour debugging distribué

    // Logging structuré
    $logger->error('API error', [
        'error_code' => $errorCode,
        'trace_id' => $traceId,
        'details' => $details,
    ]);
}
```

## 📚 Documentation

- [Documentation complète](https://github.com/jpgiannetti/netfield-rag)
- [Guide d'API](https://github.com/jpgiannetti/netfield-rag/blob/main/docs/api/reference.md)
- [Exemples complets](https://github.com/jpgiannetti/netfield-rag/tree/main/clients/php/examples)

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🤝 Support

- **Issues**: [GitHub Issues](https://github.com/jpgiannetti/netfield-rag/issues)
- **Discussions**: [GitHub Discussions](https://github.com/jpgiannetti/netfield-rag/discussions)
- **Email**: jpgiannetti@users.noreply.github.com

---

Développé avec ❤️ par [Jean-Philippe Giannetti](https://github.com/jpgiannetti)