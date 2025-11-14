<?php

declare(strict_types=1);

namespace Netfield\Client\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Netfield\Client\Client\AdminClient;
use Netfield\Client\Models\Request\CreateOrganizationRequest;
use Netfield\Client\Models\Response\OrganizationTokenResponse;
use Netfield\Client\Auth\JwtAuthenticator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class AdminClientTest extends TestCase
{
    private AdminClient $adminClient;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $token = JwtAuthenticator::generateAdminTestToken();
        $this->adminClient = new AdminClient('http://localhost:8888', $token, $httpClient);
    }

    public function testCreateOrganization(): void
    {
        $responseData = [
            'organization_id' => 'org_123',
            'token' => 'jwt_token_here',
            'expires_in' => 3600,
            'token_type' => 'Bearer'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $request = new CreateOrganizationRequest(
            'Test Organization',
            'test@example.com',
            'Test description',
            50,
            ['read', 'write']
        );

        $response = $this->adminClient->createOrganization($request);

        $this->assertInstanceOf(OrganizationTokenResponse::class, $response);
        $this->assertEquals('org_123', $response->getOrganizationId());
        $this->assertEquals('jwt_token_here', $response->getToken());
        $this->assertEquals(3600, $response->getExpiresIn());
    }

    public function testListOrganizations(): void
    {
        $responseData = [
            'organizations' => [
                [
                    'organization_id' => 'org_123',
                    'name' => 'Test Org',
                    'contact_email' => 'test@example.com',
                    'is_active' => true,
                    'created_at' => '2023-01-01T00:00:00Z'
                ]
            ],
            'total' => 1
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->adminClient->listOrganizations();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['organizations']);
        $this->assertEquals('org_123', $result['organizations'][0]['organization_id']);
        $this->assertEquals(1, $result['total']);
    }

    public function testDeactivateOrganization(): void
    {
        $responseData = [
            'status' => 'success',
            'message' => 'Organization deactivated successfully'
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->adminClient->deactivateOrganization('org_123');

        $this->assertIsArray($result);
        $this->assertEquals('success', $result['status']);
    }

    public function testGetAdminStatus(): void
    {
        $responseData = [
            'status' => 'healthy',
            'version' => '1.0.0',
            'uptime' => 3600
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->adminClient->getAdminStatus();

        $this->assertIsArray($result);
        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals('1.0.0', $result['version']);
    }
}
