<?php

declare(strict_types=1);

namespace RagApi\PhpClient\Exception;

use Exception;

class RagApiException extends Exception
{
    protected ?array $context;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?array $context = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}
