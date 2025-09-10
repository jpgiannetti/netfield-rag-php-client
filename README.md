# Client PHP pour l'API RAG

Une bibliothèque PHP moderne et robuste pour interagir avec le système RAG (Retrieval-Augmented Generation) pour la recherche et l'indexation de documents.

## 🚀 Fonctionnalités

- ✅ **Recherche intelligente** : Questions en langage naturel avec réponses contextualisées
- ✅ **Indexation de documents** : Ajout et mise à jour de documents avec métadonnées
- ✅ **Indexation en lot** : Traitement efficace de multiples documents
- ✅ **Streaming** : Réponses en temps réel via Server-Sent Events
- ✅ **Authentication JWT** : Sécurité et isolation multi-tenant
- ✅ **Gestion d'erreurs** : Exceptions typées et messages détaillés
- ✅ **Logging** : Support PSR-3 pour l'observabilité
- ✅ **Configuration flexible** : Client HTTP personnalisable

## 📦 Installation

```bash
composer require ragapi/php-client
```

### Prérequis

- PHP 8.0 ou supérieur
- Extensions PHP : `json`, `curl`
- Service RAG API accessible

## 🔧 Configuration rapide

### 1. Initialisation du client

```php
<?php
use RagApi\PhpClient\Client\RagClient;
use RagApi\PhpClient\Auth\JwtAuthenticator;

// Avec token JWT existant
$client = new RagClient(
    baseUrl: 'http://localhost:8888',
    jwtToken: 'your-jwt-token-here'
);

// Avec génération d'un token de test
$testToken = JwtAuthenticator::generateTestToken('my-tenant-id');
$client = new RagClient('http://localhost:8888', $testToken);
```

### 2. Premier exemple - Recherche simple

```php
<?php
use RagApi\PhpClient\Models\Request\AskRequest;

try {
    // Créer une requête de recherche
    $askRequest = new AskRequest(
        question: "Quels sont les documents sur les factures ?",
        limit: 5
    );

    // Exécuter la recherche
    $response = $client->ask($askRequest);

    if ($response->isSuccessful()) {
        echo "Réponse: " . $response->getAnswer() . "\n";
        echo "Confiance: " . $response->getConfidenceLevel() . "\n";
        echo "Documents trouvés: " . count($response->getRetrievedDocuments()) . "\n";
    }
} catch (\RagApi\PhpClient\Exception\RagApiException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
```

## 📖 Guide d'utilisation complet

### Recherche de documents

#### Recherche basique

```php
use RagApi\PhpClient\Models\Request\AskRequest;

$request = new AskRequest("Comment calculer la TVA ?");
$response = $client->ask($request);

echo $response->getAnswer();
```

#### Recherche avec filtres

```php
$request = new AskRequest(
    question: "Factures de janvier 2024",
    limit: 10,
    filters: [
        'type' => 'facture',
        'date_range' => '2024-01-01:2024-01-31'
    ]
);

$response = $client->ask($request);
```

#### Recherche en streaming

```php
$request = new AskRequest("Résumé des contrats en cours");

foreach ($client->askStream($request) as $chunk) {
    if (isset($chunk['content'])) {
        echo $chunk['content'];
        flush(); // Afficher immédiatement
    }
}
```

### Indexation de documents

#### Document simple

```php
use RagApi\PhpClient\Models\Request\IndexDocumentRequest;
use RagApi\PhpClient\Models\Request\DocumentInfo;

// Informations du document
$documentInfo = new DocumentInfo(
    title: "Facture Client ABC",
    creationDate: "2024-08-01 19:44:00",
    revision: 1,
    final: true,
    nbPages: 3
);

// Requête d'indexation
$request = new IndexDocumentRequest(
    documentId: "doc-123",
    tenantId: "tenant-001", 
    documentInfo: $documentInfo,
    content: "Contenu OCR du document...",
    metadata: [
        'type' => 'facture',
        'client' => 'ABC Corp',
        'amount' => 1500.00
    ]
);

$response = $client->indexDocument($request);

if ($response->isSuccessful()) {
    echo "Document indexé: " . $response->getDocumentId();
}
```

#### Indexation en lot

