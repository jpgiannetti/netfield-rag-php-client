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

use Netfield\Client\NetfieldClientFactory;

// Créer le client avec un token JWT
$client = NetfieldClientFactory::create(
    'http://localhost:8888/api/v1', 
    'your-jwt-token'
);

// Ou créer avec un token de test
$client = NetfieldClientFactory::createWithTestToken(
    'http://localhost:8888/api/v1',
    'test_client'
);
```

### 2. Classifier un Document (DIS - Document Intelligence Service)

```php
use Netfield\Client\NetfieldClientFactory;

// Créer le client DIS pour la classification
$disClient = NetfieldClientFactory::createDisClient(
    'http://localhost:8888',
    'your-jwt-token'
);

// Classifier un document pour obtenir le type et la catégorie
$classification = $disClient->classifyDocument(
    content: 'Facture n° 2025-001\nMontant: 1000€...',
    title: 'Facture January 2025',
    metadata: ['source' => 'scan']
);

echo "Type: {$classification['doc_type']}\n";        // Ex: 'invoice'
echo "Catégorie: {$classification['category']}\n";   // Ex: 'comptabilite'
echo "Confiance: {$classification['confidence']}\n"; // Ex: 0.95
```

### 3. Indexer un Document

```php
use Netfield\Client\Models\Request\IndexDocumentRequest;
use Netfield\Client\Models\Request\DocumentInfo;

// Étape 1: Classifier le document via DIS
$disClient = NetfieldClientFactory::createDisClient(
    'http://localhost:8888',
    'your-jwt-token'
);

$classification = $disClient->classifyDocument(
    content: 'Contenu du document à indexer...',
    title: 'Mon Document'
);

// Étape 2: Indexer avec les métadonnées enrichies
$ragClient = NetfieldClientFactory::create(
    'http://localhost:8888',
    'your-jwt-token'
);

$request = new IndexDocumentRequest(
    document_id: 'doc_001',
    content: 'Contenu du document à indexer...',
    metadata: array_merge(
        [
            'doc_type' => $classification['doc_type'],
            'category' => $classification['category'],
            'classification_confidence' => $classification['confidence']
        ],
        $classification['enriched_metadata'] ?? []
    ),
    document_info: new DocumentInfo(
        title: 'Mon Document',
        creation_date: '2025-01-15 10:30:00'
    )
);

try {
    $response = $ragClient->indexDocument($request);
    echo "Document indexé: {$response->document_id}\n";
} catch (Exception $e) {
    echo "Erreur: {$e->getMessage()}\n";
}
```

### 4. Effectuer une Recherche

```php
use Netfield\Client\Models\Request\AskRequest;

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

### 5. Configuration via Variables d'Environnement

```php
// .env
NETFIELD_API_URL=http://localhost:8888/api/v1
NETFIELD_JWT_TOKEN=your-jwt-token
# OU
NETFIELD_TENANT_ID=test_client
NETFIELD_JWT_SECRET=your-secret-key

// PHP
$client = NetfieldClientFactory::createFromEnv();
```

## 🔧 Fonctionnalités Avancées

### Client Monitoring - Métriques et Monitoring

Le `MonitoringClient` permet de surveiller l'état de santé du système et d'accéder aux métriques.

```php
use Netfield\Client\NetfieldClientFactory;

// Créer le client Monitoring
$monitoringClient = NetfieldClientFactory::createMonitoringClient(
    'http://localhost:8888',
    'your-jwt-token'
);

// Health check détaillé
$healthData = $monitoringClient->getDetailedHealthCheck();
echo "Status: {$healthData['status']}\n";
echo "Services: " . json_encode($healthData['services']) . "\n";

// Métriques Prometheus
$prometheusMetrics = $monitoringClient->getPrometheusMetrics();
echo $prometheusMetrics; // Format texte Prometheus

// Métriques de confiance
$confidenceMetrics = $monitoringClient->getConfidenceMetrics();
echo "Average confidence: {$confidenceMetrics['average_confidence']}\n";

// Informations de trace
$traceInfo = $monitoringClient->getTraceInfo('trace-id-123');
echo "Trace duration: {$traceInfo['duration_ms']}ms\n";
```

