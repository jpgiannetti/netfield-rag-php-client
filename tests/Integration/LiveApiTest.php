<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\RagClientFactory;
use Netfield\RagClient\Client\RagClient;
use Netfield\RagClient\Client\AdminClient;
use Netfield\RagClient\Client\OrganizationClient;
use Netfield\RagClient\Models\Request\CreateOrganizationRequest;
use Netfield\RagClient\Models\Request\CreateClientTokenRequest;
use Netfield\RagClient\Exception\RagApiException;

/**
 * Live API tests against running localhost:8888
 * These tests verify that the client can communicate with the real API
 */
class LiveApiTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = 'http://localhost:8888';
    }

    public function testBasicHealthCheck(): void
    {
        $client = RagClientFactory::createWithTestToken($this->baseUrl, 'test_live_api');

        try {
            $health = $client->health();

            $this->assertNotNull($health);
            $this->assertEquals('healthy', $health->getStatus());

            echo "\n✅ Basic health check successful\n";

        } catch (RagApiException $e) {
            $this->markTestSkipped('API not available at ' . $this->baseUrl . ': ' . $e->getMessage());
        }
    }

    public function testNewEndpointsStructure(): void
    {
        $client = RagClientFactory::createWithTestToken($this->baseUrl, 'test_live_endpoints');

        try {
            // Test confidence thresholds
            $thresholds = $client->getConfidenceThresholds();
            $this->assertIsArray($thresholds);
            echo "\n✅ Confidence thresholds endpoint accessible\n";

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                $this->markTestSkipped('Confidence endpoints not available: ' . $e->getMessage());
            }
        }

        try {
            // Test available models
            $models = $client->getAvailableModels();
            $this->assertIsArray($models);
            echo "\n✅ Available models endpoint accessible\n";

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                $this->markTestSkipped('Models endpoint not available: ' . $e->getMessage());
            }
        }

        try {
            // Test classification taxonomy
            $taxonomy = $client->getTaxonomyInfo();
            $this->assertIsArray($taxonomy);
            echo "\n✅ Taxonomy info endpoint accessible\n";

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                $this->markTestSkipped('Taxonomy endpoint not available: ' . $e->getMessage());
            }
        }
    }

    public function testTokenGenerationAndStructure(): void
    {
        // Test admin token generation
        $adminClient = RagClientFactory::createAdminWithTestToken($this->baseUrl);
        $this->assertInstanceOf(AdminClient::class, $adminClient);

        $adminRequest = new CreateOrganizationRequest(
            'Test Organization Live',
            'test-live@example.com',
            'Live API test organization'
        );

        $this->assertIsArray($adminRequest->toArray());
        echo "\n✅ Admin client and request structures valid\n";

        // Test organization token generation
        $orgClient = RagClientFactory::createOrganizationWithTestToken($this->baseUrl, 'org_live_test');
        $this->assertInstanceOf(OrganizationClient::class, $orgClient);

        $clientRequest = new CreateClientTokenRequest(
            'Live Test Client',
            ['read', 'write']
        );

        $this->assertIsArray($clientRequest->toArray());
        echo "\n✅ Organization client and request structures valid\n";
    }

    public function testJwtTokenStructures(): void
    {
        // Test different JWT token types
        $adminToken = \Netfield\RagClient\Auth\JwtAuthenticator::generateAdminTestToken();
        $orgToken = \Netfield\RagClient\Auth\JwtAuthenticator::generateOrganizationTestToken('test_org');
        $clientToken = \Netfield\RagClient\Auth\JwtAuthenticator::generateTestToken('test_client');

        $this->assertIsString($adminToken);
        $this->assertIsString($orgToken);
        $this->assertIsString($clientToken);

        // Verify token format (3 parts separated by dots)
        $this->assertCount(3, explode('.', $adminToken));
        $this->assertCount(3, explode('.', $orgToken));
        $this->assertCount(3, explode('.', $clientToken));

        echo "\n✅ All JWT token types generate correctly\n";
    }

    public function testEndpointAvailability(): void
    {
        $client = RagClientFactory::createWithTestToken($this->baseUrl, 'test_availability');

        $endpoints = [
            'health' => [$client, 'health'],
            'confidence_thresholds' => [$client, 'getConfidenceThresholds'],
            'ui_settings' => [$client, 'getUISettings'],
            'available_models' => [$client, 'getAvailableModels'],
            'taxonomy_info' => [$client, 'getTaxonomyInfo'],
            'validation_summary' => [$client, 'getValidationSummary'],
            'prometheus_metrics' => [$client, 'getPrometheusMetrics'],
        ];

        $availableEndpoints = [];
        $unavailableEndpoints = [];

        foreach ($endpoints as $name => $callable) {
            try {
                call_user_func($callable);
                $availableEndpoints[] = $name;
            } catch (RagApiException $e) {
                $unavailableEndpoints[] = $name . ' (' . $e->getMessage() . ')';
            }
        }

        echo "\n📊 Endpoint availability report:\n";
        echo "✅ Available (" . count($availableEndpoints) . "): " . implode(', ', $availableEndpoints) . "\n";
        echo "❌ Unavailable (" . count($unavailableEndpoints) . "): " . implode(', ', $unavailableEndpoints) . "\n";

        // We consider the test successful if at least basic health works
        $this->assertContains('health', $availableEndpoints, 'At least basic health endpoint should be available');
    }

    public function testClassificationEndpoints(): void
    {
        $client = RagClientFactory::createWithTestToken($this->baseUrl, 'test_classification');

        $testContent = "FACTURE N° 2023-001\nMontant: 150.00 EUR\nDate: 2023-12-01";

        try {
            // Test document classification
            $classification = $client->classifyDocument($testContent, "Test Invoice");
            $this->assertIsArray($classification);
            echo "\n✅ Document classification endpoint working\n";

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '404') === false && strpos($e->getMessage(), '503') === false) {
                throw $e;
            }
            echo "\n⚠️ Classification service not available: " . $e->getMessage() . "\n";
        }

        try {
            // Test metadata extraction
            $metadata = $client->extractMetadata($testContent, 'invoice');
            $this->assertIsArray($metadata);
            echo "\n✅ Metadata extraction endpoint working\n";

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '404') === false && strpos($e->getMessage(), '503') === false) {
                throw $e;
            }
            echo "\n⚠️ Metadata extraction service not available: " . $e->getMessage() . "\n";
        }
    }

    public function testMonitoringEndpoints(): void
    {
        $client = RagClientFactory::createWithTestToken($this->baseUrl, 'test_monitoring');

        try {
            // Test detailed health check
            $detailedHealth = $client->getDetailedHealthCheck();
            $this->assertIsArray($detailedHealth);
            echo "\n✅ Detailed health check endpoint working\n";

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '404') === false && strpos($e->getMessage(), '503') === false) {
                throw $e;
            }
            echo "\n⚠️ Detailed health check not available: " . $e->getMessage() . "\n";
        }

        try {
            // Test Prometheus metrics
            $metrics = $client->getPrometheusMetrics();
            $this->assertIsString($metrics);
            echo "\n✅ Prometheus metrics endpoint working\n";

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '404') === false && strpos($e->getMessage(), '503') === false) {
                throw $e;
            }
            echo "\n⚠️ Prometheus metrics not available: " . $e->getMessage() . "\n";
        }
    }
}