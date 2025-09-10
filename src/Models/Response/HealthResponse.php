<?php

declare(strict_types=1);

namespace RagApi\PhpClient\Models\Response;

class HealthResponse
{
    private string $status;
    private ?array $details;

    public function __construct(string $status, ?array $details = null)
    {
        $this->status = $status;
        $this->details = $details;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function isHealthy(): bool
    {
        return $this->status === 'healthy' || $this->status === 'ok';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['status'],
            $data['details'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
        ];

        if ($this->details !== null) {
            $data['details'] = $this->details;
        }

        return $data;
    }
}
