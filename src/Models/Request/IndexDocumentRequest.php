<?php

declare(strict_types=1);

namespace Netfield\RagClient\Models\Request;

use Netfield\RagClient\Exception\RagApiException;

class IndexDocumentRequest
{
    private string $documentId;
    private ?string $tenantId;
    private ?string $content;
    private DocumentInfo $documentInfo;
    private ?array $metadata;

    /**
     * @param string $documentId
     * @param string|null $tenantId Optionnel - sera extrait du JWT si null
     * @param DocumentInfo $documentInfo
     * @param string|null $content
     * @param array|null $metadata
     */
    public function __construct(
        string $documentId,
        ?string $tenantId = null,
        DocumentInfo $documentInfo = null,
        ?string $content = null,
        ?array $metadata = null
    ) {
        $this->setDocumentId($documentId);
        $this->setTenantId($tenantId);
        $this->content = $content;
        $this->documentInfo = $documentInfo;
        $this->metadata = $metadata;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function setDocumentId(string $documentId): void
    {
        $documentId = trim((string)$documentId);
        if (empty($documentId)) {
            throw new RagApiException('document_id is required');
        }
        $this->documentId = $documentId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        if ($tenantId !== null) {
            $tenantId = trim($tenantId);
            if (empty($tenantId)) {
                $tenantId = null;
            }
        }
        $this->tenantId = $tenantId;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getDocumentInfo(): DocumentInfo
    {
        return $this->documentInfo;
    }

    public function setDocumentInfo(DocumentInfo $documentInfo): void
    {
        $this->documentInfo = $documentInfo;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function toArray(): array
    {
        $data = [
            'document_id' => $this->documentId,
            'document_info' => $this->documentInfo->toArray(),
        ];

        // N'inclure tenant_id que s'il est défini (pour compatibilité ascendante)
        if ($this->tenantId !== null) {
            $data['tenant_id'] = $this->tenantId;
        }

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $documentInfo = DocumentInfo::fromArray($data['document_info']);

        return new self(
            $data['document_id'],
            $data['tenant_id'] ?? null,
            $documentInfo,
            $data['content'] ?? null,
            $data['metadata'] ?? null
        );
    }
}
