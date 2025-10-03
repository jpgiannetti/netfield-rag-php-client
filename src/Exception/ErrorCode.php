<?php

declare(strict_types=1);

namespace Netfield\RagClient\Exception;

/**
 * Codes d'erreur standardisés de l'API RAG
 *
 * Format: UPPER_SNAKE_CASE avec préfixe de domaine (AUTH_*, INDEX_*, RAG_*, etc.)
 *
 * UTILISATION:
 *     try {
 *         $client->indexDocument($data);
 *     } catch (RagApiException $e) {
 *         // Vérifier le code d'erreur
 *         if ($e->getErrorCode() === ErrorCode::INDEX_WEAVIATE_CONNECTION_ERROR) {
 *             // Erreur retryable - réessayer
 *             retry($operation);
 *         }
 *
 *         // Ou utiliser les helpers
 *         if ($e->isRetryable()) {
 *             retry($operation);
 *         }
 *         if ($e->needsAuthRefresh()) {
 *             refreshToken();
 *         }
 *         if ($e->isCritical()) {
 *             alertAdmin($e);
 *         }
 *
 *         // Personnaliser les messages
 *         $messages = [
 *             ErrorCode::AUTH_TOKEN_EXPIRED => 'Votre session a expiré',
 *             ErrorCode::INDEX_DOCUMENT_NOT_FOUND => 'Document introuvable',
 *         ];
 *         echo $messages[$e->getErrorCode()] ?? $e->getMessage();
 *     }
 *
 * HELPERS DISPONIBLES:
 *     ErrorCode::isRetryable($code)     - Erreurs qui peuvent être retentées
 *     ErrorCode::isCritical($code)      - Erreurs critiques nécessitant une alerte
 *     ErrorCode::needsAuthRefresh($code) - Erreurs nécessitant un refresh du token
 *     ErrorCode::getCategory($code)     - Catégorie de l'erreur (AUTH, INDEX, etc.)
 *
 * MÉTHODES DE RagApiException:
 *     $e->getErrorCode()      - Code d'erreur UPPER_SNAKE_CASE
 *     $e->getErrorData()      - Données complètes de l'erreur
 *     $e->getDetails()        - Détails additionnels
 *     $e->getField()          - Champ concerné (validation)
 *     $e->getTimestamp()      - Timestamp de l'erreur
 *     $e->getTraceId()        - ID de trace pour debugging
 *     $e->isRetryable()       - Booléen: erreur retryable?
 *     $e->isCritical()        - Booléen: erreur critique?
 *     $e->needsAuthRefresh()  - Booléen: refresh token nécessaire?
 */
final class ErrorCode
{
    // ============================================================================
    // ERREURS D'AUTHENTIFICATION (AUTH_*)
    // ============================================================================
    public const AUTH_TOKEN_MISSING = 'AUTH_TOKEN_MISSING';
    public const AUTH_TOKEN_INVALID = 'AUTH_TOKEN_INVALID';
    public const AUTH_TOKEN_EXPIRED = 'AUTH_TOKEN_EXPIRED';
    public const AUTH_TOKEN_MALFORMED = 'AUTH_TOKEN_MALFORMED';
    public const AUTH_INSUFFICIENT_PERMISSIONS = 'AUTH_INSUFFICIENT_PERMISSIONS';
    public const AUTH_ADMIN_TOKEN_REQUIRED = 'AUTH_ADMIN_TOKEN_REQUIRED';
    public const AUTH_ORGANIZATION_TOKEN_REQUIRED = 'AUTH_ORGANIZATION_TOKEN_REQUIRED';
    public const AUTH_INVALID_CREDENTIALS = 'AUTH_INVALID_CREDENTIALS';
    public const AUTH_TENANT_UNAUTHORIZED = 'AUTH_TENANT_UNAUTHORIZED';
    public const AUTH_TENANT_DEACTIVATED = 'AUTH_TENANT_DEACTIVATED';

