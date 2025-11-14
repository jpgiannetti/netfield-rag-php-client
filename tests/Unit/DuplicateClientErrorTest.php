<?php

declare(strict_types=1);

namespace Netfield\Client\Tests\Unit;

use Netfield\Client\Exception\ErrorCode;
use Netfield\Client\Exception\NetfieldApiException;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour la gestion de l'erreur ORG_CLIENT_ALREADY_EXISTS
 */
class DuplicateClientErrorTest extends TestCase
{
    /**
     * @test
     */
    public function org_client_already_exists_error_code_exists(): void
    {
        $this->assertTrue(
            defined('Netfield\Client\Exception\ErrorCode::ORG_CLIENT_ALREADY_EXISTS'),
            'ORG_CLIENT_ALREADY_EXISTS error code should be defined'
        );
    }

    /**
     * @test
     */
    public function org_client_already_exists_has_correct_format(): void
    {
        $errorCode = ErrorCode::ORG_CLIENT_ALREADY_EXISTS;

        // Vérifie le format UPPER_SNAKE_CASE
        $this->assertMatchesRegularExpression(
            '/^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$/',
            $errorCode,
            'ORG_CLIENT_ALREADY_EXISTS should be in UPPER_SNAKE_CASE format'
        );

        // Vérifie le préfixe ORG_
        $this->assertStringStartsWith(
            'ORG_',
            $errorCode,
            'ORG_CLIENT_ALREADY_EXISTS should start with ORG_ prefix'
        );

        // Vérifie la valeur exacte
        $this->assertEquals(
            'ORG_CLIENT_ALREADY_EXISTS',
            $errorCode,
            'ORG_CLIENT_ALREADY_EXISTS constant value should match its name'
        );
    }

    /**
     * @test
     */
    public function org_client_already_exists_is_not_retryable(): void
    {
        $this->assertFalse(
            ErrorCode::isRetryable(ErrorCode::ORG_CLIENT_ALREADY_EXISTS),
            'ORG_CLIENT_ALREADY_EXISTS should not be retryable (409 Conflict is permanent)'
        );
    }

    /**
     * @test
     */
    public function org_client_already_exists_is_not_critical(): void
    {
        $this->assertFalse(
            ErrorCode::isCritical(ErrorCode::ORG_CLIENT_ALREADY_EXISTS),
            'ORG_CLIENT_ALREADY_EXISTS should not be critical (user error, not system error)'
        );
    }

    /**
     * @test
     */
    public function org_client_already_exists_does_not_need_auth_refresh(): void
    {
        $this->assertFalse(
            ErrorCode::needsAuthRefresh(ErrorCode::ORG_CLIENT_ALREADY_EXISTS),
            'ORG_CLIENT_ALREADY_EXISTS should not need auth refresh (not an auth error)'
        );
    }

    /**
     * @test
     */
    public function rag_api_exception_with_org_client_already_exists_has_correct_properties(): void
    {
        $errorData = [
            'error_code' => ErrorCode::ORG_CLIENT_ALREADY_EXISTS,
            'message' => 'Un client avec ce nom existe déjà dans cette organisation',
            'details' => [
                'client_name' => 'Test Client',
                'organization_id' => 'org-123',
            ],
            'timestamp' => '2025-10-05T14:30:00Z',
            'trace_id' => 'abc123xyz',
        ];

        $exception = new NetfieldApiException(
            message: 'Un client avec ce nom existe déjà dans cette organisation',
            code: 409,
            errorCode: ErrorCode::ORG_CLIENT_ALREADY_EXISTS,
            errorData: $errorData
        );

        // Vérifier les propriétés de base
        $this->assertEquals(409, $exception->getCode(), 'HTTP status should be 409 Conflict');
        $this->assertEquals(
            ErrorCode::ORG_CLIENT_ALREADY_EXISTS,
            $exception->getErrorCode(),
            'Error code should be ORG_CLIENT_ALREADY_EXISTS'
        );

        // Vérifier les données d'erreur
        $this->assertEquals($errorData, $exception->getErrorData(), 'Error data should match');

        // Vérifier les détails
        $details = $exception->getDetails();
        $this->assertIsArray($details, 'Details should be an array');
        $this->assertEquals('Test Client', $details['client_name'] ?? null);
        $this->assertEquals('org-123', $details['organization_id'] ?? null);

        // Vérifier les métadonnées
        $this->assertEquals('2025-10-05T14:30:00Z', $exception->getTimestamp());
        $this->assertEquals('abc123xyz', $exception->getTraceId());

        // Vérifier les helpers
        $this->assertFalse($exception->isRetryable(), 'Should not be retryable');
        $this->assertFalse($exception->isCritical(), 'Should not be critical');
        $this->assertFalse($exception->needsAuthRefresh(), 'Should not need auth refresh');
    }

