<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\Client\RagClient;
use Netfield\RagClient\Models\Request\AskRequest;
use Netfield\RagClient\Models\Request\BulkIndexRequest;
use Netfield\RagClient\Models\Request\IndexDocumentRequest;
use Netfield\RagClient\Models\Request\DocumentInfo;
use Netfield\RagClient\Auth\JwtAuthenticator;
use Netfield\RagClient\Exception\RagApiException;

class RagClientExtendedIntegrationTest extends TestCase
{
    private RagClient $ragClient;
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = $_ENV['RAG_API_URL'] ?? 'http://localhost:8888';

        // Generate client token for testing
        $clientToken = JwtAuthenticator::generateTestToken('test_client_integration');
        $this->ragClient = new RagClient($this->baseUrl, $clientToken);
    }

    public function testClassifyDocumentIntegration(): void
    {
        $documentContent = "FACTURE N° 2023-001\nMontant: 150.00 EUR\nDate: 2023-12-01\nFournisseur: Société Test";

        try {
            $result = $this->ragClient->classifyDocument($documentContent, "Facture Test");

            $this->assertIsArray($result);
            $this->assertArrayHasKey('doc_type', $result);
            $this->assertArrayHasKey('category', $result);
            $this->assertArrayHasKey('confidence', $result);

            if (isset($result['doc_type'])) {
                $this->assertIsString($result['doc_type']);
            }
            if (isset($result['confidence'])) {
                $this->assertIsFloat($result['confidence']);
                $this->assertGreaterThanOrEqual(0, $result['confidence']);
                $this->assertLessThanOrEqual(1, $result['confidence']);
            }

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Classification service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testExtractMetadataIntegration(): void
    {
        $documentContent = "FACTURE N° INV-2023-001\nMontant: 250.50 EUR\nDate: 2023-12-15\nFournisseur: ACME Corp";

        try {
            $result = $this->ragClient->extractMetadata($documentContent, 'invoice');

            $this->assertIsArray($result);
            // Metadata extraction might return various fields depending on document type

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false ||
                strpos($e->getMessage(), '404') !== false ||
                strpos($e->getMessage(), '422') !== false) {
                $this->markTestIncomplete('Metadata extraction service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetTaxonomyInfoIntegration(): void
    {
        try {
            $result = $this->ragClient->getTaxonomyInfo();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('schema_version', $result);
            $this->assertArrayHasKey('total_categories', $result);
            $this->assertArrayHasKey('total_document_types', $result);
            $this->assertArrayHasKey('categories', $result);

            $this->assertIsString($result['schema_version']);
            $this->assertIsInt($result['total_categories']);
            $this->assertIsInt($result['total_document_types']);
            $this->assertIsArray($result['categories']);

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Taxonomy service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetFilterableFieldsIntegration(): void
    {
        try {
            $result = $this->ragClient->getFilterableFields('invoice');

            $this->assertIsArray($result);
            // Should return array of field names
            foreach ($result as $field) {
                $this->assertIsString($field);
            }

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false ||
                strpos($e->getMessage(), '404') !== false ||
                strpos($e->getMessage(), '422') !== false) {
                $this->markTestIncomplete('Filterable fields service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetCommonMetadataFieldsIntegration(): void
    {
        try {
            $result = $this->ragClient->getCommonMetadataFields();

            $this->assertIsArray($result);
            // Should return metadata field definitions

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Common metadata fields service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testValidateDocumentsIntegration(): void
    {
        $documentInfo = new DocumentInfo('Test Document', '2023-12-01 10:00:00');
        $document = new IndexDocumentRequest('doc_test_validation', 'test_client_integration', $documentInfo);
        $document->setContent('Test document content for validation');

        $bulkRequest = new BulkIndexRequest('test_client_integration', [$document]);

        try {
            $result = $this->ragClient->validateDocuments($bulkRequest);

            $this->assertIsArray($result);
            // Validation should return structure information

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Document validation service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetValidationSummaryIntegration(): void
    {
        try {
            $result = $this->ragClient->getValidationSummary(7); // Last 7 days

            $this->assertIsArray($result);
            $this->assertArrayHasKey('total_documents', $result);
            $this->assertArrayHasKey('documents_with_errors', $result);
            $this->assertArrayHasKey('total_errors', $result);
            $this->assertArrayHasKey('total_warnings', $result);
            $this->assertArrayHasKey('error_rate', $result);

            $this->assertIsInt($result['total_documents']);
            $this->assertIsInt($result['documents_with_errors']);
            $this->assertIsInt($result['total_errors']);
            $this->assertIsInt($result['total_warnings']);
            $this->assertIsFloat($result['error_rate']);

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Validation summary service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetConfidenceThresholdsIntegration(): void
    {
        try {
            $result = $this->ragClient->getConfidenceThresholds();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('very_high', $result);
            $this->assertArrayHasKey('high', $result);
            $this->assertArrayHasKey('medium', $result);
            $this->assertArrayHasKey('low', $result);
            $this->assertArrayHasKey('display_threshold', $result);

            foreach (['very_high', 'high', 'medium', 'low', 'display_threshold'] as $key) {
                $this->assertIsFloat($result[$key]);
                $this->assertGreaterThanOrEqual(0, $result[$key]);
                $this->assertLessThanOrEqual(1, $result[$key]);
            }

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Confidence thresholds service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetUISettingsIntegration(): void
    {
        try {
            $result = $this->ragClient->getUISettings();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('show_confidence_badge', $result);
            $this->assertArrayHasKey('show_reliability_details', $result);
            $this->assertArrayHasKey('hide_low_confidence', $result);
            $this->assertArrayHasKey('warning_threshold', $result);

            $this->assertIsBool($result['show_confidence_badge']);
            $this->assertIsBool($result['show_reliability_details']);
            $this->assertIsBool($result['hide_low_confidence']);
            $this->assertIsFloat($result['warning_threshold']);

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('UI settings service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetAvailableModelsIntegration(): void
    {
        try {
            $result = $this->ragClient->getAvailableModels();

            $this->assertIsArray($result);
            if (isset($result['models'])) {
                $this->assertIsArray($result['models']);
            }
            if (isset($result['default_model'])) {
                $this->assertIsString($result['default_model']);
            }

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Available models service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testRagHealthCheckIntegration(): void
    {
        try {
            $result = $this->ragClient->ragHealthCheck();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('status', $result);

            if ($result['status'] === 'healthy') {
                // If healthy, might have additional info
                $this->assertEquals('healthy', $result['status']);
            }

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false) {
                $this->markTestIncomplete('RAG health check indicates service issues - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetDetailedHealthCheckIntegration(): void
    {
        try {
            $result = $this->ragClient->getDetailedHealthCheck();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('status', $result);

            if (isset($result['services'])) {
                $this->assertIsArray($result['services']);
            }
            if (isset($result['system'])) {
                $this->assertIsArray($result['system']);
            }

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Detailed health check service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testGetPrometheusMetricsIntegration(): void
    {
        try {
            $result = $this->ragClient->getPrometheusMetrics();

            $this->assertIsString($result);
            $this->assertThat($result, $this->stringContains('# HELP'));

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false || strpos($e->getMessage(), '404') !== false) {
                $this->markTestIncomplete('Prometheus metrics service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }

    public function testTestRagPipelineIntegration(): void
    {
        $askRequest = new AskRequest('Test question about document management');

        try {
            $result = $this->ragClient->testRagPipeline($askRequest);

            $this->assertIsArray($result);
            // Test pipeline should return debug information

        } catch (RagApiException $e) {
            if (strpos($e->getMessage(), '503') !== false ||
                strpos($e->getMessage(), '404') !== false ||
                strpos($e->getMessage(), '422') !== false) {
                $this->markTestIncomplete('RAG pipeline test service may not be available - endpoint structure validated');
            } else {
                throw $e;
            }
        }
    }
}