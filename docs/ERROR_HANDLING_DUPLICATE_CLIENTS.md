# Gestion des erreurs de clients dupliqués

Ce document explique comment gérer l'erreur `ORG_CLIENT_ALREADY_EXISTS` qui est retournée lorsqu'on tente de créer un client avec un nom qui existe déjà dans la même organisation.

## Code d'erreur

- **Code**: `ORG_CLIENT_ALREADY_EXISTS`
- **HTTP Status**: `409 Conflict`
- **Catégorie**: Organisation (préfixe `ORG_`)

## Quand cette erreur se produit-elle ?

Cette erreur est retournée par l'API RAG lorsque vous tentez de créer un nouveau client pour une organisation, mais qu'un client avec le même nom existe déjà dans cette organisation.

**Important**: La contrainte d'unicité est basée sur la combinaison `(organization_id, client_name)`. Cela signifie que :

- ✅ Deux organisations **différentes** peuvent avoir des clients avec le **même nom**
- ❌ Une organisation **ne peut pas** avoir deux clients avec le **même nom**

## Exemple de réponse d'erreur

Lorsque vous tentez de créer un client dupliqué, l'API retourne une réponse HTTP 409 avec le format suivant :

```json
{
  "error_code": "ORG_CLIENT_ALREADY_EXISTS",
  "message": "Un client avec ce nom existe déjà dans cette organisation",
  "details": {
    "client_name": "Mon Client",
    "organization_id": "org-abc123"
  },
  "timestamp": "2025-10-05T14:30:00Z",
  "trace_id": "xyz789"
}
```

## Utilisation avec le client PHP

### 1. Détection basique de l'erreur

```php
use Netfield\RagClient\NetfieldClient;
use Netfield\RagClient\Exception\NetfieldApiException;
use Netfield\RagClient\Exception\ErrorCode;

try {
    $client = new NetfieldClient($apiUrl, $token);
    $response = $client->createClientToken([
        'client_name' => 'Mon Client',
        'client_description' => 'Description du client',
        'scopes' => ['read', 'write'],
        'confidentiality_levels' => ['public', 'internal'],
        'expires_in_days' => 365,
    ]);
} catch (NetfieldApiException $e) {
    if ($e->getErrorCode() === ErrorCode::ORG_CLIENT_ALREADY_EXISTS) {
        echo "Erreur : Ce nom de client est déjà utilisé dans votre organisation.\n";
        echo "Veuillez choisir un nom différent.\n";

        // Récupérer les détails de l'erreur
        $details = $e->getDetails();
        echo "Client en conflit : " . ($details['client_name'] ?? 'N/A') . "\n";
        echo "Organisation : " . ($details['organization_id'] ?? 'N/A') . "\n";
    } else {
        // Gérer d'autres types d'erreurs
        throw $e;
    }
}
```

### 2. Gestion avec retry automatique (nom alternatif)

```php
use Netfield\RagClient\NetfieldClient;
use Netfield\RagClient\Exception\NetfieldApiException;
use Netfield\RagClient\Exception\ErrorCode;

function createClientWithFallback(NetfieldClient $client, string $baseName, array $clientData): array
{
    $attempt = 0;
    $maxAttempts = 5;

    while ($attempt < $maxAttempts) {
        try {
            $clientName = $attempt === 0 ? $baseName : "{$baseName} ({$attempt})";
            $clientData['client_name'] = $clientName;

            return $client->createClientToken($clientData);
        } catch (NetfieldApiException $e) {
            if ($e->getErrorCode() === ErrorCode::ORG_CLIENT_ALREADY_EXISTS) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException(
                        "Impossible de créer le client après {$maxAttempts} tentatives avec des noms différents.",
                        0,
                        $e
                    );
                }
                continue; // Réessayer avec un nouveau nom
            }

            // Autre erreur, on la propage
            throw $e;
        }
    }

    throw new \RuntimeException("Nombre maximum de tentatives atteint");
}

// Utilisation
try {
    $result = createClientWithFallback($client, 'Mon Client', [
        'client_description' => 'Description',
        'scopes' => ['read', 'write'],
        'confidentiality_levels' => ['public'],
        'expires_in_days' => 365,
    ]);

    echo "Client créé : " . $result['client_name'] . "\n";
} catch (\Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
```

### 3. Validation préalable avec liste des clients existants

```php
use Netfield\RagClient\NetfieldClient;
use Netfield\RagClient\Exception\NetfieldApiException;
use Netfield\RagClient\Exception\ErrorCode;

function isClientNameAvailable(NetfieldClient $client, string $clientName): bool
{
    try {
        $existingClients = $client->listMyClients();

        foreach ($existingClients['clients'] as $existingClient) {
            if ($existingClient['client_name'] === $clientName) {
                return false; // Nom déjà utilisé
            }
        }

        return true; // Nom disponible
    } catch (NetfieldApiException $e) {
        // En cas d'erreur de récupération, on laisse l'API gérer la validation
        return true;
    }
}

function createClientSafely(NetfieldClient $client, array $clientData): array
{
    $clientName = $clientData['client_name'];

    // Vérification préalable (optionnelle mais recommandée pour UX)
    if (!isClientNameAvailable($client, $clientName)) {
        throw new \InvalidArgumentException(
            "Le nom '{$clientName}' est déjà utilisé dans cette organisation."
        );
    }

    try {
        return $client->createClientToken($clientData);
    } catch (NetfieldApiException $e) {
        if ($e->getErrorCode() === ErrorCode::ORG_CLIENT_ALREADY_EXISTS) {
            // Race condition : le client a été créé entre la vérification et la création
            throw new \RuntimeException(
                "Le nom '{$clientName}' a été utilisé par un autre processus.",
                0,
                $e
            );
        }
        throw $e;
    }
}
```

