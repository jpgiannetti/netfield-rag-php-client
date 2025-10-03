<?php
/**
 * Exemple d'utilisation du système de codes d'erreur du client RAG PHP
 *
 * Ce fichier démontre comment gérer les erreurs avec les codes standardisés UPPER_SNAKE_CASE
 * et implémenter une stratégie de retry intelligente.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netfield\RagClient\Client\RagClient;
use Netfield\RagClient\DTO\AskRequest;
use Netfield\RagClient\Exception\RagApiException;
use Netfield\RagClient\Exception\ErrorCode;
use Psr\Log\LoggerInterface;

/**
 * Exemple 1: Gestion basique des codes d'erreur
 */
function basicErrorHandling(RagClient $client, LoggerInterface $logger): void
{
    try {
        $request = new AskRequest('Quelle est la procédure de sécurité?');
        $response = $client->ask($request);

        echo "Réponse: " . $response->getAnswer() . "\n";
        echo "Confiance: " . $response->getConfidenceScore() . "\n";

    } catch (RagApiException $e) {
        // Récupérer le code d'erreur standardisé
        $errorCode = $e->getErrorCode();

        if ($errorCode) {
            // Gestion spécifique par code d'erreur
            switch ($errorCode) {
                case ErrorCode::AUTH_TOKEN_EXPIRED:
                    echo "⚠️  Token expiré - Veuillez vous reconnecter\n";
                    // Implémenter la logique de refresh du token
                    break;

                case ErrorCode::AUTH_TENANT_UNAUTHORIZED:
                    echo "🚫 Accès non autorisé pour ce tenant\n";
                    break;

                case ErrorCode::RAG_NO_RELEVANT_DOCUMENTS:
                    echo "📭 Aucun document pertinent trouvé\n";
                    // Afficher un message utilisateur approprié
                    break;

                case ErrorCode::RAG_CONFIDENCE_TOO_LOW:
                    echo "⚠️  Confiance trop faible - Résultat peu fiable\n";
                    // Demander à l'utilisateur de reformuler
                    break;

                default:
                    echo "❌ Erreur: {$e->getMessage()}\n";
                    echo "Code: {$errorCode}\n";
            }

            // Récupérer les détails supplémentaires
            $details = $e->getDetails();
            if ($details) {
                echo "Détails: " . json_encode($details, JSON_PRETTY_PRINT) . "\n";
            }

            // Récupérer le trace_id pour le debugging
            $traceId = $e->getTraceId();
            if ($traceId) {
                $logger->error('RAG query failed', [
                    'error_code' => $errorCode,
                    'trace_id' => $traceId,
                    'field' => $e->getField(),
                ]);
            }
        } else {
            // Erreur sans code (format legacy)
            echo "❌ Erreur: {$e->getMessage()}\n";
        }
    }
}

/**
 * Exemple 2: Stratégie de retry intelligente basée sur les codes d'erreur
 */
function smartRetryStrategy(RagClient $client, LoggerInterface $logger): void
{
    $maxRetries = 3;
    $retryDelay = 1000; // milliseconds

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $request = new AskRequest('Question importante');
            $response = $client->ask($request);

            echo "✅ Succès après $attempt tentative(s)\n";
            return;

        } catch (RagApiException $e) {
            $errorCode = $e->getErrorCode();

            // Vérifier si l'erreur est retryable
            if ($e->isRetryable()) {
                $logger->warning("Tentative $attempt échouée - Retry dans {$retryDelay}ms", [
                    'error_code' => $errorCode,
                    'attempt' => $attempt,
                ]);

                if ($attempt < $maxRetries) {
                    usleep($retryDelay * 1000);
                    $retryDelay *= 2; // Exponential backoff
                    continue;
                }
            }

            // Erreur non retryable ou max retries atteint
            if ($e->isCritical()) {
                $logger->critical('Erreur critique non récupérable', [
                    'error_code' => $errorCode,
                    'trace_id' => $e->getTraceId(),
                ]);
                throw $e; // Propager l'erreur critique
            }

            // Erreur nécessitant un refresh d'authentification
            if ($e->needsAuthRefresh()) {
                echo "🔄 Refresh du token requis\n";
                // Implémenter la logique de refresh
                // refreshAuthToken();
                // Puis retry
            }

            throw $e;
        }
    }
}

/**
 * Exemple 3: Messages d'erreur personnalisés par locale
 */
