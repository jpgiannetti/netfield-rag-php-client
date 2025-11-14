<?php

declare(strict_types=1);

namespace Netfield\Client\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Netfield\Client\Auth\JwtAuthenticator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuthenticatorExtendedTest extends TestCase
{
    public function testGenerateAdminTestToken(): void
    {
        $token = JwtAuthenticator::generateAdminTestToken();

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Decode and verify the token
        $payload = JWT::decode($token, new Key('super-secret-jwt-key-change-in-production-2024', 'HS256'));
        $payloadArray = (array) $payload;

        $this->assertEquals('admin_user_001', $payloadArray['sub']);
        $this->assertEquals('admin', $payloadArray['user_type']);
        $this->assertContains('admin', $payloadArray['scopes']);
        $this->assertEquals('admin-system', $payloadArray['iss']);
    }

    public function testGenerateOrganizationTestToken(): void
    {
        $organizationId = 'org_test_123';
        $token = JwtAuthenticator::generateOrganizationTestToken($organizationId);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Decode and verify the token
        $payload = JWT::decode($token, new Key('super-secret-jwt-key-change-in-production-2024', 'HS256'));
        $payloadArray = (array) $payload;

        $this->assertEquals('organization_' . $organizationId, $payloadArray['sub']);
        $this->assertEquals($organizationId, $payloadArray['organization_id']);
        $this->assertEquals('organization', $payloadArray['user_type']);
        $this->assertContains('manage_clients', $payloadArray['scopes']);
        $this->assertEquals('organization-system', $payloadArray['iss']);
    }

    public function testAdminTokenWithCustomExpiration(): void
    {
        $token = JwtAuthenticator::generateAdminTestToken('custom-secret', 48);

        $payload = JWT::decode($token, new Key('custom-secret', 'HS256'));
        $payloadArray = (array) $payload;

        $expectedExp = time() + (48 * 3600);
        $this->assertGreaterThanOrEqual($expectedExp - 10, $payloadArray['exp']);
        $this->assertLessThanOrEqual($expectedExp + 10, $payloadArray['exp']);
    }

    public function testOrganizationTokenWithCustomSecret(): void
    {
        $customSecret = 'my-custom-secret-key';
        $organizationId = 'org_custom_456';

        $token = JwtAuthenticator::generateOrganizationTestToken($organizationId, $customSecret);

        $payload = JWT::decode($token, new Key($customSecret, 'HS256'));
        $payloadArray = (array) $payload;

        $this->assertEquals($organizationId, $payloadArray['organization_id']);
        $this->assertContains('read', $payloadArray['scopes']);
        $this->assertContains('write', $payloadArray['scopes']);
        $this->assertContains('manage_clients', $payloadArray['scopes']);
    }

    public function testTokenExpirationValidation(): void
    {
        // Generate an already expired token
        $now = time();
        $exp = $now - 3600; // 1 hour ago

        $payload = [
            'sub' => 'test_user',
            'tenant_id' => 'test_tenant',
            'iat' => $now - 7200,
            'exp' => $exp,
        ];

        $expiredToken = JWT::encode($payload, 'test-secret', 'HS256');
        $authenticator = new JwtAuthenticator($expiredToken);

        $this->assertFalse($authenticator->isTokenValid());
    }

    public function testTokenWithFutureNotBefore(): void
    {
        // Generate a token that's not valid yet
        $now = time();
        $nbf = $now + 3600; // 1 hour from now

        $payload = [
            'sub' => 'test_user',
            'tenant_id' => 'test_tenant',
            'iat' => $now,
            'nbf' => $nbf,
            'exp' => $now + 7200,
        ];

        $futureToken = JWT::encode($payload, 'test-secret', 'HS256');
        $authenticator = new JwtAuthenticator($futureToken);

        $this->assertFalse($authenticator->isTokenValid());
    }

    public function testGetTokenPayloadForDifferentTokenTypes(): void
    {
        // Test admin token
        $adminToken = JwtAuthenticator::generateAdminTestToken();
        $adminAuth = new JwtAuthenticator($adminToken);
        $adminPayload = $adminAuth->getTokenPayload();

        $this->assertEquals('admin', $adminPayload['user_type']);
        $this->assertEquals('admin_user_001', $adminPayload['sub']);

        // Test organization token
        $orgToken = JwtAuthenticator::generateOrganizationTestToken('org_test');
        $orgAuth = new JwtAuthenticator($orgToken);
        $orgPayload = $orgAuth->getTokenPayload();

        $this->assertEquals('organization', $orgPayload['user_type']);
        $this->assertEquals('org_test', $orgPayload['organization_id']);

        // Test client token
        $clientToken = JwtAuthenticator::generateTestToken('client_test');
        $clientAuth = new JwtAuthenticator($clientToken);
        $clientPayload = $clientAuth->getTokenPayload();

        $this->assertEquals('client_test', $clientPayload['tenant_id']);
        $this->assertEquals('test_user_001', $clientPayload['sub']);
    }
}
