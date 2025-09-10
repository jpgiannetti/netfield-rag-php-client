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

```php
use Netfield\RagClient\Exception\RagApiException;
use Netfield\RagClient\Exception\AuthenticationException;

try {
    $response = $client->ask($question);
} catch (AuthenticationException $e) {
    echo "Erreur d'authentification: {$e->getMessage()}\n";
} catch (RagApiException $e) {
    echo "Erreur API: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
} catch (Exception $e) {
    echo "Erreur générale: {$e->getMessage()}\n";
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