<?php

declare(strict_types=1);

namespace Netfield\Client;

use GuzzleHttp\Client;
use Netfield\Client\Auth\JwtAuthenticator;
use Netfield\Client\Client\NetfieldClient;
use Netfield\Client\Client\DisClient;
use Netfield\Client\Client\AdminClient;
use Netfield\Client\Client\OrganizationClient;
use Netfield\Client\Client\MonitoringClient;
use Netfield\Client\Client\ValidationClient;
use Psr\Log\LoggerInterface;

/**
 * Factory pour créer facilement des instances de NetfieldClient
 */
class NetfieldClientFactory
{
    /**
     * Crée un client Netfield avec configuration par défaut
     */
    public static function create(string $baseUrl, string $jwtToken): NetfieldClient
    {
        return new NetfieldClient($baseUrl, $jwtToken);
    }

    /**
     * Crée un client Netfield avec token de test
     */
    public static function createWithTestToken(
        string $baseUrl,
        string $tenantId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024'
    ): NetfieldClient {
        $token = JwtAuthenticator::generateTestToken($tenantId, $secretKey);
        return new NetfieldClient($baseUrl, $token);
    }

    /**
     * Crée un client Netfield avec configuration personnalisée
     */
    public static function createCustom(
        string $baseUrl,
        string $jwtToken,
        array $httpOptions = [],
        ?LoggerInterface $logger = null
    ): NetfieldClient {
        $httpClient = new Client($httpOptions);
        return new NetfieldClient($baseUrl, $jwtToken, $httpClient, $logger);
    }

    /**
     * Crée un client Netfield à partir des variables d'environnement
     */
    public static function createFromEnv(): NetfieldClient
    {
        $baseUrl = $_ENV['NETFIELD_API_URL'] ?? throw new \InvalidArgumentException('NETFIELD_API_URL environment variable is required');
        $jwtToken = $_ENV['NETFIELD_JWT_TOKEN'] ?? null;
        $tenantId = $_ENV['NETFIELD_TENANT_ID'] ?? null;
        $jwtSecret = $_ENV['NETFIELD_JWT_SECRET'] ?? 'super-secret-jwt-key-change-in-production-2024';

        if ($jwtToken) {
            return self::create($baseUrl, $jwtToken);
        }

        if ($tenantId) {
            return self::createWithTestToken($baseUrl, $tenantId, $jwtSecret);
        }

        throw new \InvalidArgumentException('Either NETFIELD_JWT_TOKEN or NETFIELD_TENANT_ID environment variable is required');
    }

    /**
     * Crée un client Admin pour la gestion des organizations
     */
    public static function createAdminClient(string $baseUrl, string $adminJwtToken): AdminClient
    {
        return new AdminClient($baseUrl, $adminJwtToken);
    }

    /**
     * Crée un client Admin avec token de test
     */
    public static function createAdminWithTestToken(
        string $baseUrl,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024'
    ): AdminClient {
        $token = JwtAuthenticator::generateAdminTestToken($secretKey);
        return new AdminClient($baseUrl, $token);
    }

    /**
     * Crée un client Organization pour la gestion des clients
     */
    public static function createOrganizationClient(string $baseUrl, string $organizationJwtToken): OrganizationClient
    {
        return new OrganizationClient($baseUrl, $organizationJwtToken);
    }

    /**
     * Crée un client Organization avec token de test
     */
    public static function createOrganizationWithTestToken(
        string $baseUrl,
        string $organizationId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024'
    ): OrganizationClient {
        $token = JwtAuthenticator::generateOrganizationTestToken($organizationId, $secretKey);
        return new OrganizationClient($baseUrl, $token);
    }

    /**
     * Crée un client DIS (Document Intelligence Service) pour la classification
     */
    public static function createDisClient(string $baseUrl, string $jwtToken): DisClient
    {
        $authenticator = new JwtAuthenticator($jwtToken);
        return new DisClient($baseUrl, $authenticator);
    }

    /**
     * Crée un client DIS avec token de test
     */
    public static function createDisWithTestToken(
        string $baseUrl,
        string $tenantId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024'
    ): DisClient {
        $token = JwtAuthenticator::generateTestToken($tenantId, $secretKey);
        $authenticator = new JwtAuthenticator($token);
        return new DisClient($baseUrl, $authenticator);
    }

    /**
     * Crée un client DIS avec configuration personnalisée
     */
    public static function createDisCustom(
        string $baseUrl,
        string $jwtToken,
        array $httpOptions = [],
        ?LoggerInterface $logger = null
    ): DisClient {
        $httpClient = new Client($httpOptions);
        $authenticator = new JwtAuthenticator($jwtToken);
        return new DisClient($baseUrl, $authenticator, $httpClient, $logger);
    }

    /**
     * Crée un client Monitoring pour les métriques, health checks et traces
     */
    public static function createMonitoringClient(string $baseUrl, string $jwtToken): MonitoringClient
    {
        return new MonitoringClient($baseUrl, $jwtToken);
    }

    /**
     * Crée un client Monitoring avec token de test
     */
    public static function createMonitoringWithTestToken(
        string $baseUrl,
        string $tenantId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024'
    ): MonitoringClient {
        $token = JwtAuthenticator::generateTestToken($tenantId, $secretKey);
        return new MonitoringClient($baseUrl, $token);
    }

    /**
     * Crée un client Monitoring avec configuration personnalisée
     */
    public static function createMonitoringCustom(
        string $baseUrl,
        string $jwtToken,
        array $httpOptions = [],
        ?LoggerInterface $logger = null
    ): MonitoringClient {
        $httpClient = new Client($httpOptions);
        return new MonitoringClient($baseUrl, $jwtToken, $httpClient, $logger);
    }

    /**
     * Crée un client Validation pour la validation de documents
     */
    public static function createValidationClient(string $baseUrl, string $jwtToken): ValidationClient
    {
        return new ValidationClient($baseUrl, $jwtToken);
    }

    /**
     * Crée un client Validation avec token de test
     */
    public static function createValidationWithTestToken(
        string $baseUrl,
        string $tenantId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024'
    ): ValidationClient {
        $token = JwtAuthenticator::generateTestToken($tenantId, $secretKey);
        return new ValidationClient($baseUrl, $token);
    }

    /**
     * Crée un client Validation avec configuration personnalisée
     */
    public static function createValidationCustom(
        string $baseUrl,
        string $jwtToken,
        array $httpOptions = [],
        ?LoggerInterface $logger = null
    ): ValidationClient {
        $httpClient = new Client($httpOptions);
        return new ValidationClient($baseUrl, $jwtToken, $httpClient, $logger);
    }
}
