<?php

declare(strict_types=1);

namespace Netfield\Client\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Netfield\Client\Exception\AuthenticationException;

class JwtAuthenticator
{
    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->validateToken();
    }

    /**
     * Retourne les headers d'authentification pour les requêtes HTTP
     */
    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    /**
     * Extrait le tenant_id du JWT token
     */
    public function getTenantId(): ?string
    {
        try {
            $payload = $this->decodeToken();
            return $payload['tenant_id'] ?? $payload['sub'] ?? null;
        } catch (\Exception $e) {
            throw new AuthenticationException('Cannot extract tenant_id from token: ' . $e->getMessage());
        }
    }

    /**
     * Vérifie si le token est valide (non expiré)
     */
    public function isTokenValid(): bool
    {
        try {
            $payload = $this->decodeToken();
            $now = time();

            if (isset($payload['exp']) && $payload['exp'] < $now) {
                return false;
            }

            if (isset($payload['nbf']) && $payload['nbf'] > $now) {
                return false;
            }

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Récupère le payload complet du JWT
     */
    public function getTokenPayload(): array
    {
        return $this->decodeToken();
    }

    /**
     * Génère un token de test (à des fins de développement)
     */
    public static function generateTestToken(
        string $tenantId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024',
        int $expirationHours = 24
    ): string {
        $now = time();
        $exp = $now + ($expirationHours * 3600);

        $payload = [
            'sub' => 'test_user_001',
            'tenant_id' => $tenantId,
            'scopes' => ['read', 'write', 'admin'],
            'confidentiality_levels' => ['public', 'internal'],
            'department' => 'IT',
            'iss' => 'client-system',
            'iat' => $now,
            'exp' => $exp,
        ];

        return JWT::encode($payload, $secretKey, 'HS256');
    }

    /**
     * Génère un token admin de test
     */
    public static function generateAdminTestToken(
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024',
        int $expirationHours = 24
    ): string {
        $now = time();
        $exp = $now + ($expirationHours * 3600);

        $payload = [
            'sub' => 'admin_user_001',
            'user_type' => 'admin',
            'scopes' => ['read', 'write', 'admin'],
            'confidentiality_levels' => ['public', 'internal', 'confidential', 'secret'],
            'department' => 'IT',
            'iss' => 'admin-system',
            'iat' => $now,
            'exp' => $exp,
        ];

        return JWT::encode($payload, $secretKey, 'HS256');
    }

    /**
     * Génère un token organization de test
     */
    public static function generateOrganizationTestToken(
        string $organizationId,
        string $secretKey = 'super-secret-jwt-key-change-in-production-2024',
        int $expirationHours = 24
    ): string {
        $now = time();
        $exp = $now + ($expirationHours * 3600);

        $payload = [
            'sub' => 'organization_' . $organizationId,
            'organization_id' => $organizationId,
            'user_type' => 'organization',
            'scopes' => ['read', 'write', 'manage_clients'],
            'confidentiality_levels' => ['public', 'internal'],
            'iss' => 'organization-system',
            'iat' => $now,
            'exp' => $exp,
        ];

        return JWT::encode($payload, $secretKey, 'HS256');
    }

    private function validateToken(): void
    {
        if (empty($this->token)) {
            throw new AuthenticationException('JWT token cannot be empty');
        }

        $parts = explode('.', $this->token);
        if (count($parts) !== 3) {
            throw new AuthenticationException('Invalid JWT format');
        }
    }

    private function decodeToken(): array
    {
        try {
            $parts = explode('.', $this->token);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AuthenticationException('Invalid JWT payload format');
            }

            return $payload;
        } catch (\Exception $e) {
            throw new AuthenticationException('Cannot decode JWT token: ' . $e->getMessage());
        }
    }
}