### Client Validation - Validation de Documents

Le `ValidationClient` permet de valider des documents avant indexation et d'analyser les erreurs.

```php
use Netfield\Client\NetfieldClientFactory;
use Netfield\Client\Models\Request\BulkIndexRequest;
use Netfield\Client\Models\Request\IndexDocumentRequest;

// Créer le client Validation
$validationClient = NetfieldClientFactory::createValidationClient(
    'http://localhost:8888',
    'your-jwt-token'
);

// Valider des documents (dry-run)
$documents = [
    new IndexDocumentRequest(/* ... */),
    new IndexDocumentRequest(/* ... */)
];
$bulkRequest = new BulkIndexRequest($documents);
$validationResult = $validationClient->validateDocuments($bulkRequest);

echo "Valid documents: {$validationResult['valid_count']}\n";
echo "Invalid documents: {$validationResult['invalid_count']}\n";

// Récupérer le rapport de validation d'un document
$report = $validationClient->getDocumentValidationReport('doc_123');
foreach ($report['errors'] as $error) {
    echo "Error: {$error['message']} (field: {$error['field']})\n";
}

// Résumé des validations sur 30 jours
$summary = $validationClient->getValidationSummary(30);
echo "Error rate: {$summary['error_rate']}%\n";

// Statistiques d'erreurs par champ
$errorsByField = $validationClient->getErrorsByField('invoice', 10);
foreach ($errorsByField as $fieldError) {
    echo "{$fieldError['field']}: {$fieldError['count']} errors\n";
}
```

### Client DIS - Classification de Documents

Le `DisClient` expose les fonctionnalités du Document Intelligence Service (DIS), un module séparé dédié à la classification et l'extraction de métadonnées.

#### Classification Simple

```php
use Netfield\Client\NetfieldClientFactory;

$disClient = NetfieldClientFactory::createDisClient(
    'http://localhost:8888',
    'your-jwt-token'
);

$classification = $disClient->classifyDocument(
    content: $documentContent,
    title: 'Optional Title',
    metadata: ['optional' => 'metadata']
);

// Résultat:
// - doc_type: Type de document (invoice, contract, etc.)
// - category: Catégorie (comptabilite, juridique, etc.)
// - confidence: Score de confiance (0.0-1.0)
// - subtype: Sous-type optionnel
// - enriched_metadata: Métadonnées extraites automatiquement
```

#### Autres Méthodes DIS

```php
// Extraction de métadonnées pour un type spécifique
$metadata = $disClient->extractMetadata(
    content: $documentContent,
    docType: 'invoice'
);

// Récupérer la taxonomie complète
$taxonomy = $disClient->getTaxonomyInfo();

// Récupérer les champs filtrables pour un type
$fields = $disClient->getFilterableFields('invoice');

// Récupérer les champs de métadonnées communs
$commonFields = $disClient->getCommonMetadataFields();
```

#### Gestion des Erreurs DIS

```php
use Netfield\Client\Exception\NetfieldApiException;
use Netfield\Client\Exception\ErrorCode;

try {
    $classification = $disClient->classifyDocument($content);
} catch (NetfieldApiException $e) {
    // Codes d'erreur spécifiques DIS
    switch ($e->getErrorCode()) {
        case ErrorCode::CLASSIFY_CONTENT_EMPTY:
            echo "Contenu vide ou trop court\n";
            break;
        case ErrorCode::CLASSIFY_FAILED:
            echo "Échec de la classification\n";
            break;
        case ErrorCode::CLASSIFY_TAXONOMY_NOT_FOUND:
            echo "Taxonomie non trouvée\n";
            break;
    }
}
```

### Configuration Personnalisée