```php
use RagApi\PhpClient\Models\Request\BulkIndexRequest;

$documents = [];

// Préparer plusieurs documents
for ($i = 1; $i <= 10; $i++) {
    $docInfo = new DocumentInfo(
        title: "Document $i",
        creationDate: date('Y-m-d H:i:s')
    );
    
    $documents[] = new IndexDocumentRequest(
        documentId: "doc-$i",
        tenantId: "tenant-001",
        documentInfo: $docInfo,
        content: "Contenu du document $i..."
    );
}

// Indexation en lot
$bulkRequest = new BulkIndexRequest("tenant-001", $documents);
$response = $client->bulkIndexDocuments($bulkRequest);

echo sprintf(
    "Indexation: %d/%d documents traités (%.1f%% succès)",
    $response->getIndexedSuccessfully(),
    $response->getTotalDocuments(), 
    $response->getSuccessRate()
);

// Gérer les erreurs
if ($response->hasErrors()) {
    foreach ($response->getErrors() as $error) {
        echo "Erreur sur {$error['document_id']}: {$error['error']}\n";
    }
}
```

### Gestion des erreurs

```php
use RagApi\PhpClient\Exception\RagApiException;
use RagApi\PhpClient\Exception\AuthenticationException;

try {
    $response = $client->ask($askRequest);
} catch (AuthenticationException $e) {
    // Problème d'authentification JWT
    echo "Erreur d'authentification: " . $e->getMessage();
} catch (RagApiException $e) {
    // Autres erreurs de l'API
    echo "Erreur API: " . $e->getMessage();
    
    if ($context = $e->getContext()) {
        print_r($context); // Détails supplémentaires
    }
} catch (\Exception $e) {
    // Erreurs génériques
    echo "Erreur inattendue: " . $e->getMessage();
}
```

### Mise à jour et suppression

#### Mise à jour d'un document

```php
// Préparer les nouvelles données
$updatedInfo = new DocumentInfo(
    title: "Facture Client ABC - Modifiée",
    creationDate: "2024-08-01 19:44:00",
    revision: 2
);

$updateRequest = new IndexDocumentRequest(
    documentId: "doc-123",
    tenantId: "tenant-001",
    documentInfo: $updatedInfo,
    content: "Nouveau contenu OCR...",
    metadata: ['status' => 'updated']
);

$response = $client->updateDocument("doc-123", $updateRequest);
```

#### Suppression d'un document

```php
$result = $client->deleteDocument("doc-123");
echo "Document supprimé: " . $result['status'];
```

## 🔐 Authentification avancée

### Génération de tokens JWT

```php
use RagApi\PhpClient\Auth\JwtAuthenticator;

// Token de production (nécessite la clé secrète)
$token = JwtAuthenticator::generateTestToken(
    tenantId: 'prod-tenant',
    secretKey: 'your-production-secret-key',
    expirationHours: 8
);

// Validation du token
$auth = new JwtAuthenticator($token);
if ($auth->isTokenValid()) {
    echo "Token valide pour: " . $auth->getTenantId();
} else {
    echo "Token expiré ou invalide";
}

// Récupération du payload complet
$payload = $auth->getTokenPayload();
echo "Scopes: " . implode(', ', $payload['scopes']);
echo "Expire: " . date('Y-m-d H:i:s', $payload['exp']);
```

### Configuration personnalisée du client HTTP

```php
use GuzzleHttp\Client as GuzzleClient;

// Client personnalisé avec proxy
$httpClient = new GuzzleClient([
    'timeout' => 60,
    'proxy' => 'http://proxy.company.com:8080',
    'verify' => '/path/to/cacert.pem',
    'headers' => [
        'User-Agent' => 'MyApp/1.0'
    ]
]);

$ragClient = new RagClient(
    baseUrl: 'https://rag-api.company.com',
    jwtToken: $token,
    httpClient: $httpClient
);
```

## 📊 Monitoring et logging

### Avec Monolog

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('rag-client');
$logger->pushHandler(new StreamHandler('rag-client.log', Logger::INFO));

$client = new RagClient(
    baseUrl: 'http://localhost:8888',
    jwtToken: $token,
    httpClient: null,
    logger: $logger
);

// Les requêtes sont maintenant loggées automatiquement
$response = $client->ask($askRequest);
```

### Surveillance de la santé

```php
// Vérification de l'état du service
$health = $client->health();

if ($health->isHealthy()) {
    echo "Service opérationnel\n";
} else {
    echo "Service en panne: " . $health->getStatus() . "\n";
    print_r($health->getDetails());
}

// Récupération des métriques
$thresholds = $client->getConfidenceThresholds();
echo "Seuil de confiance élevé: " . $thresholds['high'];

