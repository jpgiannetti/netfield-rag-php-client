<?php

declare(strict_types=1);

namespace RagApi\PhpClient\Models\Request;

use RagApi\PhpClient\Exception\RagApiException;

class BulkIndexRequest
{
    private string $tenantId;
    private array $documents;

    public function __construct(string $tenantId, array $documents = [])
    {
        $this->setTenantId($tenantId);
        $this->setDocuments($documents);
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): void
    {
        $tenantId = trim((string)$tenantId);
        if (empty($tenantId)) {
            throw new RagApiException('tenant_id is required');
        }
        $this->tenantId = $tenantId;
    }

    /**
     * @return IndexDocumentRequest[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param IndexDocumentRequest[] $documents
     */
    public function setDocuments(array $documents): void
    {
        if (empty($documents)) {
            throw new RagApiException('Documents list cannot be empty');
        }

        if (count($documents) > 100) {
            throw new RagApiException('Maximum 100 documents per batch');
        }

        foreach ($documents as $document) {
            if (!$document instanceof IndexDocumentRequest) {
                throw new RagApiException('All documents must be IndexDocumentRequest instances');
            }
        }

        $this->documents = $documents;
    }

    public function addDocument(IndexDocumentRequest $document): void
    {
        if (count($this->documents) >= 100) {
            throw new RagApiException('Maximum 100 documents per batch');
        }

        $this->documents[] = $document;
    }

    public function removeDocument(int $index): void
    {
        if (isset($this->documents[$index])) {
            unset($this->documents[$index]);
            $this->documents = array_values($this->documents); // Re-index array
        }
    }

    public function getDocumentCount(): int
    {
        return count($this->documents);
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'documents' => array_map(fn($doc) => $doc->toArray(), $this->documents),
        ];
    }

    public static function fromArray(array $data): self
    {
        $documents = [];
        foreach ($data['documents'] as $documentData) {
            $documents[] = IndexDocumentRequest::fromArray($documentData);
        }

        return new self($data['tenant_id'], $documents);
    }
}
