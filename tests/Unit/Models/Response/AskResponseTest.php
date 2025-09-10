<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Unit\Models\Response;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\Models\Response\AskResponse;

class AskResponseTest extends TestCase
{
    private array $minimalResponseData;
    private array $fullResponseData;

    protected function setUp(): void
    {
        $this->minimalResponseData = [
            'status' => 'success',
            'answer' => 'This is the answer to your question.',
            'question' => 'What is the question?',
            'tenant_id' => 'test-tenant',
            'retrieved_documents' => [],
            'processing_time' => 1.25
        ];

        $this->fullResponseData = [
            'status' => 'success',
            'answer' => 'Comprehensive answer with all details.',
            'question' => 'Complex question requiring detailed response?',
            'tenant_id' => 'test-tenant-full',
            'retrieved_documents' => [
                [
                    'document_id' => 'doc-1',
                    'title' => 'Document One',
                    'content' => 'Content excerpt',
                    'score' => 0.95,
                    'metadata' => ['type' => 'invoice']
                ],
                [
                    'document_id' => 'doc-2', 
                    'title' => 'Document Two',
                    'content' => 'Another excerpt',
                    'score' => 0.87,
                    'metadata' => ['type' => 'contract']
                ]
            ],
            'processing_time' => 2.75,
            'model_used' => 'llama3',
            'confidence' => 0.92,
            'confidence_level' => 'very_high',
            'ui_message' => 'High confidence response based on relevant documents',
            'reliability_indicators' => [
                'source_quality' => 'high',
                'answer_completeness' => 'complete',
                'citation_coverage' => 1.0
            ],
            'validation_passed' => true,
            'search_strategy' => 'hybrid_semantic_exact'
        ];
    }

    public function testConstructorWithMinimalData(): void
    {
        $response = new AskResponse(
            $this->minimalResponseData['status'],
            $this->minimalResponseData['answer'],
            $this->minimalResponseData['question'],
            $this->minimalResponseData['tenant_id'],
            $this->minimalResponseData['retrieved_documents'],
            $this->minimalResponseData['processing_time']
        );

        $this->assertEquals($this->minimalResponseData['status'], $response->getStatus());
        $this->assertEquals($this->minimalResponseData['answer'], $response->getAnswer());
        $this->assertEquals($this->minimalResponseData['question'], $response->getQuestion());
        $this->assertEquals($this->minimalResponseData['tenant_id'], $response->getTenantId());
        $this->assertEquals($this->minimalResponseData['retrieved_documents'], $response->getRetrievedDocuments());
        $this->assertEquals($this->minimalResponseData['processing_time'], $response->getProcessingTime());
        
        // Optional fields should be null
        $this->assertNull($response->getModelUsed());
        $this->assertNull($response->getConfidence());
        $this->assertNull($response->getConfidenceLevel());
        $this->assertNull($response->getUiMessage());
        $this->assertNull($response->getReliabilityIndicators());
        $this->assertNull($response->isValidationPassed());
        $this->assertNull($response->getSearchStrategy());
    }

    public function testConstructorWithFullData(): void
    {
        $response = new AskResponse(
            $this->fullResponseData['status'],
            $this->fullResponseData['answer'],
            $this->fullResponseData['question'],
            $this->fullResponseData['tenant_id'],
            $this->fullResponseData['retrieved_documents'],
            $this->fullResponseData['processing_time'],
            $this->fullResponseData['model_used'],
            $this->fullResponseData['confidence'],
            $this->fullResponseData['confidence_level'],
            $this->fullResponseData['ui_message'],
            $this->fullResponseData['reliability_indicators'],
            $this->fullResponseData['validation_passed'],
            $this->fullResponseData['search_strategy']
        );

        $this->assertEquals($this->fullResponseData['status'], $response->getStatus());
        $this->assertEquals($this->fullResponseData['answer'], $response->getAnswer());
        $this->assertEquals($this->fullResponseData['question'], $response->getQuestion());
        $this->assertEquals($this->fullResponseData['tenant_id'], $response->getTenantId());
        $this->assertEquals($this->fullResponseData['retrieved_documents'], $response->getRetrievedDocuments());
        $this->assertEquals($this->fullResponseData['processing_time'], $response->getProcessingTime());
        $this->assertEquals($this->fullResponseData['model_used'], $response->getModelUsed());
        $this->assertEquals($this->fullResponseData['confidence'], $response->getConfidence());
        $this->assertEquals($this->fullResponseData['confidence_level'], $response->getConfidenceLevel());
        $this->assertEquals($this->fullResponseData['ui_message'], $response->getUiMessage());
        $this->assertEquals($this->fullResponseData['reliability_indicators'], $response->getReliabilityIndicators());
        $this->assertEquals($this->fullResponseData['validation_passed'], $response->isValidationPassed());
        $this->assertEquals($this->fullResponseData['search_strategy'], $response->getSearchStrategy());
    }

