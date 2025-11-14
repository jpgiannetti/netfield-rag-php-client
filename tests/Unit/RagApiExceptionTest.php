<?php

declare(strict_types=1);

namespace Netfield\Client\Tests\Unit;

use Netfield\Client\Exception\NetfieldApiException;
use Netfield\Client\Exception\ErrorCode;
use PHPUnit\Framework\TestCase;

class RagApiExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function exception_stores_error_code(): void
    {
        $exception = new NetfieldApiException(
            'Token expired',
            401,
            null,
            null,
            ErrorCode::AUTH_TOKEN_EXPIRED
        );

        $this->assertEquals(ErrorCode::AUTH_TOKEN_EXPIRED, $exception->getErrorCode());
    }

    /**
     * @test
     */
    public function exception_stores_full_error_data(): void
    {
        $errorData = [
            'error_code' => ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
            'message' => 'No relevant documents found',
            'details' => ['query' => 'test', 'searched_documents' => 0],
            'field' => null,
            'timestamp' => '2025-10-03T14:30:00Z',
            'trace_id' => 'abc123',
        ];

        $exception = new NetfieldApiException(
            'No relevant documents',
            404,
            null,
            null,
            ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
            $errorData
        );

        $this->assertEquals($errorData, $exception->getErrorData());
    }

    /**
     * @test
     */
    public function get_details_extracts_details_from_error_data(): void
    {
        $details = ['query' => 'test', 'searched' => 100];
        $errorData = [
            'error_code' => ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
            'message' => 'No results',
            'details' => $details,
        ];

        $exception = new NetfieldApiException(
            'No results',
            404,
            null,
            null,
            ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
            $errorData
        );

        $this->assertEquals($details, $exception->getDetails());
    }

    /**
     * @test
     */
    public function get_field_extracts_field_from_error_data(): void
    {
        $errorData = [
            'error_code' => ErrorCode::VALIDATION_MISSING_FIELD,
            'message' => 'Field required',
            'field' => 'document_id',
        ];

        $exception = new NetfieldApiException(
            'Field required',
            422,
            null,
            null,
            ErrorCode::VALIDATION_MISSING_FIELD,
            $errorData
        );

        $this->assertEquals('document_id', $exception->getField());
    }

    /**
     * @test
     */
    public function get_timestamp_extracts_timestamp_from_error_data(): void
    {
        $timestamp = '2025-10-03T14:30:00Z';
        $errorData = [
            'error_code' => ErrorCode::SYSTEM_INTERNAL_ERROR,
            'message' => 'Internal error',
            'timestamp' => $timestamp,
        ];

        $exception = new NetfieldApiException(
            'Internal error',
            500,
            null,
            null,
            ErrorCode::SYSTEM_INTERNAL_ERROR,
            $errorData
        );

        $this->assertEquals($timestamp, $exception->getTimestamp());
    }

    /**
     * @test
     */
    public function get_trace_id_extracts_trace_id_from_error_data(): void
    {
        $traceId = 'trace-abc-123';
        $errorData = [
            'error_code' => ErrorCode::SYSTEM_INTERNAL_ERROR,
            'message' => 'Internal error',
            'trace_id' => $traceId,
        ];

        $exception = new NetfieldApiException(
            'Internal error',
            500,
            null,
            null,
            ErrorCode::SYSTEM_INTERNAL_ERROR,
            $errorData
        );

        $this->assertEquals($traceId, $exception->getTraceId());
    }

    /**
     * @test
     */
    public function is_retryable_returns_true_for_retryable_errors(): void
    {
        $exception = new NetfieldApiException(
            'Service unavailable',
            503,
            null,
            null,
            ErrorCode::SYSTEM_SERVICE_UNAVAILABLE
        );

        $this->assertTrue($exception->isRetryable());
    }

    /**
     * @test
     */
    public function is_retryable_returns_false_for_non_retryable_errors(): void
    {
        $exception = new NetfieldApiException(
            'Invalid token',
            401,
            null,
            null,
            ErrorCode::AUTH_TOKEN_INVALID
        );

        $this->assertFalse($exception->isRetryable());
    }

    /**
     * @test
     */
    public function is_critical_returns_true_for_critical_errors(): void
    {
        $exception = new NetfieldApiException(
            'Internal error',
            500,
            null,
            null,
            ErrorCode::SYSTEM_INTERNAL_ERROR
        );

        $this->assertTrue($exception->isCritical());
    }

    /**
     * @test
     */
    public function is_critical_returns_false_for_non_critical_errors(): void
    {
        $exception = new NetfieldApiException(
            'No documents',
            404,
            null,
            null,
            ErrorCode::RAG_NO_RELEVANT_DOCUMENTS
        );

        $this->assertFalse($exception->isCritical());
    }

    /**
     * @test
     */
    public function needs_auth_refresh_returns_true_for_auth_errors(): void
    {
        $exception = new NetfieldApiException(
            'Token expired',
            401,
            null,
            null,
            ErrorCode::AUTH_TOKEN_EXPIRED
        );

        $this->assertTrue($exception->needsAuthRefresh());
    }

    /**
     * @test
     */
    public function needs_auth_refresh_returns_false_for_non_auth_errors(): void
    {
        $exception = new NetfieldApiException(
            'LLM unavailable',
            503,
            null,
            null,
            ErrorCode::RAG_LLM_UNAVAILABLE
        );

        $this->assertFalse($exception->needsAuthRefresh());
    }

    /**
     * @test
     */
    public function exception_without_error_code_returns_null(): void
    {
        $exception = new NetfieldApiException('Generic error', 500);

        $this->assertNull($exception->getErrorCode());
        $this->assertNull($exception->getErrorData());
        $this->assertNull($exception->getDetails());
        $this->assertNull($exception->getField());
        $this->assertNull($exception->getTimestamp());
        $this->assertNull($exception->getTraceId());
    }

    /**
     * @test
     */
    public function exception_without_error_code_returns_false_for_checks(): void
    {
        $exception = new NetfieldApiException('Generic error', 500);

        $this->assertFalse($exception->isRetryable());
        $this->assertFalse($exception->isCritical());
        $this->assertFalse($exception->needsAuthRefresh());
    }

    /**
     * @test
     */
    public function exception_preserves_original_context(): void
    {
        $context = ['user_id' => 123, 'action' => 'query'];

        $exception = new NetfieldApiException(
            'Error occurred',
            500,
            null,
            $context,
            ErrorCode::SYSTEM_INTERNAL_ERROR
        );

        $this->assertEquals($context, $exception->getContext());
    }

    /**
     * @test
     */
    public function exception_with_missing_error_data_fields_returns_null(): void
    {
        $errorData = [
            'error_code' => ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
            'message' => 'No documents',
            // Missing: details, field, timestamp, trace_id
        ];

        $exception = new NetfieldApiException(
            'No documents',
            404,
            null,
            null,
            ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
            $errorData
        );

        $this->assertNull($exception->getDetails());
        $this->assertNull($exception->getField());
        $this->assertNull($exception->getTimestamp());
        $this->assertNull($exception->getTraceId());
    }

    /**
     * @test
     */
    public function exception_chain_is_preserved(): void
    {
        $previous = new \RuntimeException('Previous error');

        $exception = new NetfieldApiException(
            'RAG error',
            500,
            $previous,
            null,
            ErrorCode::SYSTEM_INTERNAL_ERROR
        );

        $this->assertSame($previous, $exception->getPrevious());
    }
}
