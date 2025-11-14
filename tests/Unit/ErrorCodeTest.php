<?php

declare(strict_types=1);

namespace Netfield\Client\Tests\Unit;

use Netfield\Client\Exception\ErrorCode;
use PHPUnit\Framework\TestCase;

class ErrorCodeTest extends TestCase
{
    /**
     * @test
     */
    public function all_error_codes_are_in_upper_snake_case(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $name => $value) {
            // Skip non-error-code constants (arrays, etc.)
            if (!is_string($value)) {
                continue;
            }

            // Vérifier que la valeur est en UPPER_SNAKE_CASE
            $this->assertMatchesRegularExpression(
                '/^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$/',
                $value,
                "Error code '{$name}' with value '{$value}' is not in UPPER_SNAKE_CASE format"
            );
        }
    }

    /**
     * @test
     */
    public function error_codes_have_correct_prefixes(): void
    {
        $prefixCategories = [
            'AUTH_' => ['AUTH_TOKEN_EXPIRED', 'AUTH_TOKEN_INVALID', 'AUTH_TENANT_UNAUTHORIZED'],
            'INDEX_' => ['INDEX_DOCUMENT_NOT_FOUND', 'INDEX_CONTENT_TOO_SHORT', 'INDEX_INVALID_METADATA'],
            'RAG_' => ['RAG_NO_RELEVANT_DOCUMENTS', 'RAG_CONFIDENCE_TOO_LOW', 'RAG_LLM_UNAVAILABLE'],
            'CLASSIFY_' => ['CLASSIFY_FAILED', 'CLASSIFY_UNSUPPORTED_TYPE'],
            'VALIDATION_' => ['VALIDATION_MISSING_FIELD', 'VALIDATION_INVALID_FORMAT'],
            'CONFIDENCE_' => ['CONFIDENCE_CALIBRATION_FAILED', 'CONFIDENCE_THRESHOLD_ERROR'],
            'MONITOR_' => ['MONITOR_SERVICE_UNHEALTHY', 'MONITOR_METRICS_UNAVAILABLE'],
            'ADMIN_' => ['ADMIN_UNAUTHORIZED', 'ADMIN_INVALID_CONFIG'],
            'ORG_' => ['ORG_NOT_FOUND', 'ORG_LIMIT_EXCEEDED'],
            'REQUEST_' => ['REQUEST_INVALID_PARAMETER', 'REQUEST_VALIDATION_ERROR'],
            'SYSTEM_' => ['SYSTEM_INTERNAL_ERROR', 'SYSTEM_SERVICE_UNAVAILABLE', 'SYSTEM_TIMEOUT'],
        ];

        foreach ($prefixCategories as $prefix => $expectedCodes) {
            foreach ($expectedCodes as $code) {
                $constantExists = defined(ErrorCode::class . '::' . $code);
                $this->assertTrue(
                    $constantExists,
                    "Expected error code constant '{$code}' with prefix '{$prefix}' does not exist"
                );

                if ($constantExists) {
                    $value = constant(ErrorCode::class . '::' . $code);
                    $this->assertStringStartsWith(
                        $prefix,
                        $value,
                        "Error code '{$code}' should start with prefix '{$prefix}'"
                    );
                }
            }
        }
    }

    /**
     * @test
     */
    public function retryable_errors_are_correctly_identified(): void
    {
        $retryableCodes = [
            ErrorCode::INDEX_WEAVIATE_CONNECTION_ERROR,
            ErrorCode::RAG_LLM_UNAVAILABLE,
            ErrorCode::SYSTEM_SERVICE_UNAVAILABLE,
            ErrorCode::SYSTEM_TIMEOUT,
        ];

        foreach ($retryableCodes as $code) {
            $this->assertTrue(
                ErrorCode::isRetryable($code),
                "Error code '{$code}' should be retryable"
            );
        }

        $nonRetryableCodes = [
            ErrorCode::AUTH_TOKEN_INVALID,
            ErrorCode::VALIDATION_MISSING_FIELD,
            ErrorCode::INDEX_CONTENT_TOO_SHORT,
        ];

        foreach ($nonRetryableCodes as $code) {
            $this->assertFalse(
                ErrorCode::isRetryable($code),
                "Error code '{$code}' should not be retryable"
            );
        }
    }

    /**
     * @test
     */
    public function critical_errors_are_correctly_identified(): void
    {
        $criticalCodes = [
            ErrorCode::SYSTEM_INTERNAL_ERROR,
            ErrorCode::SYSTEM_DATABASE_ERROR,
            ErrorCode::INDEX_WEAVIATE_UNAVAILABLE,
            ErrorCode::AUTH_TENANT_DEACTIVATED,
        ];

        foreach ($criticalCodes as $code) {
            $this->assertTrue(
                ErrorCode::isCritical($code),
                "Error code '{$code}' should be critical"
            );
        }

        $nonCriticalCodes = [
            ErrorCode::RAG_NO_RELEVANT_DOCUMENTS,
            ErrorCode::VALIDATION_MISSING_FIELD,
            ErrorCode::AUTH_TOKEN_EXPIRED,
        ];

        foreach ($nonCriticalCodes as $code) {
            $this->assertFalse(
                ErrorCode::isCritical($code),
                "Error code '{$code}' should not be critical"
            );
        }
    }

    /**
     * @test
     */
    public function auth_refresh_errors_are_correctly_identified(): void
    {
        $authRefreshCodes = [
            ErrorCode::AUTH_TOKEN_EXPIRED,
            ErrorCode::AUTH_TOKEN_INVALID,
        ];

        foreach ($authRefreshCodes as $code) {
            $this->assertTrue(
                ErrorCode::needsAuthRefresh($code),
                "Error code '{$code}' should need auth refresh"
            );
        }

        $noAuthRefreshCodes = [
            ErrorCode::RAG_LLM_UNAVAILABLE,
            ErrorCode::SYSTEM_INTERNAL_ERROR,
            ErrorCode::INDEX_CONTENT_TOO_SHORT,
        ];

        foreach ($noAuthRefreshCodes as $code) {
            $this->assertFalse(
                ErrorCode::needsAuthRefresh($code),
                "Error code '{$code}' should not need auth refresh"
            );
        }
    }

    /**
     * @test
     */
    public function all_error_code_constants_are_unique(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $constants = $reflection->getConstants();

        $errorCodes = array_filter($constants, fn($value) => is_string($value));
        $uniqueCodes = array_unique($errorCodes);

        $this->assertCount(
            count($errorCodes),
            $uniqueCodes,
            'All error code values must be unique'
        );
    }

    /**
     * @test
     */
    public function constant_names_match_their_values(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $name => $value) {
            if (!is_string($value)) {
                continue;
            }

            $this->assertEquals(
                $name,
                $value,
                "Constant name '{$name}' should match its value '{$value}'"
            );
        }
    }

    /**
     * @test
     */
    public function error_code_categories_have_minimum_codes(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $constants = $reflection->getConstants();

        $categoryCounts = [
            'AUTH_' => 0,
            'INDEX_' => 0,
            'RAG_' => 0,
            'CLASSIFY_' => 0,
            'VALIDATION_' => 0,
            'CONFIDENCE_' => 0,
            'MONITOR_' => 0,
            'ADMIN_' => 0,
            'ORG_' => 0,
            'REQUEST_' => 0,
            'SYSTEM_' => 0,
        ];

        foreach ($constants as $name => $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach (array_keys($categoryCounts) as $prefix) {
                if (str_starts_with($value, $prefix)) {
                    $categoryCounts[$prefix]++;
                    break;
                }
            }
        }

        // Vérifier qu'on a au moins 3 codes par catégorie
        foreach ($categoryCounts as $category => $count) {
            $this->assertGreaterThanOrEqual(
                3,
                $count,
                "Category '{$category}' should have at least 3 error codes, found {$count}"
            );
        }
    }

    /**
     * @test
     */
    public function retryable_errors_array_contains_valid_codes(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $retryableErrors = $reflection->getConstant('RETRYABLE_ERRORS');

        $this->assertIsArray($retryableErrors, 'RETRYABLE_ERRORS should be an array');
        $this->assertNotEmpty($retryableErrors, 'RETRYABLE_ERRORS should not be empty');

        foreach ($retryableErrors as $code) {
            $constantExists = defined(ErrorCode::class . '::' . $code);
            $this->assertTrue(
                $constantExists,
                "Retryable error code '{$code}' should be a valid constant"
            );
        }
    }

    /**
     * @test
     */
    public function critical_errors_array_contains_valid_codes(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $criticalErrors = $reflection->getConstant('CRITICAL_ERRORS');

        $this->assertIsArray($criticalErrors, 'CRITICAL_ERRORS should be an array');
        $this->assertNotEmpty($criticalErrors, 'CRITICAL_ERRORS should not be empty');

        foreach ($criticalErrors as $code) {
            $constantExists = defined(ErrorCode::class . '::' . $code);
            $this->assertTrue(
                $constantExists,
                "Critical error code '{$code}' should be a valid constant"
            );
        }
    }

    /**
     * @test
     */
    public function auth_refresh_errors_array_contains_valid_codes(): void
    {
        $reflection = new \ReflectionClass(ErrorCode::class);
        $authRefreshErrors = $reflection->getConstant('AUTH_REFRESH_ERRORS');

        $this->assertIsArray($authRefreshErrors, 'AUTH_REFRESH_ERRORS should be an array');
        $this->assertNotEmpty($authRefreshErrors, 'AUTH_REFRESH_ERRORS should not be empty');

        foreach ($authRefreshErrors as $code) {
            $constantExists = defined(ErrorCode::class . '::' . $code);
            $this->assertTrue(
                $constantExists,
                "Auth refresh error code '{$code}' should be a valid constant"
            );
        }
    }
}
