<?php

declare(strict_types=1);

namespace RagApi\PhpClient\Models\Response;

class IndexResponse
{
    private string $status;
    private ?string $message;
    private ?string $documentId;
    private ?float $processingTime;

    public function __construct(
        string $status,
        ?string $message = null,
        ?string $documentId = null,
        ?float $processingTime = null
    ) {
        $this->status = $status;
        $this->message = $message;
        $this->documentId = $documentId;
        $this->processingTime = $processingTime;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getDocumentId(): ?string
    {
        return $this->documentId;
    }

    public function getProcessingTime(): ?float
    {
        return $this->processingTime;
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['status'],
            $data['message'] ?? null,
            $data['document_id'] ?? null,
            $data['processing_time'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
        ];

        if ($this->message !== null) {
            $data['message'] = $this->message;
        }

        if ($this->documentId !== null) {
            $data['document_id'] = $this->documentId;
        }

        if ($this->processingTime !== null) {
            $data['processing_time'] = $this->processingTime;
        }

        return $data;
    }
}
