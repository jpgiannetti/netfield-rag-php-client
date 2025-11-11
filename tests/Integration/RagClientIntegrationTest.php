<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\Client\NetfieldClient;
use Netfield\RagClient\Auth\JwtAuthenticator;
use Netfield\RagClient\Models\Request\{AskRequest, IndexDocumentRequest, BulkIndexRequest, DocumentInfo};
use Netfield\RagClient\Exception\NetfieldApiException;
use GuzzleHttp\Client;
use Psr\Log\NullLogger;

class RagClientIntegrationTest extends TestCase
{
    private NetfieldClient $client;
    private NullLogger $logger;
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = $_ENV['NETFIELD_API_URL'] ?? 'http://rag-api:8080';
        $this->logger = new NullLogger();

        // Generate test JWT token
        $tenantId = $_ENV['NETFIELD_TENANT_ID'] ?? 'test-tenant';
        $secretKey = $_ENV['NETFIELD_JWT_SECRET'] ?? 'test-secret-jwt-key-for-docker-tests';
        $token = JwtAuthenticator::generateTestToken($tenantId, $secretKey);

        // Create client with custom HTTP client for testing
        $httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        $this->client = new NetfieldClient($this->baseUrl, $token, $httpClient, $this->logger);
    }

    public function testHealthCheck(): void
    {
        $response = $this->client->health();

        $this->assertNotNull($response);
        $this->assertTrue($response->isHealthy());
        $this->assertEquals('healthy', $response->getStatus());
        $this->assertIsArray($response->getDetails());
    }

    public function testAskEndpoint(): void
    {
        // First ensure the service is healthy
        $this->testHealthCheck();

        $request = new AskRequest(
            "What is the RAG system?",
            5,
            ['type' => 'documentation']
        );

        $response = $this->client->ask($request);

        $this->assertNotNull($response);
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('success', $response->getStatus());
        $this->assertNotEmpty($response->getAnswer());
        $this->assertEquals($request->getQuestion(), $response->getQuestion());
        $this->assertIsArray($response->getRetrievedDocuments());
        $this->assertIsFloat($response->getProcessingTime());
        $this->assertGreaterThan(0, $response->getProcessingTime());

        // Check confidence information
        $this->assertIsString($response->getConfidenceLevel());
        $this->assertIsFloat($response->getConfidence());
        $this->assertTrue($response->getConfidence() >= 0 && $response->getConfidence() <= 1);

        // Verify logging
    }

    public function testIndexDocument(): void
    {
        $documentInfo = new DocumentInfo(
            title: 'Test Integration Document',
            creationDate: date('Y-m-d H:i:s'),
            nbPages: 1
        );

        $request = new IndexDocumentRequest(
            documentId: 'integration-test-doc-' . uniqid(),
            documentInfo: $documentInfo,
            content: 'This is a test document for integration testing. It contains sample content to verify the indexing process.',
            metadata: [
                'type' => 'test',
                'source' => 'integration-test',
                'environment' => 'docker'
            ]
        );

        $response = $this->client->indexDocument($request);

        $this->assertNotNull($response);
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('success', $response->getStatus());
        $this->assertNotEmpty($response->getDocumentId());
        $this->assertIsFloat($response->getProcessingTime());
        $this->assertGreaterThan(0, $response->getProcessingTime());

        // Verify logging
    }

    public function testBulkIndexDocuments(): void
    {
        $documents = [];

        for ($i = 1; $i <= 3; $i++) {
            $documentInfo = new DocumentInfo(
                title: "Bulk Test Document $i",
                creationDate: date('Y-m-d H:i:s'),
                nbPages: 1
            );

            $documents[] = new IndexDocumentRequest(
                documentId: 'bulk-test-doc-' . $i . '-' . uniqid(),
                documentInfo: $documentInfo,
                content: "This is bulk test document number $i with sample content for testing.",
                metadata: [
                    'type' => 'bulk-test',
                    'index' => $i,
                    'batch' => 'integration-test'
                ]
            );
        }

        $bulkRequest = new BulkIndexRequest('test-tenant', $documents);
        $response = $this->client->bulkIndexDocuments($bulkRequest);

        $this->assertNotNull($response);
        $this->assertTrue($response->isFullySuccessful());
        $this->assertEquals('success', $response->getStatus());
        $this->assertEquals(3, $response->getTotalDocuments());
        $this->assertEquals(3, $response->getIndexedSuccessfully());
        $this->assertEquals([], $response->getErrors());
        $this->assertEquals(100.0, $response->getSuccessRate());
        $this->assertIsFloat($response->getProcessingTime());
        $this->assertGreaterThan(0, $response->getProcessingTime());

        // Verify logging
    }

    public function testAskStreamingEndpoint(): void
    {
        $request = new AskRequest("Tell me about the test documents", 3);

        $chunks = [];
        $chunkCount = 0;

        foreach ($this->client->askStream($request) as $chunk) {
            $chunks[] = $chunk;
            $chunkCount++;

            $this->assertIsArray($chunk);

            // Limit the test to avoid infinite loops
            if ($chunkCount >= 10) {
                break;
            }
        }

        $this->assertGreaterThan(0, $chunkCount, 'Should receive at least one streaming chunk');
    }

    public function testUpdateDocument(): void
    {
        // First, index a document
        $documentId = 'update-test-doc-' . uniqid();

        $originalInfo = new DocumentInfo(
            title: 'Original Document Title',
            creationDate: date('Y-m-d H:i:s'),
            revision: 1
        );

        $originalRequest = new IndexDocumentRequest(
            documentId: $documentId,
            documentInfo: $originalInfo,
            content: 'Original document content',
            metadata: ['version' => 'original']
        );

        $indexResponse = $this->client->indexDocument($originalRequest);
        $this->assertTrue($indexResponse->isSuccessful());

        // Wait a moment to ensure indexing is complete
        sleep(1);

        // Now update the document
        $updatedInfo = new DocumentInfo(
            title: 'Updated Document Title',
            creationDate: date('Y-m-d H:i:s'),
            revision: 2
        );

        $updateRequest = new IndexDocumentRequest(
            documentId: $documentId,
            documentInfo: $updatedInfo,
            content: 'Updated document content with new information',
            metadata: ['version' => 'updated', 'modified' => true]
        );

        $updateResponse = $this->client->updateDocument($documentId, $updateRequest);

        $this->assertNotNull($updateResponse);
        $this->assertTrue($updateResponse->isSuccessful());
        $this->assertEquals('success', $updateResponse->getStatus());
        $this->assertNotEmpty($updateResponse->getDocumentId());
    }

    public function testDeleteDocument(): void
    {
        // First, index a document to delete
        $documentId = 'delete-test-doc-' . uniqid();

        $documentInfo = new DocumentInfo(
            title: 'Document to Delete',
            creationDate: date('Y-m-d H:i:s')
        );

        $indexRequest = new IndexDocumentRequest(
            documentId: $documentId,
            documentInfo: $documentInfo,
            content: 'This document will be deleted'
        );

        $indexResponse = $this->client->indexDocument($indexRequest);
        $this->assertTrue($indexResponse->isSuccessful());

        // Wait a moment to ensure indexing is complete
        sleep(1);

        // Now delete the document
        $deleteResponse = $this->client->deleteDocument($documentId);

        $this->assertIsArray($deleteResponse);
        $this->assertArrayHasKey('status', $deleteResponse);
        $this->assertEquals('success', $deleteResponse['status']);
    }

    public function testGetConfidenceThresholds(): void
    {
        $thresholds = $this->client->getConfidenceThresholds();

        $this->assertIsArray($thresholds);
        // The exact structure depends on the API implementation
        // but we expect some threshold configuration
    }

    public function testGetIndexingStats(): void
    {
        $stats = $this->client->getIndexingStats('test-tenant');

        $this->assertIsArray($stats);
        // The exact structure depends on the API implementation
        // but we expect some indexing statistics
    }

    public function testErrorHandling(): void
    {
        // Test with invalid question (too short)
        $this->expectException(NetfieldApiException::class);
        $invalidRequest = new AskRequest("Hi"); // Too short
    }

    public function testAuthenticationError(): void
    {
        // Create client with invalid JWT token (valid format but wrong signature)
        $httpClient = new Client(['timeout' => 30]);
        $invalidToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJpbnZhbGlkIiwidGVuYW50X2lkIjoiaW52YWxpZCIsImlhdCI6MTY5MzU5ODgwMCwiZXhwIjoxNjkzNjg1MjAwfQ.invalid_signature';
        $invalidClient = new NetfieldClient($this->baseUrl, $invalidToken, $httpClient);

        $this->expectException(NetfieldApiException::class);
        $this->expectExceptionMessage('Failed to execute RAG query');

        $request = new AskRequest("This should fail due to auth");
        $invalidClient->ask($request);
    }

    public function testEndToEndWorkflow(): void
    {
        // This test demonstrates a complete workflow
        $documentId = 'e2e-test-doc-' . uniqid();

        // 1. Health check
        $health = $this->client->health();
        $this->assertTrue($health->isHealthy());

        // 2. Index a document
        $documentInfo = new DocumentInfo(
            title: 'End-to-End Test Document',
            creationDate: date('Y-m-d H:i:s'),
            nbPages: 1
        );

        $indexRequest = new IndexDocumentRequest(
            documentId: $documentId,
            documentInfo: $documentInfo,
            content: 'This document is used for end-to-end testing. It contains information about the RAG system testing process and validation procedures.',
            metadata: [
                'type' => 'test',
                'workflow' => 'e2e',
                'importance' => 'high'
            ]
        );

        $indexResponse = $this->client->indexDocument($indexRequest);
        $this->assertTrue($indexResponse->isSuccessful());

        // Wait for indexing to complete
        sleep(2);

        // 3. Search for the document
        $askRequest = new AskRequest("Tell me about testing procedures", 5);
        $askResponse = $this->client->ask($askRequest);

        $this->assertTrue($askResponse->isSuccessful());
        $this->assertNotEmpty($askResponse->getAnswer());

        // 4. Clean up - delete the document
        $deleteResponse = $this->client->deleteDocument($documentId);
        $this->assertEquals('success', $deleteResponse['status']);

        // All operations completed successfully
    }
}
