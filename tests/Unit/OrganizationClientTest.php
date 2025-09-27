<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\Client\OrganizationClient;
use Netfield\RagClient\Models\Request\CreateClientTokenRequest;
use Netfield\RagClient\Models\Response\ClientTokenResponse;
use Netfield\RagClient\Auth\JwtAuthenticator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class OrganizationClientTest extends TestCase
{
    private OrganizationClient $organizationClient;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $token = JwtAuthenticator::generateOrganizationTestToken('org_123');
        $this->organizationClient = new OrganizationClient('http://localhost:8888', $token, $httpClient);
    }

    public function testCreateClientToken(): void
    {
        $responseData = [
            'client_id' => 'client_456',
            'client_name' => 'Test Client',
            'token' => 'client_jwt_token_here',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scopes' => ['read', 'write'],
            'confidentiality_levels' => ['public', 'internal']
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $request = new CreateClientTokenRequest(
            'Test Client',
            ['read', 'write'],
            ['public', 'internal'],
            'Test client description',
            365
        );

        $response = $this->organizationClient->createClientToken($request);

        $this->assertInstanceOf(ClientTokenResponse::class, $response);
        $this->assertEquals('client_456', $response->getClientId());
        $this->assertEquals('Test Client', $response->getClientName());
        $this->assertEquals('client_jwt_token_here', $response->getToken());
        $this->assertEquals(3600, $response->getExpiresIn());
        $this->assertEquals(['read', 'write'], $response->getScopes());
    }

    public function testListMyClients(): void
    {
        $responseData = [
            'organization_id' => 'org_123',
            'clients' => [
                [
                    'client_id' => 'client_456',
                    'client_name' => 'Test Client',
                    'client_description' => 'Test description',
                    'scopes' => ['read', 'write'],
                    'confidentiality_levels' => ['public'],
                    'is_active' => true,
                    'created_at' => '2023-01-01T00:00:00Z',
                    'last_used_at' => null,
                    'metadata' => null
                ]
            ],
            'total' => 1
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->organizationClient->listMyClients();

        $this->assertIsArray($result);
        $this->assertEquals('org_123', $result['organization_id']);
        $this->assertCount(1, $result['clients']);
        $this->assertEquals('client_456', $result['clients'][0]['client_id']);
        $this->assertEquals(1, $result['total']);
    }

    public function testDeactivateClient(): void
    {
        $responseData = [
            'status' => 'success',
            'message' => 'Client deactivated successfully'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->organizationClient->deactivateClient('client_456');

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
    }

    public function testGetOrganizationInfo(): void
    {
        $responseData = [
            'organization_id' => 'org_123',
            'name' => 'Test Organization',
            'description' => 'Test description',
            'contact_email' => 'test@example.com',
            'max_clients' => 100,
            'allowed_scopes' => ['read', 'write'],
            'is_active' => true
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->organizationClient->getOrganizationInfo();

        $this->assertIsArray($result);
        $this->assertEquals('org_123', $result['organization_id']);
        $this->assertEquals('Test Organization', $result['name']);
        $this->assertEquals('test@example.com', $result['contact_email']);
    }

    public function testValidateClientToken(): void
    {
        $responseData = [
            'valid' => true,
            'client_id' => 'client_456',
            'expires_at' => '2024-01-01T00:00:00Z'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $tokenData = ['token' => 'some_jwt_token'];
        $result = $this->organizationClient->validateClientToken($tokenData);

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertEquals('client_456', $result['client_id']);
    }
}