<?php

declare(strict_types=1);

namespace Netfield\RagClient\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Netfield\RagClient\Auth\JwtAuthenticator;
use Netfield\RagClient\Exception\RagApiException;
use Netfield\RagClient\Models\Request\CreateOrganizationRequest;
use Netfield\RagClient\Models\Response\OrganizationTokenResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Admin client for managing organizations and system administration
 */
class AdminClient
{
    use ErrorMessageExtractorTrait;

    private Client $httpClient;
    private JwtAuthenticator $authenticator;
    private LoggerInterface $logger;
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        string $adminJwtToken,
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
        $this->authenticator = new JwtAuthenticator($adminJwtToken);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Create a new organization
     */
    public function createOrganization(CreateOrganizationRequest $request): OrganizationTokenResponse
    {
        try {
            $this->logger->info('Creating organization', ['name' => $request->getName()]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/admin/organizations', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return OrganizationTokenResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to create organization', ['error' => $errorMessage]);
            throw new RagApiException('Failed to create organization: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * List all organizations
     */
    public function listOrganizations(?string $search = null, ?string $orgStatus = null): array
    {
        try {
            $queryParams = [];
            if ($search !== null) {
                $queryParams['search'] = $search;
            }
            if ($orgStatus !== null) {
                $queryParams['org_status'] = $orgStatus;
            }

            $url = $this->baseUrl . '/api/v1/admin/organizations';
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $response = $this->httpClient->get($url, [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to list organizations', ['error' => $errorMessage]);
            throw new RagApiException('Failed to list organizations: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Update an organization
     */
    public function updateOrganization(string $organizationId, array $updateData): array
    {
        try {
            $this->logger->info('Updating organization', ['organization_id' => $organizationId]);

            $response = $this->httpClient->put($this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId), [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $updateData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to update organization', ['error' => $errorMessage]);
            throw new RagApiException('Failed to update organization: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Delete an organization
     */
    public function deleteOrganization(string $organizationId, bool $force = false): array
    {
        try {
            $this->logger->info('Deleting organization', ['organization_id' => $organizationId, 'force' => $force]);

            $url = $this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId);
            if ($force) {
                $url .= '?force=true';
            }

            $response = $this->httpClient->delete($url, [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to delete organization', ['error' => $errorMessage]);
            throw new RagApiException('Failed to delete organization: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Deactivate an organization
     */
    public function deactivateOrganization(string $organizationId): array
    {
        try {
            $this->logger->info('Deactivating organization', ['organization_id' => $organizationId]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId) . '/deactivate', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to deactivate organization', ['error' => $errorMessage]);
            throw new RagApiException('Failed to deactivate organization: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Reactivate an organization
     */
    public function reactivateOrganization(string $organizationId): array
    {
        try {
            $this->logger->info('Reactivating organization', ['organization_id' => $organizationId]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId) . '/reactivate', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to reactivate organization', ['error' => $errorMessage]);
            throw new RagApiException('Failed to reactivate organization: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * List clients of an organization
     */
    public function listOrganizationClients(string $organizationId): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId) . '/clients', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to list organization clients', ['error' => $errorMessage]);
            throw new RagApiException('Failed to list organization clients: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Deactivate a client
     */
    public function deactivateClient(string $organizationId, string $clientId): array
    {
        try {
            $this->logger->info('Deactivating client', ['organization_id' => $organizationId, 'client_id' => $clientId]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId) . '/clients/' . urlencode($clientId) . '/deactivate', [
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
     * Reactivate a client
     */
    public function reactivateClient(string $organizationId, string $clientId): array
    {
        try {
            $this->logger->info('Reactivating client', ['organization_id' => $organizationId, 'client_id' => $clientId]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId) . '/clients/' . urlencode($clientId) . '/reactivate', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to reactivate client', ['error' => $errorMessage]);
            throw new RagApiException('Failed to reactivate client: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Delete a client
     */
    public function deleteClient(string $organizationId, string $clientId): array
    {
        try {
            $this->logger->info('Deleting client', ['organization_id' => $organizationId, 'client_id' => $clientId]);

            $response = $this->httpClient->delete($this->baseUrl . '/api/v1/admin/organizations/' . urlencode($organizationId) . '/clients/' . urlencode($clientId), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to delete client', ['error' => $errorMessage]);
            throw new RagApiException('Failed to delete client: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Get admin system status
     */
    public function getAdminStatus(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/admin/status', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Failed to get admin status', ['error' => $errorMessage]);
            throw new RagApiException('Failed to get admin status: ' . $errorMessage, $e->getCode(), $e);
        }
    }
}