    // ============================================================================
    // ERREURS D'INDEXATION (INDEX_*)
    // ============================================================================
    public const INDEX_DOCUMENT_NOT_FOUND = 'INDEX_DOCUMENT_NOT_FOUND';
    public const INDEX_CONTENT_TOO_SHORT = 'INDEX_CONTENT_TOO_SHORT';
    public const INDEX_CONTENT_EMPTY = 'INDEX_CONTENT_EMPTY';
    public const INDEX_INVALID_METADATA = 'INDEX_INVALID_METADATA';
    public const INDEX_INVALID_TENANT_ID = 'INDEX_INVALID_TENANT_ID';
    public const INDEX_DOCUMENT_ID_REQUIRED = 'INDEX_DOCUMENT_ID_REQUIRED';
    public const INDEX_TENANT_MISMATCH = 'INDEX_TENANT_MISMATCH';
    public const INDEX_CLASSIFICATION_TIMEOUT = 'INDEX_CLASSIFICATION_TIMEOUT';
    public const INDEX_CLASSIFICATION_FAILED = 'INDEX_CLASSIFICATION_FAILED';
    public const INDEX_WEAVIATE_CONNECTION_ERROR = 'INDEX_WEAVIATE_CONNECTION_ERROR';
    public const INDEX_WEAVIATE_UNAVAILABLE = 'INDEX_WEAVIATE_UNAVAILABLE';
    public const INDEX_BATCH_SIZE_EXCEEDED = 'INDEX_BATCH_SIZE_EXCEEDED';
    public const INDEX_DUPLICATE_DOCUMENT_ID = 'INDEX_DUPLICATE_DOCUMENT_ID';
    public const INDEX_UPDATE_NO_CHANGES = 'INDEX_UPDATE_NO_CHANGES';
    public const INDEX_DELETE_FAILED = 'INDEX_DELETE_FAILED';

    // ============================================================================
    // ERREURS RAG / RECHERCHE (RAG_*)
    // ============================================================================
    public const RAG_QUESTION_TOO_SHORT = 'RAG_QUESTION_TOO_SHORT';
    public const RAG_QUESTION_EMPTY = 'RAG_QUESTION_EMPTY';
    public const RAG_QUESTION_OFF_TOPIC = 'RAG_QUESTION_OFF_TOPIC';
    public const RAG_NO_DOCUMENTS_FOUND = 'RAG_NO_DOCUMENTS_FOUND';
    public const RAG_NO_RELEVANT_DOCUMENTS = 'RAG_NO_RELEVANT_DOCUMENTS';
    public const RAG_SEARCH_FAILED = 'RAG_SEARCH_FAILED';
    public const RAG_LLM_UNAVAILABLE = 'RAG_LLM_UNAVAILABLE';
    public const RAG_LLM_GENERATION_FAILED = 'RAG_LLM_GENERATION_FAILED';
    public const RAG_LLM_TIMEOUT = 'RAG_LLM_TIMEOUT';
    public const RAG_CONFIDENCE_TOO_LOW = 'RAG_CONFIDENCE_TOO_LOW';
    public const RAG_VALIDATION_FAILED = 'RAG_VALIDATION_FAILED';
    public const RAG_STREAMING_ERROR = 'RAG_STREAMING_ERROR';
    public const RAG_CONTEXT_TOO_LARGE = 'RAG_CONTEXT_TOO_LARGE';
    public const RAG_INVALID_FILTERS = 'RAG_INVALID_FILTERS';
    public const RAG_MODEL_NOT_FOUND = 'RAG_MODEL_NOT_FOUND';

    // ============================================================================
    // ERREURS DE CLASSIFICATION (CLASSIFY_*)
    // ============================================================================
    public const CLASSIFY_CONTENT_EMPTY = 'CLASSIFY_CONTENT_EMPTY';
    public const CLASSIFY_FAILED = 'CLASSIFY_FAILED';
    public const CLASSIFY_INVALID_DOC_TYPE = 'CLASSIFY_INVALID_DOC_TYPE';
    public const CLASSIFY_UNSUPPORTED_TYPE = 'CLASSIFY_UNSUPPORTED_TYPE';
    public const CLASSIFY_METADATA_EXTRACTION_FAILED = 'CLASSIFY_METADATA_EXTRACTION_FAILED';
    public const CLASSIFY_TAXONOMY_NOT_FOUND = 'CLASSIFY_TAXONOMY_NOT_FOUND';
    public const CLASSIFY_TAXONOMY_LOADING_ERROR = 'CLASSIFY_TAXONOMY_LOADING_ERROR';

