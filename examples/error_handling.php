<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Netfield\RagClient\NetfieldClientFactory;
use Netfield\RagClient\Client\OrganizationClient;
use Netfield\RagClient\Exception\NetfieldApiException;
use Netfield\RagClient\Exception\ErrorCode;
use Netfield\RagClient\Models\Request\CreateClientTokenRequest;

/**
 * Exemple : Gestion avancée des erreurs avec codes standardisés
 *
 * Ce fichier illustre comment utiliser les codes d'erreur UPPER_SNAKE_CASE
 * pour gérer intelligemment les erreurs et les transmettre au front-end.
 */

// Configuration
$apiUrl = getenv('NETFIELD_API_URL') ?: 'http://localhost:8888/api/v1';
$orgToken = getenv('ORG_JWT_TOKEN') ?: 'your-organization-token';

// Créer le client organisation
$orgClient = new OrganizationClient($apiUrl, $orgToken);

echo "=== Exemple 1 : Gestion basique avec codes d'erreur ===\n\n";

try {
    $request = new CreateClientTokenRequest(
        clientName: 'test-client-' . time(),
        scopes: ['read', 'write'],
        confidentialityLevels: ['public', 'internal']
    );

    $response = $orgClient->createClientToken($request);
    echo "✅ Token créé avec succès: {$response->clientName}\n";
    echo "   JWT: " . substr($response->jwtToken, 0, 50) . "...\n\n";
} catch (NetfieldApiException $e) {
    echo "❌ Erreur: {$e->getErrorCode()}\n";
    echo "   Message: {$e->getMessage()}\n\n";
}

echo "=== Exemple 2 : Traitement conditionnel selon le code ===\n\n";

try {
    // Essayer de créer un client existant
    $request = new CreateClientTokenRequest(
        clientName: 'duplicate-client',
        scopes: ['read'],
        confidentialityLevels: ['public']
    );

    $response = $orgClient->createClientToken($request);
    echo "✅ Token créé\n\n";
} catch (NetfieldApiException $e) {
    switch ($e->getErrorCode()) {
        case ErrorCode::ORG_CLIENT_ALREADY_EXISTS:
            echo "⚠️  Le client existe déjà - on peut continuer\n";
            echo "   Détails: " . json_encode($e->getDetails()) . "\n\n";
            break;

        case ErrorCode::AUTH_TOKEN_EXPIRED:
            echo "🔄 Token expiré - refresh nécessaire\n\n";
            // refreshToken() et retry
            break;

        case ErrorCode::AUTH_INSUFFICIENT_PERMISSIONS:
            echo "🚫 Permissions insuffisantes\n\n";
            break;

        default:
            echo "❌ Erreur non gérée: {$e->getErrorCode()}\n";
            echo "   Message: {$e->getMessage()}\n\n";
    }
}

echo "=== Exemple 3 : Helpers de classification ===\n\n";

function handleApiError(NetfieldApiException $e, callable $retryCallback): void
{
    echo "Code d'erreur: {$e->getErrorCode()}\n";

    if ($e->isRetryable()) {
        echo "⚠️  Erreur temporaire - retry automatique dans 2s...\n";
        sleep(2);
        $retryCallback();
    } elseif ($e->needsAuthRefresh()) {
        echo "🔄 Token expiré - refresh requis\n";
        // refreshToken() puis retry
    } elseif ($e->isCritical()) {
        echo "🚨 ERREUR CRITIQUE - Alerter l'équipe ops!\n";
        // sendAlert($e);
    } else {
        echo "ℹ️  Erreur standard - traitement normal\n";
    }

    echo "   Trace ID: {$e->getTraceId()}\n\n";
}

try {
    // Simuler une erreur
    throw NetfieldApiException::fromGuzzleException(
        new \GuzzleHttp\Exception\ServerException(
            'Service Unavailable',
            new \GuzzleHttp\Psr7\Request('POST', '/test'),
            new \GuzzleHttp\Psr7\Response(503, [], json_encode([
                'error_code' => 'SYSTEM_SERVICE_UNAVAILABLE',
                'message' => 'Service temporairement indisponible',
                'trace_id' => 'abc-123-def-456',
            ]))
        ),
        'Test error'
    );
} catch (NetfieldApiException $e) {
    handleApiError($e, function () {
        echo "   → Retry effectué avec succès\n\n";
    });
}

