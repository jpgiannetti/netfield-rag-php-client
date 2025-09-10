<?php

declare(strict_types=1);

namespace Netfield\RagClient\Models\Response;

class AskResponse
{
    private string $status;
    private string $answer;
    private string $question;
    private string $tenantId;
    private array $retrievedDocuments;
    private float $processingTime;
    private ?string $modelUsed;
    private ?float $confidence;
    private ?string $confidenceLevel;
    private ?string $uiMessage;
    private ?array $reliabilityIndicators;
    private ?bool $validationPassed;
    private ?string $searchStrategy;

    public function __construct(
        string $status,
        string $answer,
        string $question,
        string $tenantId,
        array $retrievedDocuments,
        float $processingTime,
        ?string $modelUsed = null,
        ?float $confidence = null,
        ?string $confidenceLevel = null,
        ?string $uiMessage = null,
        ?array $reliabilityIndicators = null,
        ?bool $validationPassed = null,
        ?string $searchStrategy = null
    ) {
        $this->status = $status;
        $this->answer = $answer;
        $this->question = $question;
        $this->tenantId = $tenantId;
        $this->retrievedDocuments = $retrievedDocuments;
        $this->processingTime = $processingTime;
        $this->modelUsed = $modelUsed;
        $this->confidence = $confidence;
        $this->confidenceLevel = $confidenceLevel;
        $this->uiMessage = $uiMessage;
        $this->reliabilityIndicators = $reliabilityIndicators;
        $this->validationPassed = $validationPassed;
        $this->searchStrategy = $searchStrategy;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getRetrievedDocuments(): array
    {
        return $this->retrievedDocuments;
    }

    public function getProcessingTime(): float
    {
        return $this->processingTime;
    }

    public function getModelUsed(): ?string
    {
        return $this->modelUsed;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function getConfidenceLevel(): ?string
    {
        return $this->confidenceLevel;
    }

    public function getUiMessage(): ?string
    {
        return $this->uiMessage;
    }

    public function getReliabilityIndicators(): ?array
    {
        return $this->reliabilityIndicators;
    }

    public function isValidationPassed(): ?bool
    {
        return $this->validationPassed;
    }

    public function getSearchStrategy(): ?string
    {
        return $this->searchStrategy;
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function hasHighConfidence(): bool
    {
        return $this->confidenceLevel === 'very_high' || $this->confidenceLevel === 'high';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['status'],
            $data['answer'],
            $data['question'],
            $data['tenant_id'],
            $data['retrieved_documents'] ?? [],
            $data['processing_time'],
            $data['model_used'] ?? null,
            $data['confidence'] ?? null,
            $data['confidence_level'] ?? null,
            $data['ui_message'] ?? null,
            $data['reliability_indicators'] ?? null,
            $data['validation_passed'] ?? null,
            $data['search_strategy'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [
            'status' => $this->status,
            'answer' => $this->answer,
            'question' => $this->question,
            'tenant_id' => $this->tenantId,
            'retrieved_documents' => $this->retrievedDocuments,
            'processing_time' => $this->processingTime,
        ];

        if ($this->modelUsed !== null) {
            $data['model_used'] = $this->modelUsed;
        }

        if ($this->confidence !== null) {
            $data['confidence'] = $this->confidence;
        }

        if ($this->confidenceLevel !== null) {
            $data['confidence_level'] = $this->confidenceLevel;
        }

        if ($this->uiMessage !== null) {
            $data['ui_message'] = $this->uiMessage;
        }

        if ($this->reliabilityIndicators !== null) {
            $data['reliability_indicators'] = $this->reliabilityIndicators;
        }

        if ($this->validationPassed !== null) {
            $data['validation_passed'] = $this->validationPassed;
        }

        if ($this->searchStrategy !== null) {
            $data['search_strategy'] = $this->searchStrategy;
        }

        return $data;
    }
}