    // ============================================================================
    // ERREURS DE VALIDATION (VALIDATION_*)
    // ============================================================================
    public const VALIDATION_MISSING_FIELD = 'VALIDATION_MISSING_FIELD';
    public const VALIDATION_INVALID_FORMAT = 'VALIDATION_INVALID_FORMAT';
    public const VALIDATION_REPORT_NOT_FOUND = 'VALIDATION_REPORT_NOT_FOUND';
    public const VALIDATION_QUERY_FAILED = 'VALIDATION_QUERY_FAILED';
    public const VALIDATION_INVALID_DATE_RANGE = 'VALIDATION_INVALID_DATE_RANGE';
    public const VALIDATION_CLEANUP_FAILED = 'VALIDATION_CLEANUP_FAILED';
    public const VALIDATION_INSUFFICIENT_PERMISSIONS = 'VALIDATION_INSUFFICIENT_PERMISSIONS';

    // ============================================================================
    // ERREURS DE CONFIANCE (CONFIDENCE_*)
    // ============================================================================
    public const CONFIDENCE_CALIBRATION_FAILED = 'CONFIDENCE_CALIBRATION_FAILED';
    public const CONFIDENCE_THRESHOLD_ERROR = 'CONFIDENCE_THRESHOLD_ERROR';
    public const CONFIDENCE_CALCULATION_FAILED = 'CONFIDENCE_CALCULATION_FAILED';
    public const CONFIDENCE_INVALID_THRESHOLD = 'CONFIDENCE_INVALID_THRESHOLD';
    public const CONFIDENCE_MISSING_FEATURES = 'CONFIDENCE_MISSING_FEATURES';
    public const CONFIDENCE_VALIDATION_FAILED = 'CONFIDENCE_VALIDATION_FAILED';

    // ============================================================================
    // ERREURS DE MONITORING (MONITOR_*)
    // ============================================================================
    public const MONITOR_SERVICE_UNHEALTHY = 'MONITOR_SERVICE_UNHEALTHY';
    public const MONITOR_TRACE_NOT_FOUND = 'MONITOR_TRACE_NOT_FOUND';
    public const MONITOR_METRICS_UNAVAILABLE = 'MONITOR_METRICS_UNAVAILABLE';
    public const MONITOR_HEALTH_CHECK_FAILED = 'MONITOR_HEALTH_CHECK_FAILED';
    public const MONITOR_ALERT_SEND_FAILED = 'MONITOR_ALERT_SEND_FAILED';

    // ============================================================================
    // ERREURS D'ADMINISTRATION (ADMIN_*)
    // ============================================================================
    public const ADMIN_UNAUTHORIZED = 'ADMIN_UNAUTHORIZED';
    public const ADMIN_INVALID_CONFIG = 'ADMIN_INVALID_CONFIG';
    public const ADMIN_ORGANIZATION_NOT_FOUND = 'ADMIN_ORGANIZATION_NOT_FOUND';
    public const ADMIN_ORGANIZATION_ALREADY_EXISTS = 'ADMIN_ORGANIZATION_ALREADY_EXISTS';
    public const ADMIN_ORGANIZATION_CREATE_FAILED = 'ADMIN_ORGANIZATION_CREATE_FAILED';
    public const ADMIN_ORGANIZATION_UPDATE_FAILED = 'ADMIN_ORGANIZATION_UPDATE_FAILED';
    public const ADMIN_ORGANIZATION_DELETE_FAILED = 'ADMIN_ORGANIZATION_DELETE_FAILED';
    public const ADMIN_CLIENT_NOT_FOUND = 'ADMIN_CLIENT_NOT_FOUND';
    public const ADMIN_CLIENT_LIMIT_EXCEEDED = 'ADMIN_CLIENT_LIMIT_EXCEEDED';
    public const ADMIN_CLIENT_CREATE_FAILED = 'ADMIN_CLIENT_CREATE_FAILED';
    public const ADMIN_CLIENT_DELETE_FAILED = 'ADMIN_CLIENT_DELETE_FAILED';
    public const ADMIN_INVALID_SCOPE = 'ADMIN_INVALID_SCOPE';
    public const ADMIN_SCOPE_NOT_ALLOWED = 'ADMIN_SCOPE_NOT_ALLOWED';

