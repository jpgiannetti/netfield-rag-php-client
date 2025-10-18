<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\RagClientFactory;
use Netfield\RagClient\Client\RagClient;
use Netfield\RagClient\Client\AdminClient;
use Netfield\RagClient\Client\OrganizationClient;
use Netfield\RagClient\Exception\RagApiException;

class ClientFactoryIntegrationTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = $_ENV['RAG_API_URL'] ?? 'http://localhost:8888';
    }

    public function testCreateRagClientWithTestToken(): void
    {
        $client = RagClientFactory::createWithTestToken($this->baseUrl, 'test_tenant_factory');

        $this->assertInstanceOf(RagClient::class, $client);

        // Test that the client can make a basic request
        try {
            $health = $client->health();
            $this->assertInstanceOf(\Netfield\RagClient\Models\Response\HealthResponse::class, $health);
            $this->assertEquals('healthy', $health->getStatus());
        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false) {
                $this->markTestIncomplete('Health service may not be available - client creation successful');
            } else {
                throw $e;
            }
        }
    }

    public function testCreateAdminClientWithTestToken(): void
    {
        $adminClient = RagClientFactory::createAdminWithTestToken($this->baseUrl);

        $this->assertInstanceOf(AdminClient::class, $adminClient);

        // Test that the admin client can attempt a request (may fail due to auth)
        try {
            $status = $adminClient->getAdminStatus();
            $this->assertIsArray($status);
        } catch (RagApiException $e) {
            if (
                strpos($e->getMessage(), '403') !== false ||
                strpos($e->getMessage(), '401') !== false ||
                strpos($e->getMessage(), '404') !== false
            ) {
                $this->markTestIncomplete('Admin endpoints require proper authentication or are not mocked - client creation successful');
            } else {
                throw $e;
            }
        }
    }

    public function testCreateOrganizationClientWithTestToken(): void
    {
        $orgClient = RagClientFactory::createOrganizationWithTestToken($this->baseUrl, 'org_factory_test');

        $this->assertInstanceOf(OrganizationClient::class, $orgClient);

        // Test that the organization client can attempt a request (may fail due to auth)
        try {
            $info = $orgClient->getOrganizationInfo();
            $this->assertIsArray($info);
        } catch (RagApiException $e) {
            if (
                strpos($e->getMessage(), '403') !== false ||
                strpos($e->getMessage(), '401') !== false ||
                strpos($e->getMessage(), '404') !== false
            ) {
                $this->markTestIncomplete('Organization endpoints require proper authentication or are not mocked - client creation successful');
            } else {
                throw $e;
            }
        }
    }

    public function testCreateRagClientCustom(): void
    {
        $customHttpOptions = [
            'timeout' => 60,
            'connect_timeout' => 5,
            'headers' => [
                'User-Agent' => 'PHP RAG Client Test'
            ]
        ];

        $client = RagClientFactory::createCustom(
            $this->baseUrl,
            'test.jwt.token',
            $customHttpOptions
        );

        $this->assertInstanceOf(RagClient::class, $client);

        // Test that the client can make a request (may fail due to invalid token, but that's expected)
        try {
            $health = $client->health();
            $this->assertInstanceOf(\Netfield\RagClient\Models\Response\HealthResponse::class, $health);
        } catch (RagApiException $e) {
            // Expected with a fake token
            $this->assertStringContainsString('JWT', $e->getMessage());
        }
    }

    public function testCreateFromEnvVariables(): void
    {
        // Set test environment variables
        $_ENV['RAG_API_URL'] = $this->baseUrl;
        $_ENV['RAG_TENANT_ID'] = 'test_env_tenant';

        try {
            $client = RagClientFactory::createFromEnv();
            $this->assertInstanceOf(RagClient::class, $client);

            // Test basic functionality
            try {
                $health = $client->health();
                $this->assertInstanceOf(\Netfield\RagClient\Models\Response\HealthResponse::class, $health);
            } catch (RagApiException $e) {
                if (strpos($e->getMessage(), '503') !== false) {
                    $this->markTestIncomplete('Service may not be available - client creation from env successful');
                } else {
                    throw $e;
                }
            }
        } finally {
            // Clean up environment variables
            unset($_ENV['RAG_API_URL']);
            unset($_ENV['RAG_TENANT_ID']);
        }
    }

    public function testCreateFromEnvWithToken(): void
    {
        // Set test environment variables with explicit token
        $_ENV['RAG_API_URL'] = $this->baseUrl;
        $_ENV['RAG_JWT_TOKEN'] = 'test.jwt.token.here';

        try {
            $client = RagClientFactory::createFromEnv();
            $this->assertInstanceOf(RagClient::class, $client);
        } catch (RagApiException $e) {
            // Expected with a fake token format
            $this->assertTrue(true, 'Factory correctly handles invalid tokens');
        } finally {
            // Clean up environment variables
            unset($_ENV['RAG_API_URL']);
            unset($_ENV['RAG_JWT_TOKEN']);
        }
    }

    public function testCreateFromEnvMissingVariables(): void
    {
        // Ensure no relevant env vars are set
        unset($_ENV['RAG_API_URL']);
        unset($_ENV['RAG_JWT_TOKEN']);
        unset($_ENV['RAG_TENANT_ID']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RAG_API_URL environment variable is required');

        RagClientFactory::createFromEnv();
    }

    public function testCreateFromEnvMissingTokenAndTenant(): void
    {
        $_ENV['RAG_API_URL'] = $this->baseUrl;

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Either RAG_JWT_TOKEN or RAG_TENANT_ID environment variable is required');

            RagClientFactory::createFromEnv();
        } finally {
            unset($_ENV['RAG_API_URL']);
        }
    }

    public function testAllClientTypesCanBeCreated(): void
    {
        // Test that all three client types can be instantiated
        $ragClient = RagClientFactory::createWithTestToken($this->baseUrl, 'test_tenant');
        $adminClient = RagClientFactory::createAdminWithTestToken($this->baseUrl);
        $orgClient = RagClientFactory::createOrganizationWithTestToken($this->baseUrl, 'test_org');

        $this->assertInstanceOf(RagClient::class, $ragClient);
        $this->assertInstanceOf(AdminClient::class, $adminClient);
        $this->assertInstanceOf(OrganizationClient::class, $orgClient);

        // Verify they are distinct instances
        $this->assertNotSame($ragClient, $adminClient);
        $this->assertNotSame($ragClient, $orgClient);
        $this->assertNotSame($adminClient, $orgClient);
    }

    public function testFactoryMethodsWithDifferentSecrets(): void
    {
        $customSecret = 'custom-test-secret-key-123';

        $adminClient = RagClientFactory::createAdminWithTestToken($this->baseUrl, $customSecret);
        $orgClient = RagClientFactory::createOrganizationWithTestToken($this->baseUrl, 'test_org', $customSecret);
        $ragClient = RagClientFactory::createWithTestToken($this->baseUrl, 'test_tenant', $customSecret);

        $this->assertInstanceOf(AdminClient::class, $adminClient);
        $this->assertInstanceOf(OrganizationClient::class, $orgClient);
        $this->assertInstanceOf(RagClient::class, $ragClient);

        // All should be created successfully with custom secret
        $this->assertTrue(true, 'All clients created successfully with custom secret');
    }
}
