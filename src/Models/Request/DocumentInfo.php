<?php

declare(strict_types=1);

namespace Netfield\RagClient\Models\Request;

class DocumentInfo
{
    private string $title;
    private string $creationDate;
    private ?int $revision;
    private ?bool $final;
    private ?int $nbPages;
    private ?string $hashMd5;
    private ?string $hashSha1;

    public function __construct(
        string $title,
        string $creationDate,
        ?int $revision = null,
        ?bool $final = null,
        ?int $nbPages = null,
        ?string $hashMd5 = null,
        ?string $hashSha1 = null
    ) {
        $this->title = $title;
        $this->creationDate = $creationDate;
        $this->revision = $revision;
        $this->final = $final;
        $this->nbPages = $nbPages;
        $this->hashMd5 = $hashMd5;
        $this->hashSha1 = $hashSha1;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public function setCreationDate(string $creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    public function getRevision(): ?int
    {
        return $this->revision;
    }

    public function setRevision(?int $revision): void
    {
        $this->revision = $revision;
    }

    public function isFinal(): ?bool
    {
        return $this->final;
    }

    public function setFinal(?bool $final): void
    {
        $this->final = $final;
    }

    public function getNbPages(): ?int
    {
        return $this->nbPages;
    }

    public function setNbPages(?int $nbPages): void
    {
        $this->nbPages = $nbPages;
    }

    public function getHashMd5(): ?string
    {
        return $this->hashMd5;
    }

    public function setHashMd5(?string $hashMd5): void
    {
        $this->hashMd5 = $hashMd5;
    }

    public function getHashSha1(): ?string
    {
        return $this->hashSha1;
    }

    public function setHashSha1(?string $hashSha1): void
    {
        $this->hashSha1 = $hashSha1;
    }

    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'creation_date' => $this->creationDate,
        ];

        if ($this->revision !== null) {
            $data['revision'] = $this->revision;
        }

        if ($this->final !== null) {
            $data['final'] = $this->final;
        }

        if ($this->nbPages !== null) {
            $data['nb_pages'] = $this->nbPages;
        }

        if ($this->hashMd5 !== null) {
            $data['hash_md5'] = $this->hashMd5;
        }

        if ($this->hashSha1 !== null) {
            $data['hash_sha1'] = $this->hashSha1;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['title'],
            $data['creation_date'],
            $data['revision'] ?? null,
            $data['final'] ?? null,
            $data['nb_pages'] ?? null,
            $data['hash_md5'] ?? null,
            $data['hash_sha1'] ?? null
        );
    }
}