```php
use GuzzleHttp\Client;
use Monolog\Logger;

$httpClient = new Client([
    'timeout' => 30,
    'verify' => false,  // Pour environnements de test
]);

$logger = new Logger('rag-client');

$client = NetfieldClientFactory::createCustom(
    baseUrl: 'http://localhost:8888/api/v1',
    jwtToken: 'your-token',
    httpOptions: ['timeout' => 30],
    logger: $logger
);
```

### Indexation en Lot

```php
use Netfield\Client\Models\Request\BulkIndexRequest;

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
use Netfield\Client\Auth\JwtAuthenticator;

$token = JwtAuthenticator::generateTestToken(
    tenantId: 'my_client',
    secretKey: 'your-secret-key',
    scopes: ['read', 'write'],
    confidentialityLevels: ['public', 'internal']
);
```

### Configuration Avancée

```php
$client = new NetfieldClient(
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
├── Client/            # Clients API spécialisés
│   ├── NetfieldClient.php        # Client RAG (Q&A et indexation)
│   ├── DisClient.php             # Client DIS (classification documents)
│   ├── MonitoringClient.php      # Client Monitoring (métriques, health, traces)
│   ├── ValidationClient.php      # Client Validation (validation documents)
│   ├── AdminClient.php           # Client Admin (gestion organisations)
│   └── OrganizationClient.php    # Client Organisation (gestion clients)
├── Exception/         # Exceptions personnalisées
│   ├── NetfieldApiException.php  # Exception base avec erreur standardisée
│   └── ErrorCode.php             # Codes d'erreur (CLASSIFY_*, INDEX_*, etc.)
├── Models/            # Modèles de données
│   ├── Request/       # Requêtes API
│   └── Response/      # Réponses API
└── NetfieldClientFactory.php     # Factory principal
```

## 📦 Clients Disponibles

Le SDK PHP offre plusieurs clients spécialisés pour différentes fonctionnalités :

### NetfieldClient - RAG Q&A et Indexation
Client principal pour les fonctionnalités RAG (Retrieval-Augmented Generation) :
- Questions/Réponses avec scoring de confiance
- Streaming Server-Sent Events (SSE)
- Indexation de documents (simple et batch)
- Mise à jour et suppression de documents
- Configuration et statistiques RAG

### DisClient - Classification de Documents
Client pour le module DIS (Document Intelligence Service) :
- Classification automatique de documents
- Extraction de métadonnées
- Gestion de la taxonomie
- Récupération des champs filtrables

### MonitoringClient - Métriques et Monitoring
Client pour le monitoring du système :
- Health checks (global et détaillé)
- Métriques Prometheus
- Traces distribuées
- Résumés de performance
- Tests d'alertes
- Métriques de confiance
- Informations de calibration

### ValidationClient - Validation de Documents
Client pour la validation de documents :
- Validation dry-run (sans indexation)
- Rapports de validation par document
- Résumés de validation
- Recherche d'erreurs de validation
- Statistiques d'erreurs par champ
- Nettoyage des anciens rapports

### AdminClient - Gestion Organisations
Client administrateur pour gérer les organisations :
- CRUD organisations
- Activation/Désactivation
- Statistiques d'utilisation

### OrganizationClient - Gestion Clients
Client pour gérer les clients d'une organisation :
- CRUD clients
- Génération de tokens JWT
- Gestion des permissions

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

$client = NetfieldClientFactory::createCustom(
    baseUrl: 'http://localhost:8888/api/v1',
    jwtToken: $token,
    logger: $logger
);
```

### Gestion des Erreurs

Le client gère automatiquement les codes d'erreur standardisés de l'API Netfield (format `UPPER_SNAKE_CASE`).

#### Gestion Simple

```php
use Netfield\Client\Exception\NetfieldApiException;
use Netfield\Client\Exception\ErrorCode;

try {
    $response = $orgClient->createClientToken($request);
    echo "Token créé: {$response->jwt_token}\n";
} catch (NetfieldApiException $e) {
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
} catch (NetfieldApiException $e) {
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
} catch (NetfieldApiException $e) {
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
} catch (NetfieldApiException $e) {
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