<?php

declare(strict_types=1);

namespace Netfield\RagClient\Exception;

use Exception;

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
}
