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
    use ErrorMessageExtractorTrait;

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
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to create client token', ['error' => $errorMessage]);
            throw new RagApiException('Failed to create client token: ' . $errorMessage, $e->getCode(), $e);
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
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to list clients', ['error' => $errorMessage]);
            throw new RagApiException('Failed to list clients: ' . $errorMessage, $e->getCode(), $e);
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
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to deactivate client', ['error' => $errorMessage]);
            throw new RagApiException('Failed to deactivate client: ' . $errorMessage, $e->getCode(), $e);
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
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to get organization info', ['error' => $errorMessage]);
            throw new RagApiException('Failed to get organization info: ' . $errorMessage, $e->getCode(), $e);
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
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to validate client token', ['error' => $errorMessage]);
            throw new RagApiException('Failed to validate client token: ' . $errorMessage, $e->getCode(), $e);
        }
    }
}