### 4. Logging et monitoring

```php
use Netfield\RagClient\NetfieldClient;
use Netfield\RagClient\Exception\NetfieldApiException;
use Netfield\RagClient\Exception\ErrorCode;
use Psr\Log\LoggerInterface;

function createClientWithLogging(
    NetfieldClient $client,
    array $clientData,
    LoggerInterface $logger
): array {
    try {
        $result = $client->createClientToken($clientData);

        $logger->info('Client créé avec succès', [
            'client_name' => $result['client_name'],
            'client_id' => $result['client_id'],
            'organization_id' => $clientData['organization_id'] ?? 'N/A',
        ]);

        return $result;
    } catch (NetfieldApiException $e) {
        if ($e->getErrorCode() === ErrorCode::ORG_CLIENT_ALREADY_EXISTS) {
            $logger->warning('Tentative de création de client dupliqué', [
                'error_code' => $e->getErrorCode(),
                'client_name' => $clientData['client_name'],
                'details' => $e->getDetails(),
                'trace_id' => $e->getTraceId(),
            ]);

            // Retourner une erreur conviviale à l'utilisateur
            throw new \InvalidArgumentException(
                "Ce nom de client est déjà utilisé. Veuillez choisir un nom différent.",
                409,
                $e
            );
        }

        // Autres erreurs
        $logger->error('Erreur lors de la création du client', [
            'error_code' => $e->getErrorCode(),
            'message' => $e->getMessage(),
            'trace_id' => $e->getTraceId(),
        ]);

        throw $e;
    }
}
```

## Propriétés de l'erreur

### Méthodes NetfieldApiException disponibles

```php
$exception->getCode()           // 409 (HTTP status)
$exception->getErrorCode()      // "ORG_CLIENT_ALREADY_EXISTS"
$exception->getMessage()        // "Un client avec ce nom existe déjà..."
$exception->getDetails()        // ['client_name' => '...', 'organization_id' => '...']
$exception->getTimestamp()      // "2025-10-05T14:30:00Z"
$exception->getTraceId()        // "xyz789"

// Helpers
$exception->isRetryable()       // false (409 n'est pas retryable)
$exception->isCritical()        // false (erreur utilisateur, pas système)
$exception->needsAuthRefresh()  // false (pas une erreur d'auth)
```

### Helpers ErrorCode statiques

```php
ErrorCode::isRetryable(ErrorCode::ORG_CLIENT_ALREADY_EXISTS)      // false
ErrorCode::isCritical(ErrorCode::ORG_CLIENT_ALREADY_EXISTS)       // false
ErrorCode::needsAuthRefresh(ErrorCode::ORG_CLIENT_ALREADY_EXISTS) // false
```

## Bonnes pratiques

### ✅ À faire

1. **Vérifier le code d'erreur spécifique** au lieu de se fier uniquement au code HTTP
2. **Afficher un message clair** à l'utilisateur avec une action à entreprendre
3. **Logger les tentatives de duplication** pour le monitoring
4. **Proposer des noms alternatifs** automatiquement si possible
5. **Utiliser le `trace_id`** pour le debugging et le support

### ❌ À éviter

1. **Ne pas réessayer** automatiquement avec le même nom (409 n'est pas retryable)
2. **Ne pas ignorer** les détails de l'erreur (client_name, organization_id)
3. **Ne pas traiter** comme une erreur critique (c'est une erreur utilisateur)
4. **Ne pas exposer** les détails techniques à l'utilisateur final

## Messages utilisateur recommandés

### Pour l'interface utilisateur

```
❌ "Erreur 409"
❌ "ORG_CLIENT_ALREADY_EXISTS"
❌ "Un client avec ce nom existe déjà dans cette organisation"

✅ "Ce nom est déjà utilisé. Veuillez choisir un nom différent."
✅ "Un autre client porte déjà ce nom dans votre organisation."
✅ "Nom indisponible. Suggestions : 'Mon Client (1)', 'Mon Client (2)'..."
```

### Pour les logs

```php
$logger->warning('Duplicate client creation attempt', [
    'error_code' => 'ORG_CLIENT_ALREADY_EXISTS',
    'http_status' => 409,
    'attempted_name' => 'Mon Client',
    'organization_id' => 'org-123',
    'trace_id' => 'xyz789',
    'user_id' => $currentUserId,
]);
```

## Codes d'erreur liés

Si vous rencontrez d'autres problèmes lors de la création de clients, consultez ces codes :

- `ORG_CLIENT_CREATE_FAILED` - Échec général de création (500)
- `ORG_CLIENT_NOT_FOUND` - Client non trouvé (404)
- `ORG_LIMIT_EXCEEDED` - Limite de clients atteinte (403)
- `ADMIN_CLIENT_LIMIT_EXCEEDED` - Limite administrative dépassée (403)
- `AUTH_ORGANIZATION_TOKEN_REQUIRED` - Token organisation requis (403)
- `REQUEST_VALIDATION_ERROR` - Données invalides (400)

## Support et debugging

Si vous rencontrez des problèmes :

1. **Vérifiez le `trace_id`** dans la réponse d'erreur
2. **Consultez les logs** de l'API avec ce trace_id
3. **Vérifiez la liste des clients** existants avec `listMyClients()`
4. **Contactez le support** en fournissant le trace_id

## Changelog

- **2025-10-05**: Ajout du code d'erreur `ORG_CLIENT_ALREADY_EXISTS` avec HTTP 409
- Migration de l'ancien comportement (500 Internal Server Error) vers 409 Conflict
