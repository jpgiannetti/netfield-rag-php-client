<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\Client\RagClient;
use Netfield\RagClient\Auth\JwtAuthenticator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class RagClientExtendedTest extends TestCase
{
    private RagClient $ragClient;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $token = JwtAuthenticator::generateTestToken('test_client');
        $this->ragClient = new RagClient('http://localhost:8888', $token, $httpClient);
    }

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

    public function testGetValidationSummary(): void
    {
        $responseData = [
            'total_documents' => 100,
            'documents_with_errors' => 5,
            'total_errors' => 8,
            'total_warnings' => 12,
            'error_rate' => 0.05,
            'most_common_errors' => [
                ['field' => 'amount', 'error_count' => 3],
                ['field' => 'date', 'error_count' => 2]
            ]
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ragClient->getValidationSummary(30);

        $this->assertIsArray($result);
        $this->assertEquals(100, $result['total_documents']);
        $this->assertEquals(5, $result['documents_with_errors']);
        $this->assertEquals(0.05, $result['error_rate']);
        $this->assertCount(2, $result['most_common_errors']);
    }

    public function testGetPrometheusMetrics(): void
    {
        $metricsData = '# HELP rag_queries_total Total number of RAG queries
# TYPE rag_queries_total counter
rag_queries_total{tenant="test_client"} 42
# HELP rag_query_duration_seconds RAG query duration
# TYPE rag_query_duration_seconds histogram
rag_query_duration_seconds_bucket{le="1.0"} 10';

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'text/plain'], $metricsData)
        );

        $result = $this->ragClient->getPrometheusMetrics();

        $this->assertIsString($result);
        $this->assertThat($result, $this->stringContains('rag_queries_total'));
        $this->assertThat($result, $this->stringContains('test_client'));
    }

    public function testGetDetailedHealthCheck(): void
    {
        $responseData = [
            'status' => 'healthy',
            'services' => [
                'weaviate' => ['status' => 'up', 'response_time' => 0.05],
                'ollama' => ['status' => 'up', 'response_time' => 0.12],
                'redis' => ['status' => 'up', 'response_time' => 0.01]
            ],
            'system' => [
                'cpu_usage' => 45.2,
                'memory_usage' => 62.8,
                'disk_usage' => 78.1
            ],
            'version' => '1.0.0',
            'uptime' => 3600
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ragClient->getDetailedHealthCheck();

        $this->assertIsArray($result);
        $this->assertEquals('healthy', $result['status']);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('system', $result);
        $this->assertEquals('1.0.0', $result['version']);
    }

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

    public function testGetConfidenceMetrics(): void
    {
        $responseData = [
            'average_confidence' => 0.78,
            'confidence_distribution' => [
                'very_high' => 15,
                'high' => 25,
                'medium' => 30,
                'low' => 20,
                'very_low' => 10
            ],
            'total_queries' => 100,
            'period' => '24h'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ragClient->getConfidenceMetrics();

        $this->assertIsArray($result);
        $this->assertEquals(0.78, $result['average_confidence']);
        $this->assertArrayHasKey('confidence_distribution', $result);
        $this->assertEquals(100, $result['total_queries']);
    }
}
