<?php

declare(strict_types=1);

namespace Netfield\Client\Client;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Trait for extracting error information from Guzzle exceptions
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
        $errorData = $this->extractErrorData($e);

        // Return the message from the structured error data
        if ($errorData !== null && isset($errorData['message'])) {
            return $errorData['message'];
        }

        // Try to get the response body from the exception
        if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
            $response = $e->getResponse();
            $body = (string) $response->getBody();

            // Try to decode JSON response
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Check for common error message fields (priority order: error > message > detail)
                $errorMessage = $data['error'] ?? $data['message'] ?? $data['detail'] ?? null;

                if ($errorMessage !== null) {
                    return is_string($errorMessage) ? $errorMessage : json_encode($errorMessage);
                }
            }

            // If no structured error, return body or generic message
            return $body ?: 'Unknown error';
        }

        // Fallback to the original Guzzle message
        return $e->getMessage();
    }

    /**
     * Extract complete error data from Guzzle exception
     * Returns the standardized error response structure from the RAG API
     *
     * @return array{error_code?: string, message?: string, details?: array, field?: string, timestamp?: string, trace_id?: string}|null
     */
    private function extractErrorData(GuzzleException $e): ?array
    {
        // Try to get the response body from the exception
        if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
            $response = $e->getResponse();
            $body = (string) $response->getBody();

            // Try to decode JSON response
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // Check if this is a standardized error response (has error_code)
                if (isset($data['error_code'])) {
                    return [
                        'error_code' => $data['error_code'],
                        'message' => $data['message'] ?? 'Unknown error',
                        'details' => $data['details'] ?? null,
                        'field' => $data['field'] ?? null,
                        'timestamp' => $data['timestamp'] ?? null,
                        'trace_id' => $data['trace_id'] ?? null,
                    ];
                }

                // Legacy format - try to extract what we can
                $message = $data['error'] ?? $data['message'] ?? $data['detail'] ?? null;
                if ($message !== null) {
                    return [
                        'message' => is_string($message) ? $message : json_encode($message),
                        'details' => $data['details'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extract error code from Guzzle exception
     * Returns the standardized error code (ex: AUTH_TOKEN_EXPIRED)
     */
    private function extractErrorCode(GuzzleException $e): ?string
    {
        $errorData = $this->extractErrorData($e);
        return $errorData['error_code'] ?? null;
    }
}