    /**
     * @test
     */
    public function duplicate_client_exception_can_be_caught_and_handled(): void
    {
        $errorData = [
            'error_code' => ErrorCode::ORG_CLIENT_ALREADY_EXISTS,
            'message' => 'Un client avec ce nom existe déjà dans cette organisation',
            'details' => [
                'client_name' => 'Duplicate Client',
                'organization_id' => 'org-456',
            ],
        ];

        try {
            throw new NetfieldApiException(
                message: 'Un client avec ce nom existe déjà dans cette organisation',
                code: 409,
                errorCode: ErrorCode::ORG_CLIENT_ALREADY_EXISTS,
                errorData: $errorData
            );
        } catch (NetfieldApiException $e) {
            // Vérifier qu'on peut identifier l'erreur par son code
            $this->assertEquals(ErrorCode::ORG_CLIENT_ALREADY_EXISTS, $e->getErrorCode());

            // Vérifier qu'on peut récupérer le nom du client
            $details = $e->getDetails();
            $this->assertEquals('Duplicate Client', $details['client_name'] ?? null);

            // Vérifier qu'on peut logger l'erreur
            $logContext = [
                'error_code' => $e->getErrorCode(),
                'http_status' => $e->getCode(),
                'client_name' => $details['client_name'] ?? null,
                'organization_id' => $details['organization_id'] ?? null,
                'trace_id' => $e->getTraceId(),
            ];

            $this->assertIsArray($logContext);
            $this->assertEquals('ORG_CLIENT_ALREADY_EXISTS', $logContext['error_code']);
            $this->assertEquals(409, $logContext['http_status']);
        }
    }

    /**
     * @test
     */
    public function can_distinguish_duplicate_client_from_other_org_errors(): void
    {
        $duplicateError = ErrorCode::ORG_CLIENT_ALREADY_EXISTS;
        $otherOrgErrors = [
            ErrorCode::ORG_NOT_FOUND,
            ErrorCode::ORG_LIMIT_EXCEEDED,
            ErrorCode::ORG_CLIENT_CREATE_FAILED,
            ErrorCode::ORG_CLIENT_NOT_FOUND,
            ErrorCode::ORG_CLIENT_DEACTIVATE_FAILED,
            ErrorCode::ORG_TOKEN_VALIDATION_FAILED,
            ErrorCode::ORG_TOKEN_NOT_OWNED,
            ErrorCode::ORG_INFO_RETRIEVAL_FAILED,
        ];

        // Vérifier que le code existe
        $this->assertNotNull($duplicateError);

        // Vérifier qu'il est différent des autres codes ORG_
        foreach ($otherOrgErrors as $otherError) {
            $this->assertNotEquals(
                $duplicateError,
                $otherError,
                "ORG_CLIENT_ALREADY_EXISTS should be distinct from {$otherError}"
            );
        }

        // Vérifier qu'ils partagent tous le même préfixe
        foreach (array_merge([$duplicateError], $otherOrgErrors) as $orgError) {
            $this->assertStringStartsWith(
                'ORG_',
                $orgError,
                "All organization errors should start with ORG_ prefix"
            );
        }
    }

    /**
     * @test
     */
    public function duplicate_client_error_suggests_user_action(): void
    {
        // Simuler comment un développeur pourrait utiliser cette erreur
        $errorCode = ErrorCode::ORG_CLIENT_ALREADY_EXISTS;

        // Mapping de codes d'erreur vers des actions suggérées
        $suggestedActions = [
            ErrorCode::ORG_CLIENT_ALREADY_EXISTS => 'Veuillez choisir un nom différent pour le client',
            ErrorCode::AUTH_TOKEN_EXPIRED => 'Veuillez vous reconnecter',
            ErrorCode::SYSTEM_SERVICE_UNAVAILABLE => 'Veuillez réessayer plus tard',
        ];

        // Vérifier qu'il y a une action suggérée pour cette erreur
        $this->assertArrayHasKey(
            $errorCode,
            $suggestedActions,
            'ORG_CLIENT_ALREADY_EXISTS should have a suggested user action'
        );

        $suggestion = $suggestedActions[$errorCode];
        $this->assertStringContainsString(
            'nom différent',
            $suggestion,
            'Suggestion should guide user to choose a different name'
        );
    }
}
