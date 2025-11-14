<?php

declare(strict_types=1);

namespace Netfield\Client\Models\Response;

class ClientTokenResponse
{
    private string $clientId;
    private string $clientName;
    private string $token;
    private int $expiresIn;
    private string $tokenType;
    private array $scopes;
    private array $confidentialityLevels;

    public function __construct(
        string $clientId,
        string $clientName,
        string $token,
        int $expiresIn,
        array $scopes,
        array $confidentialityLevels,
        string $tokenType = 'Bearer'
    ) {
        $this->clientId = $clientId;
        $this->clientName = $clientName;
        $this->token = $token;
        $this->expiresIn = $expiresIn;
        $this->scopes = $scopes;
        $this->confidentialityLevels = $confidentialityLevels;
        $this->tokenType = $tokenType;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getConfidentialityLevels(): array
    {
        return $this->confidentialityLevels;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['client_id'],
            $data['client_name'],
            $data['token'],
            $data['expires_in'],
            $data['scopes'],
            $data['confidentiality_levels'],
            $data['token_type'] ?? 'Bearer'
        );
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
            'token' => $this->token,
            'expires_in' => $this->expiresIn,
            'token_type' => $this->tokenType,
            'scopes' => $this->scopes,
            'confidentiality_levels' => $this->confidentialityLevels,
        ];
    }
}