echo "=== Exemple 4 : Sérialisation JSON pour le front-end ===\n\n";

function handleApiCallForFrontend(callable $apiCall): array
{
    try {
        $result = $apiCall();
        return [
            'success' => true,
            'data' => $result,
        ];
    } catch (NetfieldApiException $e) {
        // Convertir l'exception en format JSON standardisé
        return [
            'success' => false,
            'error' => $e->toArray(),
        ];
    }
}

$response = handleApiCallForFrontend(function () use ($orgClient) {
    return $orgClient->getOrganizationInfo();
});

echo "Réponse pour le front-end:\n";
echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Exemple 5 : Logging structuré avec contexte complet ===\n\n";

try {
    // Simuler une erreur avec détails
    throw NetfieldApiException::fromGuzzleException(
        new \GuzzleHttp\Exception\ClientException(
            'Client Already Exists',
            new \GuzzleHttp\Psr7\Request('POST', '/test'),
            new \GuzzleHttp\Psr7\Response(409, [], json_encode([
                'error_code' => 'ORG_CLIENT_ALREADY_EXISTS',
                'message' => 'Un client avec ce nom existe déjà',
                'details' => [
                    'client_name' => 'duplicate-client',
                    'organization_id' => 'org_123',
                ],
                'field' => 'client_name',
                'timestamp' => date('c'),
                'trace_id' => 'trace-789',
            ]))
        ),
        'Failed to create client'
    );
} catch (NetfieldApiException $e) {
    // Logging structuré avec toutes les informations
    $logData = [
        'level' => $e->isCritical() ? 'CRITICAL' : 'ERROR',
        'error_code' => $e->getErrorCode(),
        'message' => $e->getMessage(),
        'details' => $e->getDetails(),
        'field' => $e->getField(),
        'timestamp' => $e->getTimestamp(),
        'trace_id' => $e->getTraceId(),
        'http_status' => $e->getCode(),
        'classification' => [
            'retryable' => $e->isRetryable(),
            'critical' => $e->isCritical(),
            'needs_auth_refresh' => $e->needsAuthRefresh(),
        ],
    ];

    echo "📝 Log structuré:\n";
    echo json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
}

echo "=== Exemple 6 : Wrapper personnalisé pour votre application ===\n\n";

class MyAppApiClient
{
    private OrganizationClient $client;

    public function __construct(OrganizationClient $client)
    {
        $this->client = $client;
    }

    /**
     * Wrapper intelligent qui gère automatiquement les erreurs courantes
     */
    public function createClient(string $name, array $scopes): array
    {
        try {
            $request = new CreateClientTokenRequest(
                clientName: $name,
                scopes: $scopes,
                confidentialityLevels: ['public']
            );

            $response = $this->client->createClientToken($request);

            return [
                'status' => 'created',
                'client_name' => $response->clientName,
                'jwt_token' => $response->jwtToken,
            ];
        } catch (NetfieldApiException $e) {
            // Gestion intelligente selon le code
            return match ($e->getErrorCode()) {
                ErrorCode::ORG_CLIENT_ALREADY_EXISTS => [
                    'status' => 'exists',
                    'message' => 'Le client existe déjà',
                    'client_name' => $e->getDetails()['client_name'] ?? $name,
                ],

                ErrorCode::AUTH_INSUFFICIENT_PERMISSIONS => [
                    'status' => 'forbidden',
                    'message' => 'Permissions insuffisantes pour créer un client',
                ],

                default => [
                    'status' => 'error',
                    'error_code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                    'is_retryable' => $e->isRetryable(),
                ],
            };
        }
    }
}

$myClient = new MyAppApiClient($orgClient);
$result = $myClient->createClient('my-app-client', ['read', 'write']);

echo "Résultat de l'appel métier:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

echo "✅ Tous les exemples terminés !\n";
