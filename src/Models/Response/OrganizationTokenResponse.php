<?php

declare(strict_types=1);

namespace Netfield\Client\Models\Response;

class OrganizationTokenResponse
{
    private string $organizationId;
    private string $token;
    private int $expiresIn;
    private string $tokenType;

    public function __construct(string $organizationId, string $token, int $expiresIn, string $tokenType = 'Bearer')
    {
        $this->organizationId = $organizationId;
        $this->token = $token;
        $this->expiresIn = $expiresIn;
        $this->tokenType = $tokenType;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
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

    public static function fromArray(array $data): self
    {
        return new self(
            $data['organization_id'],
            $data['token'],
            $data['expires_in'],
            $data['token_type'] ?? 'Bearer'
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'token' => $this->token,
            'expires_in' => $this->expiresIn,
            'token_type' => $this->tokenType,
        ];
    }
}