function customErrorMessages(RagClient $client): void
{
    $errorMessages = [
        'fr' => [
            ErrorCode::AUTH_TOKEN_EXPIRED => 'Votre session a expiré. Veuillez vous reconnecter.',
            ErrorCode::AUTH_TOKEN_INVALID => 'Token d\'authentification invalide.',
            ErrorCode::RAG_NO_RELEVANT_DOCUMENTS => 'Aucun document ne correspond à votre recherche.',
            ErrorCode::RAG_CONFIDENCE_TOO_LOW => 'La réponse n\'est pas assez fiable. Essayez de reformuler votre question.',
            ErrorCode::RAG_LLM_UNAVAILABLE => 'Le service de réponse est temporairement indisponible. Réessayez dans quelques instants.',
            ErrorCode::INDEX_CONTENT_TOO_SHORT => 'Le contenu du document est trop court pour être indexé.',
            ErrorCode::VALIDATION_MISSING_FIELD => 'Champ requis manquant dans la requête.',
        ],
        'en' => [
            ErrorCode::AUTH_TOKEN_EXPIRED => 'Your session has expired. Please log in again.',
            ErrorCode::AUTH_TOKEN_INVALID => 'Invalid authentication token.',
            ErrorCode::RAG_NO_RELEVANT_DOCUMENTS => 'No documents match your search.',
            ErrorCode::RAG_CONFIDENCE_TOO_LOW => 'The answer is not reliable enough. Try rephrasing your question.',
            ErrorCode::RAG_LLM_UNAVAILABLE => 'The answer service is temporarily unavailable. Please try again shortly.',
            ErrorCode::INDEX_CONTENT_TOO_SHORT => 'Document content is too short to be indexed.',
            ErrorCode::VALIDATION_MISSING_FIELD => 'Required field is missing in the request.',
        ],
    ];

    $locale = 'fr'; // ou récupérer depuis la config utilisateur

    try {
        $request = new AskRequest('Test question');
        $response = $client->ask($request);

    } catch (RagApiException $e) {
        $errorCode = $e->getErrorCode();

        if ($errorCode && isset($errorMessages[$locale][$errorCode])) {
            // Afficher le message personnalisé
            echo "❌ " . $errorMessages[$locale][$errorCode] . "\n";
        } else {
            // Fallback au message de l'API
            echo "❌ " . $e->getMessage() . "\n";
        }

        // Toujours logger le code technique pour le debugging
        error_log("Error Code: " . ($errorCode ?? 'UNKNOWN') . " | Trace ID: " . ($e->getTraceId() ?? 'N/A'));
    }
}

/**
 * Exemple 4: Monitoring et alertes basés sur les codes d'erreur
 */
function monitoringAndAlerts(RagClient $client, LoggerInterface $logger): void
{
    $criticalErrors = [
        ErrorCode::SYSTEM_INTERNAL_ERROR,
        ErrorCode::SYSTEM_DATABASE_ERROR,
        ErrorCode::INDEX_WEAVIATE_UNAVAILABLE,
        ErrorCode::RAG_LLM_UNAVAILABLE,
    ];

    $warningErrors = [
        ErrorCode::RAG_CONFIDENCE_TOO_LOW,
        ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
    ];

    try {
        $request = new AskRequest('Question de production');
        $response = $client->ask($request);

        // Log succès avec métriques
        $logger->info('RAG query success', [
            'confidence' => $response->getConfidenceScore(),
            'sources_count' => count($response->getSources()),
        ]);

    } catch (RagApiException $e) {
        $errorCode = $e->getErrorCode();
        $errorData = $e->getErrorData();

        // Contexte complet pour le monitoring
        $context = [
            'error_code' => $errorCode,
            'trace_id' => $e->getTraceId(),
            'timestamp' => $e->getTimestamp(),
            'field' => $e->getField(),
            'details' => $e->getDetails(),
        ];

        // Alertes critiques
        if (in_array($errorCode, $criticalErrors, true)) {
            $logger->critical('CRITICAL: RAG system error', $context);
            // Envoyer une alerte PagerDuty/Slack/etc
            // sendCriticalAlert($errorCode, $context);
        }
        // Warnings
        elseif (in_array($errorCode, $warningErrors, true)) {
            $logger->warning('RAG query warning', $context);
        }
        // Erreurs standards
        else {
            $logger->error('RAG query failed', $context);
        }

        // Métriques pour Prometheus/Datadog
        // incrementMetric('rag.errors', ['error_code' => $errorCode]);
    }
}

/**
 * Exemple 5: Vérification de la disponibilité avec gestion d'erreur
 */
function healthCheckWithErrorHandling(RagClient $client): bool
{
    try {
        $health = $client->healthCheck();

        if ($health['status'] === 'healthy') {
            echo "✅ Service disponible\n";
            return true;
        } else {
            echo "⚠️  Service dégradé: " . ($health['message'] ?? 'Unknown') . "\n";
            return false;
        }

    } catch (RagApiException $e) {
        $errorCode = $e->getErrorCode();

        switch ($errorCode) {
            case ErrorCode::MONITOR_SERVICE_UNHEALTHY:
                echo "🔴 Service indisponible - Mode maintenance\n";
                break;

            case ErrorCode::SYSTEM_SERVICE_UNAVAILABLE:
                echo "🔴 Service totalement indisponible\n";
                break;

            default:
                echo "❌ Erreur lors du health check: {$e->getMessage()}\n";
        }

        return false;
    }
}

// Usage
if (php_sapi_name() === 'cli') {
    // Configuration
    $config = [
        'base_url' => getenv('RAG_API_URL') ?: 'http://localhost:8888',
        'jwt_token' => getenv('RAG_JWT_TOKEN') ?: 'your-jwt-token-here',
    ];

    // Créer le client (exemple simplifié)
    // $client = new RagClient($config['base_url'], $config['jwt_token']);
    // $logger = new YourLogger();

    echo "=== Exemples de gestion des codes d'erreur ===\n\n";

    // Décommenter pour tester chaque exemple:
    // basicErrorHandling($client, $logger);
    // smartRetryStrategy($client, $logger);
    // customErrorMessages($client);
    // monitoringAndAlerts($client, $logger);
    // healthCheckWithErrorHandling($client);
}
