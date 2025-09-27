<?php

declare(strict_types=1);

namespace Netfield\RagClient\Models\Request;

class CreateClientTokenRequest
{
    private string $clientName;
    private ?string $clientDescription;
    private array $scopes;
    private array $confidentialityLevels;
    private int $expiresInDays;
    private ?array $metadata;

    public function __construct(
        string $clientName,
        array $scopes,
        array $confidentialityLevels = [],
        ?string $clientDescription = null,
        int $expiresInDays = 365,
        ?array $metadata = null
    ) {
        $this->clientName = $clientName;
        $this->scopes = $scopes;
        $this->confidentialityLevels = $confidentialityLevels;
        $this->clientDescription = $clientDescription;
        $this->expiresInDays = $expiresInDays;
        $this->metadata = $metadata;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function getClientDescription(): ?string
    {
        return $this->clientDescription;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getConfidentialityLevels(): array
    {
        return $this->confidentialityLevels;
    }

    public function getExpiresInDays(): int
    {
        return $this->expiresInDays;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        $data = [
            'client_name' => $this->clientName,
            'scopes' => $this->scopes,
            'expires_in_days' => $this->expiresInDays,
        ];

        if ($this->clientDescription !== null) {
            $data['client_description'] = $this->clientDescription;
        }

        if (!empty($this->confidentialityLevels)) {
            $data['confidentiality_levels'] = $this->confidentialityLevels;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}