// Statistiques d'indexation
$stats = $client->getIndexingStats('tenant-001');
echo "Documents indexés: " . $stats['total_documents'];
```

## ⚡ Exemples complets

### Script d'indexation de fichiers

```php
#!/usr/bin/env php
<?php
require 'vendor/autoload.php';

use RagApi\PhpClient\Client\RagClient;
use RagApi\PhpClient\Auth\JwtAuthenticator;
use RagApi\PhpClient\Models\Request\{IndexDocumentRequest, DocumentInfo, BulkIndexRequest};

$token = JwtAuthenticator::generateTestToken('my-tenant');
$client = new RagClient('http://localhost:8888', $token);

$documentsDir = '/path/to/documents';
$documents = [];

foreach (glob($documentsDir . '/*.txt') as $file) {
    $content = file_get_contents($file);
    $basename = basename($file, '.txt');
    
    $docInfo = new DocumentInfo(
        title: $basename,
        creationDate: date('Y-m-d H:i:s', filemtime($file))
    );
    
    $documents[] = new IndexDocumentRequest(
        documentId: $basename,
        tenantId: 'my-tenant',
        documentInfo: $docInfo,
        content: $content
    );
}

if (!empty($documents)) {
    $bulkRequest = new BulkIndexRequest('my-tenant', $documents);
    $response = $client->bulkIndexDocuments($bulkRequest);
    
    echo sprintf(
        "Indexé %d/%d documents (%.1f%% succès)\n",
        $response->getIndexedSuccessfully(),
        $response->getTotalDocuments(),
        $response->getSuccessRate()
    );
}
```

### Interface de recherche interactive

```php
#!/usr/bin/env php
<?php
require 'vendor/autoload.php';

use RagApi\PhpClient\Client\RagClient;
use RagApi\PhpClient\Models\Request\AskRequest;

$client = new RagClient('http://localhost:8888', $argv[1] ?? '');

while (true) {
    echo "\n🔍 Question (ou 'quit' pour sortir): ";
    $question = trim(fgets(STDIN));
    
    if ($question === 'quit') break;
    if (empty($question)) continue;
    
    try {
        $request = new AskRequest($question);
        $response = $client->ask($request);
        
        if ($response->isSuccessful()) {
            echo "\n✅ Réponse ({$response->getConfidenceLevel()}):\n";
            echo $response->getAnswer() . "\n";
            
            $docs = $response->getRetrievedDocuments();
            if (!empty($docs)) {
                echo "\n📄 Sources (" . count($docs) . "):\n";
                foreach (array_slice($docs, 0, 3) as $i => $doc) {
                    echo sprintf(
                        "%d. %s (score: %.2f)\n",
                        $i + 1,
                        $doc['title'],
                        $doc['score']
                    );
                }
            }
        }
    } catch (Exception $e) {
        echo "\n❌ Erreur: " . $e->getMessage() . "\n";
    }
}
```

## 🧪 Tests et développement

### Tests unitaires

```bash
# Installation des dépendances de développement
composer install --dev

# Exécution des tests
composer test

# Analyse statique
composer phpstan

# Vérification du style de code
composer cs-check
composer cs-fix
```

### Configuration pour développement

```php
// .env ou configuration
RAG_API_URL=http://localhost:8888
RAG_JWT_SECRET=super-secret-jwt-key-change-in-production-2024
RAG_TENANT_ID=dev-tenant

// Configuration de test
$client = new RagClient(
    $_ENV['RAG_API_URL'],
    JwtAuthenticator::generateTestToken($_ENV['RAG_TENANT_ID'])
);
```

## 🔗 API de référence

### Classes principales

- **`RagClient`** : Client principal pour toutes les opérations
- **`JwtAuthenticator`** : Gestion de l'authentification JWT
- **`AskRequest`** / **`AskResponse`** : Recherche de documents
- **`IndexDocumentRequest`** / **`IndexResponse`** : Indexation simple
- **`BulkIndexRequest`** / **`BulkIndexResponse`** : Indexation en lot

### Exceptions

- **`RagApiException`** : Erreur générale de l'API
- **`AuthenticationException`** : Problème d'authentification JWT

## 📞 Support et contribution

- **Documentation API** : [http://localhost:8888/docs](http://localhost:8888/docs)
- **Issues** : Signalez les bugs via les issues GitHub
- **Contributions** : Pull requests bienvenues

## 📄 Licence

MIT License - voir le fichier [LICENSE](LICENSE) pour plus de détails.