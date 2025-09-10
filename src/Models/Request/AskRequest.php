<?php

declare(strict_types=1);

namespace Netfield\RagClient\Models\Request;

use Netfield\RagClient\Exception\RagApiException;

class AskRequest
{
    private string $question;
    private int $limit;
    private ?array $filters;

    public function __construct(string $question, int $limit = 10, ?array $filters = null)
    {
        $this->setQuestion($question);
        $this->setLimit($limit);
        $this->filters = $filters;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): void
    {
        $question = trim($question);
        if (strlen($question) < 3) {
            throw new RagApiException('Question must be at least 3 characters long');
        }
        $this->question = $question;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        if ($limit < 1 || $limit > 50) {
            throw new RagApiException('Limit must be between 1 and 50');
        }
        $this->limit = $limit;
    }

    public function getFilters(): ?array
    {
        return $this->filters;
    }

    public function setFilters(?array $filters): void
    {
        $this->filters = $filters;
    }

    public function toArray(): array
    {
        $data = [
            'question' => $this->question,
            'limit' => $this->limit,
        ];

        if ($this->filters !== null) {
            $data['filters'] = $this->filters;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['question'],
            $data['limit'] ?? 10,
            $data['filters'] ?? null
        );
    }
}
