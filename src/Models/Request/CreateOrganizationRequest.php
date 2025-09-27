<?php

declare(strict_types=1);

namespace Netfield\RagClient\Models\Request;

class CreateOrganizationRequest
{
    private string $name;
    private ?string $description;
    private string $contactEmail;
    private int $maxClients;
    private array $allowedScopes;

    public function __construct(
        string $name,
        string $contactEmail,
        ?string $description = null,
        int $maxClients = 100,
        array $allowedScopes = []
    ) {
        $this->name = $name;
        $this->contactEmail = $contactEmail;
        $this->description = $description;
        $this->maxClients = $maxClients;
        $this->allowedScopes = $allowedScopes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getContactEmail(): string
    {
        return $this->contactEmail;
    }

    public function getMaxClients(): int
    {
        return $this->maxClients;
    }

    public function getAllowedScopes(): array
    {
        return $this->allowedScopes;
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'contact_email' => $this->contactEmail,
            'max_clients' => $this->maxClients,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if (!empty($this->allowedScopes)) {
            $data['allowed_scopes'] = $this->allowedScopes;
        }

        return $data;
    }
}