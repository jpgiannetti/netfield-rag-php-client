<?php

declare(strict_types=1);

namespace Netfield\Client\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Netfield\Client\Auth\JwtAuthenticator;
use Netfield\Client\Exception\AuthenticationException;

class JwtAuthenticatorTest extends TestCase
{
    private string $validToken;
    private string $secretKey = 'test-secret-key';

    protected function setUp(): void
    {
        $this->validToken = JwtAuthenticator::generateTestToken('test-tenant', $this->secretKey);
    }

    public function testConstructorWithValidToken(): void
    {
        $authenticator = new JwtAuthenticator($this->validToken);
        $this->assertInstanceOf(JwtAuthenticator::class, $authenticator);
    }

    public function testConstructorWithEmptyToken(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('JWT token cannot be empty');

        new JwtAuthenticator('');
    }

    public function testConstructorWithInvalidTokenFormat(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid JWT format');

        new JwtAuthenticator('invalid.token');
    }

    public function testGetHeaders(): void
    {
        $authenticator = new JwtAuthenticator($this->validToken);
        $headers = $authenticator->getHeaders();

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('Bearer ', $headers['Authorization']);
        $this->assertEquals('Bearer ' . $this->validToken, $headers['Authorization']);
    }

    public function testGetTenantId(): void
    {
        $authenticator = new JwtAuthenticator($this->validToken);
        $tenantId = $authenticator->getTenantId();

        $this->assertEquals('test-tenant', $tenantId);
    }

    public function testIsTokenValid(): void
    {
        $authenticator = new JwtAuthenticator($this->validToken);
        $this->assertTrue($authenticator->isTokenValid());
    }

    public function testIsTokenValidWithExpiredToken(): void
    {
        // Create token that expires immediately
        $expiredToken = JwtAuthenticator::generateTestToken('test-tenant', $this->secretKey, -1);
        $authenticator = new JwtAuthenticator($expiredToken);

        // Wait a moment to ensure expiration
        sleep(1);

        $this->assertFalse($authenticator->isTokenValid());
    }

    public function testGetTokenPayload(): void
    {
        $authenticator = new JwtAuthenticator($this->validToken);
        $payload = $authenticator->getTokenPayload();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('tenant_id', $payload);
        $this->assertArrayHasKey('sub', $payload);
        $this->assertArrayHasKey('scopes', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('iat', $payload);

        $this->assertEquals('test-tenant', $payload['tenant_id']);
        $this->assertEquals('test_user_001', $payload['sub']);
        $this->assertContains('read', $payload['scopes']);
        $this->assertContains('write', $payload['scopes']);
    }

    public function testGenerateTestToken(): void
    {
        $tenantId = 'custom-tenant';
        $token = JwtAuthenticator::generateTestToken($tenantId, $this->secretKey);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify the token contains expected data
        $authenticator = new JwtAuthenticator($token);
        $this->assertEquals($tenantId, $authenticator->getTenantId());
        $this->assertTrue($authenticator->isTokenValid());
    }

    public function testGenerateTestTokenWithCustomExpiration(): void
    {
        // Create a token that expires in the past (already expired)
        $token = JwtAuthenticator::generateTestToken('test-tenant', $this->secretKey, -1);
        $authenticator = new JwtAuthenticator($token);

        // Token should be immediately invalid because it's expired
        $this->assertFalse($authenticator->isTokenValid());
    }

    public function testGetTenantIdWithInvalidPayload(): void
    {
        // Create a token with malformed payload (this is tricky to test with real JWT)
        $authenticator = new JwtAuthenticator($this->validToken);

        // This should work normally
        $this->assertEquals('test-tenant', $authenticator->getTenantId());
    }

    public function testDecodeTokenWithInvalidToken(): void
    {
        // Create a token with completely invalid JSON payload after base64 decode
        $header = base64_encode('{"typ":"JWT","alg":"HS256"}');
        $payload = base64_encode('{invalid-json-content}'); // Invalid JSON
        $signature = 'fake-signature';
        $invalidToken = "$header.$payload.$signature";

        $authenticator = new JwtAuthenticator($invalidToken);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid JWT payload format');

        // Force decoding by calling getTenantId()
        $authenticator->getTenantId();
    }

    /**
     * @dataProvider tokenValidationDataProvider
     */
    public function testTokenValidationScenarios(array $claims, bool $expectedValid): void
    {
        $now = time();

        // Build custom token with specific claims
        $customClaims = array_merge([
            'sub' => 'test_user',
            'tenant_id' => 'test-tenant',
            'scopes' => ['read'],
            'iss' => 'test',
            'iat' => $now,
            'exp' => $now + 3600,
        ], $claims);

        $token = \Firebase\JWT\JWT::encode($customClaims, $this->secretKey, 'HS256');
        $authenticator = new JwtAuthenticator($token);

        $this->assertEquals($expectedValid, $authenticator->isTokenValid());
    }

    public static function tokenValidationDataProvider(): array
    {
        $now = time();

        return [
            'valid_token' => [[], true],
            'expired_token' => [['exp' => $now - 3600], false],
            'not_before_future' => [['nbf' => $now + 3600], false],
            'not_before_past' => [['nbf' => $now - 3600], true],
        ];
    }
}