    public function testIsSuccessful(): void
    {
        $successResponse = AskResponse::fromArray($this->minimalResponseData);
        $this->assertTrue($successResponse->isSuccessful());

        $errorData = array_merge($this->minimalResponseData, ['status' => 'error']);
        $errorResponse = AskResponse::fromArray($errorData);
        $this->assertFalse($errorResponse->isSuccessful());

        $failedData = array_merge($this->minimalResponseData, ['status' => 'failed']);
        $failedResponse = AskResponse::fromArray($failedData);
        $this->assertFalse($failedResponse->isSuccessful());
    }

    public function testHasHighConfidence(): void
    {
        // Test very_high confidence
        $veryHighData = array_merge($this->minimalResponseData, ['confidence_level' => 'very_high']);
        $veryHighResponse = AskResponse::fromArray($veryHighData);
        $this->assertTrue($veryHighResponse->hasHighConfidence());

        // Test high confidence
        $highData = array_merge($this->minimalResponseData, ['confidence_level' => 'high']);
        $highResponse = AskResponse::fromArray($highData);
        $this->assertTrue($highResponse->hasHighConfidence());

        // Test medium confidence
        $mediumData = array_merge($this->minimalResponseData, ['confidence_level' => 'medium']);
        $mediumResponse = AskResponse::fromArray($mediumData);
        $this->assertFalse($mediumResponse->hasHighConfidence());

        // Test low confidence
        $lowData = array_merge($this->minimalResponseData, ['confidence_level' => 'low']);
        $lowResponse = AskResponse::fromArray($lowData);
        $this->assertFalse($lowResponse->hasHighConfidence());

        // Test very_low confidence
        $veryLowData = array_merge($this->minimalResponseData, ['confidence_level' => 'very_low']);
        $veryLowResponse = AskResponse::fromArray($veryLowData);
        $this->assertFalse($veryLowResponse->hasHighConfidence());

        // Test null confidence level
        $nullData = array_merge($this->minimalResponseData, ['confidence_level' => null]);
        $nullResponse = AskResponse::fromArray($nullData);
        $this->assertFalse($nullResponse->hasHighConfidence());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $response = AskResponse::fromArray($this->minimalResponseData);

        $this->assertEquals($this->minimalResponseData['status'], $response->getStatus());
        $this->assertEquals($this->minimalResponseData['answer'], $response->getAnswer());
        $this->assertEquals($this->minimalResponseData['question'], $response->getQuestion());
        $this->assertEquals($this->minimalResponseData['tenant_id'], $response->getTenantId());
        $this->assertEquals($this->minimalResponseData['retrieved_documents'], $response->getRetrievedDocuments());
        $this->assertEquals($this->minimalResponseData['processing_time'], $response->getProcessingTime());
    }

    public function testFromArrayWithFullData(): void
    {
        $response = AskResponse::fromArray($this->fullResponseData);

        $this->assertEquals($this->fullResponseData['status'], $response->getStatus());
        $this->assertEquals($this->fullResponseData['answer'], $response->getAnswer());
        $this->assertEquals($this->fullResponseData['question'], $response->getQuestion());
        $this->assertEquals($this->fullResponseData['tenant_id'], $response->getTenantId());
        $this->assertEquals($this->fullResponseData['retrieved_documents'], $response->getRetrievedDocuments());
        $this->assertEquals($this->fullResponseData['processing_time'], $response->getProcessingTime());
        $this->assertEquals($this->fullResponseData['model_used'], $response->getModelUsed());
        $this->assertEquals($this->fullResponseData['confidence'], $response->getConfidence());
        $this->assertEquals($this->fullResponseData['confidence_level'], $response->getConfidenceLevel());
        $this->assertEquals($this->fullResponseData['ui_message'], $response->getUiMessage());
        $this->assertEquals($this->fullResponseData['reliability_indicators'], $response->getReliabilityIndicators());
        $this->assertEquals($this->fullResponseData['validation_passed'], $response->isValidationPassed());
        $this->assertEquals($this->fullResponseData['search_strategy'], $response->getSearchStrategy());
    }