    // ============================================================================
    // ERREURS DE GESTION D'ORGANISATION (ORG_*)
    // ============================================================================
    public const ORG_NOT_FOUND = 'ORG_NOT_FOUND';
    public const ORG_LIMIT_EXCEEDED = 'ORG_LIMIT_EXCEEDED';
    public const ORG_CLIENT_CREATE_FAILED = 'ORG_CLIENT_CREATE_FAILED';
    public const ORG_CLIENT_NOT_FOUND = 'ORG_CLIENT_NOT_FOUND';
    public const ORG_CLIENT_DEACTIVATE_FAILED = 'ORG_CLIENT_DEACTIVATE_FAILED';
    public const ORG_TOKEN_VALIDATION_FAILED = 'ORG_TOKEN_VALIDATION_FAILED';
    public const ORG_TOKEN_NOT_OWNED = 'ORG_TOKEN_NOT_OWNED';
    public const ORG_INFO_RETRIEVAL_FAILED = 'ORG_INFO_RETRIEVAL_FAILED';

    // ============================================================================
    // ERREURS DE VALIDATION DE REQUÊTE (REQUEST_*)
    // ============================================================================
    public const REQUEST_INVALID_PARAMETER = 'REQUEST_INVALID_PARAMETER';
    public const REQUEST_VALIDATION_ERROR = 'REQUEST_VALIDATION_ERROR';
    public const REQUEST_MISSING_FIELD = 'REQUEST_MISSING_FIELD';
    public const REQUEST_INVALID_FORMAT = 'REQUEST_INVALID_FORMAT';
    public const REQUEST_PAYLOAD_TOO_LARGE = 'REQUEST_PAYLOAD_TOO_LARGE';
    public const REQUEST_INVALID_JSON = 'REQUEST_INVALID_JSON';

    // ============================================================================
    // ERREURS SYSTÈME (SYSTEM_*)
    // ============================================================================
    public const SYSTEM_INTERNAL_ERROR = 'SYSTEM_INTERNAL_ERROR';
    public const SYSTEM_SERVICE_UNAVAILABLE = 'SYSTEM_SERVICE_UNAVAILABLE';
    public const SYSTEM_DATABASE_ERROR = 'SYSTEM_DATABASE_ERROR';
    public const SYSTEM_CONFIGURATION_ERROR = 'SYSTEM_CONFIGURATION_ERROR';
    public const SYSTEM_RATE_LIMIT_EXCEEDED = 'SYSTEM_RATE_LIMIT_EXCEEDED';
    public const SYSTEM_TIMEOUT = 'SYSTEM_TIMEOUT';
    public const SYSTEM_RESOURCE_EXHAUSTED = 'SYSTEM_RESOURCE_EXHAUSTED';

    /**
     * Codes d'erreur nécessitant un retry automatique
     */
    public const RETRYABLE_ERRORS = [
        self::INDEX_WEAVIATE_CONNECTION_ERROR,
        self::RAG_LLM_UNAVAILABLE,
        self::RAG_LLM_TIMEOUT,
        self::SYSTEM_SERVICE_UNAVAILABLE,
        self::SYSTEM_TIMEOUT,
    ];

    /**
     * Codes d'erreur critiques nécessitant une alerte ops
     */
    public const CRITICAL_ERRORS = [
        self::SYSTEM_INTERNAL_ERROR,
        self::SYSTEM_DATABASE_ERROR,
        self::INDEX_WEAVIATE_UNAVAILABLE,
        self::AUTH_TENANT_DEACTIVATED,
    ];

    /**
     * Codes d'erreur nécessitant un refresh du token
     */
    public const AUTH_REFRESH_ERRORS = [
        self::AUTH_TOKEN_EXPIRED,
        self::AUTH_TOKEN_INVALID,
    ];

    /**
     * Vérifie si un code d'erreur nécessite un retry
     */
    public static function isRetryable(string $errorCode): bool
    {
        return in_array($errorCode, self::RETRYABLE_ERRORS, true);
    }

    /**
     * Vérifie si un code d'erreur est critique
     */
    public static function isCritical(string $errorCode): bool
    {
        return in_array($errorCode, self::CRITICAL_ERRORS, true);
    }

    /**
     * Vérifie si un code d'erreur nécessite un refresh d'authentification
     */
    public static function needsAuthRefresh(string $errorCode): bool
    {
        return in_array($errorCode, self::AUTH_REFRESH_ERRORS, true);
    }
}
