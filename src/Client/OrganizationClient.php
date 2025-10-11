<?php

declare(strict_types=1);

namespace Netfield\RagClient\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Netfield\RagClient\Auth\JwtAuthenticator;
use Netfield\RagClient\Exception\RagApiException;
use Netfield\RagClient\Models\Request\CreateClientTokenRequest;
use Netfield\RagClient\Models\Response\ClientTokenResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Organization client for managing client tokens
 */
class OrganizationClient
{
    private Client $httpClient;
    private JwtAuthenticator $authenticator;
    private LoggerInterface $logger;
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        string $organizationJwtToken,
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
        $this->authenticator = new JwtAuthenticator($organizationJwtToken);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Create a new client token
     */
    public function createClientToken(CreateClientTokenRequest $request): ClientTokenResponse
    {
        try {
            $this->logger->info('Creating client token', ['client_name' => $request->getClientName()]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/organization/clients', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return ClientTokenResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $exception = RagApiException::fromGuzzleException($e, 'Failed to create client token');
            $this->logger->error('Failed to create client token', [
                'error' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode()
            ]);
            throw $exception;
        }
    }

    /**
     * List my clients
     */
    public function listMyClients(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/organization/clients', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $exception = RagApiException::fromGuzzleException($e, 'Failed to list clients');
            $this->logger->error('Failed to list clients', [
                'error' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode()
            ]);
            throw $exception;
        }
    }

    /**
     * Deactivate a client
     */
    public function deactivateClient(string $clientId): array
    {
        try {
            $this->logger->info('Deactivating client', ['client_id' => $clientId]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/organization/clients/' . urlencode($clientId) . '/deactivate', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $exception = RagApiException::fromGuzzleException($e, 'Failed to deactivate client');
            $this->logger->error('Failed to deactivate client', [
                'error' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode()
            ]);
            throw $exception;
        }
    }

    /**
     * Get organization info
     */
    public function getOrganizationInfo(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/organization/info', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $exception = RagApiException::fromGuzzleException($e, 'Failed to get organization info');
            $this->logger->error('Failed to get organization info', [
                'error' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode()
            ]);
            throw $exception;
        }
    }

    /**
     * Validate a client token
     */
    public function validateClientToken(array $tokenData): array
    {
        try {
            $response = $this->httpClient->post($this->baseUrl . '/api/v1/organization/validate-token', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $tokenData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $exception = RagApiException::fromGuzzleException($e, 'Failed to validate client token');
            $this->logger->error('Failed to validate client token', [
                'error' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode()
            ]);
            throw $exception;
        }
    }
}
