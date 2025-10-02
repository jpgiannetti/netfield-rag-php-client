<?php

declare(strict_types=1);

namespace Netfield\RagClient\Client;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Trait for extracting clean error messages from Guzzle exceptions
 */
trait ErrorMessageExtractorTrait
{
    /**
     * Extract error message from Guzzle exception
     * Tries to parse the JSON error response from the RAG API
     * Does not include HTTP status code (handled by caller)
     */
    private function extractErrorMessage(GuzzleException $e): string
    {
        // Try to get the response body from the exception
        if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
            $response = $e->getResponse();
            $body = (string) $response->getBody(); // Cast instead of getContents() to avoid consuming stream

            // Try to decode JSON response
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Check for common error message fields (priority order: error > message > detail)
                $errorMessage = $data['error'] ?? $data['message'] ?? $data['detail'] ?? null;

                if ($errorMessage !== null) {
                    return $errorMessage;
                }
            }

            // If no structured error, return body or generic message
            return $body ?: 'Unknown error';
        }

        // Fallback to the original Guzzle message
        return $e->getMessage();
    }
}
