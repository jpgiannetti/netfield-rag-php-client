<?php

declare(strict_types=1);

namespace Netfield\RagClient\Models\Request;

use Netfield\RagClient\Exception\RagApiException;

class BulkIndexRequest
{
    private array $documents;

    /**
     * Le tenant_id est automatiquement extrait du JWT - ne pas le fournir dans la requête
     *
     * @param array $documents Liste des documents à indexer (max 100)
     */
    public function __construct(array $documents = [])
    {
        $this->setDocuments($documents);
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
            'documents' => array_map(fn($doc) => $doc->toArray(), $this->documents),
        ];
    }

    public static function fromArray(array $data): self
    {
        $documents = [];
        foreach ($data['documents'] as $documentData) {
            $documents[] = IndexDocumentRequest::fromArray($documentData);
        }

        return new self($documents);
    }
}