    public function testToArrayWithMinimalData(): void
    {
        $response = AskResponse::fromArray($this->minimalResponseData);
        $array = $response->toArray();

        $this->assertEquals($this->minimalResponseData['status'], $array['status']);
        $this->assertEquals($this->minimalResponseData['answer'], $array['answer']);
        $this->assertEquals($this->minimalResponseData['question'], $array['question']);
        $this->assertEquals($this->minimalResponseData['tenant_id'], $array['tenant_id']);
        $this->assertEquals($this->minimalResponseData['retrieved_documents'], $array['retrieved_documents']);
        $this->assertEquals($this->minimalResponseData['processing_time'], $array['processing_time']);

        // Optional fields should not be present
        $this->assertArrayNotHasKey('model_used', $array);
        $this->assertArrayNotHasKey('confidence', $array);
        $this->assertArrayNotHasKey('confidence_level', $array);
        $this->assertArrayNotHasKey('ui_message', $array);
        $this->assertArrayNotHasKey('reliability_indicators', $array);
        $this->assertArrayNotHasKey('validation_passed', $array);
        $this->assertArrayNotHasKey('search_strategy', $array);
    }

    public function testToArrayWithFullData(): void
    {
        $response = AskResponse::fromArray($this->fullResponseData);
        $array = $response->toArray();

        $this->assertEquals($this->fullResponseData, $array);
    }

    public function testRoundTripSerialization(): void
    {
        // Test minimal data round trip
        $minimalResponse = AskResponse::fromArray($this->minimalResponseData);
        $minimalArray = $minimalResponse->toArray();
        $minimalRoundTrip = AskResponse::fromArray($minimalArray);

        $this->assertEquals($minimalResponse->getStatus(), $minimalRoundTrip->getStatus());
        $this->assertEquals($minimalResponse->getAnswer(), $minimalRoundTrip->getAnswer());
        $this->assertEquals($minimalResponse->getProcessingTime(), $minimalRoundTrip->getProcessingTime());

        // Test full data round trip
        $fullResponse = AskResponse::fromArray($this->fullResponseData);
        $fullArray = $fullResponse->toArray();
        $fullRoundTrip = AskResponse::fromArray($fullArray);

        $this->assertEquals($fullResponse->getStatus(), $fullRoundTrip->getStatus());
        $this->assertEquals($fullResponse->getAnswer(), $fullRoundTrip->getAnswer());
        $this->assertEquals($fullResponse->getConfidenceLevel(), $fullRoundTrip->getConfidenceLevel());
        $this->assertEquals($fullResponse->getReliabilityIndicators(), $fullRoundTrip->getReliabilityIndicators());
    }

    /**
     * @dataProvider confidenceLevelDataProvider
     */
    public function testConfidenceLevelMethods(string $confidenceLevel, bool $expectedHighConfidence): void
    {
        $data = array_merge($this->minimalResponseData, ['confidence_level' => $confidenceLevel]);
        $response = AskResponse::fromArray($data);

        $this->assertEquals($confidenceLevel, $response->getConfidenceLevel());
        $this->assertEquals($expectedHighConfidence, $response->hasHighConfidence());
    }

    public static function confidenceLevelDataProvider(): array
    {
        return [
            'very_high' => ['very_high', true],
            'high' => ['high', true],
            'medium' => ['medium', false],
            'low' => ['low', false],
            'very_low' => ['very_low', false],
        ];
    }

    /**
     * @dataProvider statusDataProvider
     */
    public function testStatusMethods(string $status, bool $expectedSuccess): void
    {
        $data = array_merge($this->minimalResponseData, ['status' => $status]);
        $response = AskResponse::fromArray($data);

        $this->assertEquals($status, $response->getStatus());
        $this->assertEquals($expectedSuccess, $response->isSuccessful());
    }

    public static function statusDataProvider(): array
    {
        return [
            'success' => ['success', true],
            'error' => ['error', false],
            'failed' => ['failed', false],
            'timeout' => ['timeout', false],
            'invalid' => ['invalid', false],
        ];
    }
}