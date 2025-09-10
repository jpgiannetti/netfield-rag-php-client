<?php

declare(strict_types=1);

namespace Netfield\RagClient;

use GuzzleHttp\Client;
use Netfield\RagClient\Auth\JwtAuthenticator;
use Netfield\RagClient\Client\RagClient;
use Psr\Log\LoggerInterface;

/**
 * Factory pour créer facilement des instances de RagClient
 */
class RagClientFactory
{
    /**
     * Crée un client RAG avec configuration par défaut
     */
    public static function create(string $baseUrl, string $jwtToken): RagClient
    {
        return new RagClient($baseUrl, $jwtToken);
    }

    /**
     * Crée un client RAG avec token de test
     */
    public static function createWithTestToken(
        string $baseUrl,
        string $tenantId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024'
    ): RagClient {
        $token = JwtAuthenticator::generateTestToken($tenantId, $secretKey);
        return new RagClient($baseUrl, $token);
    }

    /**
     * Crée un client RAG avec configuration personnalisée
     */
    public static function createCustom(
        string $baseUrl,
        string $jwtToken,
        array $httpOptions = [],
        ?LoggerInterface $logger = null
    ): RagClient {
        $httpClient = new Client($httpOptions);
        return new RagClient($baseUrl, $jwtToken, $httpClient, $logger);
    }

    /**
     * Crée un client RAG à partir des variables d'environnement
     */
    public static function createFromEnv(): RagClient
    {
        $baseUrl = $_ENV['RAG_API_URL'] ?? throw new \InvalidArgumentException('RAG_API_URL environment variable is required');
        $jwtToken = $_ENV['RAG_JWT_TOKEN'] ?? null;
        $tenantId = $_ENV['RAG_TENANT_ID'] ?? null;
        $jwtSecret = $_ENV['RAG_JWT_SECRET'] ?? 'super-secret-jwt-key-change-in-production-2024';

        if ($jwtToken) {
            return self::create($baseUrl, $jwtToken);
        }

        if ($tenantId) {
            return self::createWithTestToken($baseUrl, $tenantId, $jwtSecret);
        }

        throw new \InvalidArgumentException('Either RAG_JWT_TOKEN or RAG_TENANT_ID environment variable is required');
    }
}
