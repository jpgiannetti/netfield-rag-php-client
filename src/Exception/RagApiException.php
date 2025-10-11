<?php

declare(strict_types=1);

namespace Netfield\RagClient\Exception;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class RagApiException extends Exception
{
    protected ?array $context;
    private ?string $errorCode;
    private ?array $errorData;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $context = null,
        ?string $errorCode = null,
        ?array $errorData = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->errorCode = $errorCode;
        $this->errorData = $errorData;
    }

    /**
     * Crée une RagApiException depuis une GuzzleException
     * Extrait automatiquement le error_code, message et toutes les données de l'API
     *
     * @param GuzzleException $e L'exception Guzzle à wrapper
     * @param string|null $contextMessage Message de contexte optionnel (ex: "Failed to create client token")
     * @return self
     */
    public static function fromGuzzleException(
        GuzzleException $e,
        ?string $contextMessage = null
    ): self {
        $errorData = self::extractErrorDataFromGuzzle($e);
        $errorCode = $errorData['error_code'] ?? null;
        $apiMessage = $errorData['message'] ?? $e->getMessage();

        // Construire le message final
        $finalMessage = $contextMessage
            ? $contextMessage . ': ' . $apiMessage
            : $apiMessage;

        return new self(
            message: $finalMessage,
            code: $e->getCode(),
            previous: $e,
            context: null,
            errorCode: $errorCode,
            errorData: $errorData
        );
    }

    /**
     * Extrait les données d'erreur structurées depuis une GuzzleException
     */
    private static function extractErrorDataFromGuzzle(GuzzleException $e): ?array
    {
        if (!method_exists($e, 'getResponse') || $e->getResponse() === null) {
            return null;
        }

        $response = $e->getResponse();
        $body = (string) $response->getBody();

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        // Format standardisé de l'API RAG
        if (isset($data['error_code'])) {
            return [
                'error_code' => $data['error_code'],
                'message' => $data['message'] ?? 'Unknown error',
                'details' => $data['details'] ?? null,
                'field' => $data['field'] ?? null,
                'timestamp' => $data['timestamp'] ?? null,
                'trace_id' => $data['trace_id'] ?? null,
            ];
        }

        // Format legacy - essayer d'extraire ce qu'on peut
        $message = $data['error'] ?? $data['message'] ?? $data['detail'] ?? null;
        if ($message !== null) {
            return [
                'message' => is_string($message) ? $message : json_encode($message),
                'details' => $data['details'] ?? null,
            ];
        }

        return null;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * Retourne le code d'erreur standardisé de l'API (ex: AUTH_TOKEN_EXPIRED)
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Retourne les données complètes de l'erreur de l'API
     * Inclut: error_code, message, details, field, timestamp, trace_id
     */
    public function getErrorData(): ?array
    {
        return $this->errorData;
    }

    /**
     * Retourne les détails supplémentaires de l'erreur (si présents)
     */
    public function getDetails(): ?array
    {
        return $this->errorData['details'] ?? null;
    }

    /**
     * Retourne le champ concerné par l'erreur (si présent)
     */
    public function getField(): ?string
    {
        return $this->errorData['field'] ?? null;
    }

    /**
     * Retourne le timestamp de l'erreur (si présent)
     */
    public function getTimestamp(): ?string
    {
        return $this->errorData['timestamp'] ?? null;
    }

    /**
     * Retourne l'ID de trace pour le debugging (si présent)
     */
    public function getTraceId(): ?string
    {
        return $this->errorData['trace_id'] ?? null;
    }

    /**
     * Vérifie si l'erreur nécessite un retry automatique
     */
    public function isRetryable(): bool
    {
        return $this->errorCode && ErrorCode::isRetryable($this->errorCode);
    }

    /**
     * Vérifie si l'erreur est critique
     */
    public function isCritical(): bool
    {
        return $this->errorCode && ErrorCode::isCritical($this->errorCode);
    }

    /**
     * Vérifie si l'erreur nécessite un refresh du token
     */
    public function needsAuthRefresh(): bool
    {
        return $this->errorCode && ErrorCode::needsAuthRefresh($this->errorCode);
    }

    /**
     * Convertit l'exception en tableau pour sérialisation JSON
     * Format prêt pour le front-end avec toutes les informations structurées
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode ?? 'UNKNOWN_ERROR',
            'message' => $this->getMessage(),
            'details' => $this->getDetails(),
            'field' => $this->getField(),
            'timestamp' => $this->getTimestamp(),
            'trace_id' => $this->getTraceId(),
            'http_status' => $this->getCode(),
            'is_retryable' => $this->isRetryable(),
            'is_critical' => $this->isCritical(),
            'needs_auth_refresh' => $this->needsAuthRefresh(),
        ];
    }
}
