<?php

declare(strict_types=1);

namespace Netfield\Client\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Netfield\Client\Auth\JwtAuthenticator;
use Netfield\Client\Exception\NetfieldApiException;
use Netfield\Client\Models\Request\BulkIndexRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ValidationClient
{
    use ErrorMessageExtractorTrait;

    private Client $httpClient;
    private JwtAuthenticator $authenticator;
    private LoggerInterface $logger;
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        string $jwtToken,
        ?Client $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
        $this->authenticator = new JwtAuthenticator($jwtToken);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Valide des documents sans les indexer (dry-run)
     */
    public function validateDocuments(BulkIndexRequest $request): array
    {
        try {
            $this->logger->info('Validating documents', ['count' => count($request->getDocuments())]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/index/validate', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document validation failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to validate documents: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère le rapport de validation d'un document
     */
    public function getDocumentValidationReport(string $documentId): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/validation/report/' . urlencode($documentId), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get document validation report: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère un résumé des validations
     */
    public function getValidationSummary(int $daysBack = 30): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/validation/summary?days_back=' . $daysBack, [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get validation summary: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Recherche les documents avec erreurs de validation
     */
    public function queryValidationReports(array $filters = []): array
    {
        try {
            $this->logger->info('Querying validation reports', ['filters' => $filters]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/validation/query', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $filters,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Validation query failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to query validation reports: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère les erreurs par champ
     */
    public function getErrorsByField(?string $docType = null, int $limit = 10): array
    {
        try {
            $queryParams = ['limit' => $limit];
            if ($docType !== null) {
                $queryParams['doc_type'] = $docType;
            }

            $response = $this->httpClient->get($this->baseUrl . '/api/v1/validation/errors/by-field?' . http_build_query($queryParams), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get errors by field: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Nettoie les anciens rapports de validation (admin uniquement)
     */
    public function cleanupOldReports(int $daysToKeep = 90): array
    {
        try {
            $this->logger->info('Cleaning up old validation reports', ['days_to_keep' => $daysToKeep]);

            $response = $this->httpClient->delete($this->baseUrl . '/api/v1/validation/cleanup?days_to_keep=' . $daysToKeep, [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Cleanup failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to cleanup old reports: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }
}
