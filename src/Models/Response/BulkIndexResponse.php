<?php

declare(strict_types=1);

namespace Netfield\Client\Models\Response;

class BulkIndexResponse
{
    private string $status;
    private int $totalDocuments;
    private int $indexedSuccessfully;
    private array $errors;
    private ?float $processingTime;
    private string $tenantId;

    public function __construct(
        string $status,
        int $totalDocuments,
        int $indexedSuccessfully,
        array $errors,
        string $tenantId,
        ?float $processingTime = null
    ) {
        $this->status = $status;
        $this->totalDocuments = $totalDocuments;
        $this->indexedSuccessfully = $indexedSuccessfully;
        $this->errors = $errors;
        $this->tenantId = $tenantId;
        $this->processingTime = $processingTime;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotalDocuments(): int
    {
        return $this->totalDocuments;
    }

    public function getIndexedSuccessfully(): int
    {
        return $this->indexedSuccessfully;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getProcessingTime(): ?float
    {
        return $this->processingTime;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getSuccessRate(): float
    {
        if ($this->totalDocuments === 0) {
            return 0.0;
        }
        return ($this->indexedSuccessfully / $this->totalDocuments) * 100;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function isFullySuccessful(): bool
    {
        return $this->status === 'success' && $this->indexedSuccessfully === $this->totalDocuments;
    }

    public function isPartiallySuccessful(): bool
    {
        return $this->indexedSuccessfully > 0 && $this->indexedSuccessfully < $this->totalDocuments;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['status'],
            (int)$data['total_documents'],
            (int)$data['indexed_successfully'],
            $data['errors'] ?? [],
            $data['tenant_id'],
            isset($data['processing_time']) ? (float)$data['processing_time'] : null
        );
    }

    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
            'total_documents' => $this->totalDocuments,
            'indexed_successfully' => $this->indexedSuccessfully,
            'errors' => $this->errors,
            'tenant_id' => $this->tenantId,
        ];

        if ($this->processingTime !== null) {
            $data['processing_time'] = $this->processingTime;
        }

        return $data;
    }
}
