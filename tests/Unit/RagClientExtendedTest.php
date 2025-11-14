<?php

declare(strict_types=1);

namespace Netfield\Client\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Netfield\Client\Client\NetfieldClient;
use Netfield\Client\Auth\JwtAuthenticator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class RagClientExtendedTest extends TestCase
{
    private NetfieldClient $ragClient;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $token = JwtAuthenticator::generateTestToken('test_client');
        $this->ragClient = new NetfieldClient('http://localhost:8888', $token, $httpClient);
    }

    // TODO: Ces tests doivent être migrés vers DisClientTest
    // Les méthodes classifyDocument(), extractMetadata(), getTaxonomyInfo()
    // sont maintenant dans DisClient (séparé de NetfieldClient)

    /*
    public function testClassifyDocument(): void
    {
        $responseData = [
            'doc_type' => 'invoice',
            'category' => 'finance',
            'confidence' => 0.95,
            'subtype' => 'purchase_invoice',
            'extracted_metadata' => [
                'amount' => '100.00',
                'currency' => 'EUR'
            ],
            'reasoning' => 'Document contains invoice number and amount'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ragClient->classifyDocument('Invoice content here', 'Invoice Title');

        $this->assertIsArray($result);
        $this->assertEquals('invoice', $result['doc_type']);
        $this->assertEquals('finance', $result['category']);
        $this->assertEquals(0.95, $result['confidence']);
    }

    public function testExtractMetadata(): void
    {
        $responseData = [
            'invoice_number' => 'INV-2023-001',
            'amount' => 100.00,
            'currency' => 'EUR',
            'date' => '2023-12-01',
            'vendor' => 'Test Company'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ragClient->extractMetadata('Invoice content here', 'invoice');

        $this->assertIsArray($result);
        $this->assertEquals('INV-2023-001', $result['invoice_number']);
        $this->assertEquals(100.00, $result['amount']);
        $this->assertEquals('EUR', $result['currency']);
    }

    public function testGetTaxonomyInfo(): void
    {
        $responseData = [
            'schema_version' => '1.0',
            'total_categories' => 5,
            'total_document_types' => 15,
            'categories' => [
                [
                    'name' => 'finance',
                    'document_types' => ['invoice', 'receipt', 'contract']
                ],
                [
                    'name' => 'hr',
                    'document_types' => ['payslip', 'employee_contract']
                ]
            ]
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ragClient->getTaxonomyInfo();

        $this->assertIsArray($result);
        $this->assertEquals('1.0', $result['schema_version']);
        $this->assertEquals(5, $result['total_categories']);
        $this->assertEquals(15, $result['total_document_types']);
        $this->assertCount(2, $result['categories']);
    }
    */

    /**
     * Test déplacé vers MonitoringClientTest - getValidationSummary() est maintenant dans ValidationClient
     */
    // public function testGetValidationSummary(): void

    /**
     * Test déplacé vers MonitoringClientTest - getPrometheusMetrics() est maintenant dans MonitoringClient
     */
    // public function testGetPrometheusMetrics(): void

    /**
     * Test déplacé vers MonitoringClientTest - getDetailedHealthCheck() est maintenant dans MonitoringClient
     */
    // public function testGetDetailedHealthCheck(): void

    public function testGetAvailableModels(): void
    {
        $responseData = [
            'models' => [
                ['name' => 'llama3', 'size' => '4.7GB', 'status' => 'available'],
                ['name' => 'mistral', 'size' => '4.1GB', 'status' => 'available'],
                ['name' => 'codellama', 'size' => '3.8GB', 'status' => 'downloading']
            ],
            'default_model' => 'llama3'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ragClient->getAvailableModels();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('models', $result);
        $this->assertCount(3, $result['models']);
        $this->assertEquals('llama3', $result['default_model']);
    }

    /**
     * Test déplacé vers MonitoringClientTest - getConfidenceMetrics() est maintenant dans MonitoringClient
     */
    // public function testGetConfidenceMetrics(): void
}
