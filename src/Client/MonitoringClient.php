<?php

declare(strict_types=1);

namespace Netfield\Client\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Netfield\Client\Auth\JwtAuthenticator;
use Netfield\Client\Exception\NetfieldApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MonitoringClient
{
    use ErrorMessageExtractorTrait;

    private Client $httpClient;
    private JwtAuthenticator $authenticator;
    private LoggerInterface $logger;
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        string $jwtToken,
        ?Client $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
        $this->authenticator = new JwtAuthenticator($jwtToken);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Vérifie l'état de santé du service (health check global)
     */
    public function health(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/health');
            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Health check failed: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Health check détaillé avec métriques système
     */
    public function getDetailedHealthCheck(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/monitoring/health/detailed', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get detailed health check: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère les métriques Prometheus
     */
    public function getPrometheusMetrics(): string
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/monitoring/metrics', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get Prometheus metrics: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère les informations d'une trace spécifique
     */
    public function getTraceInfo(string $traceId): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/monitoring/traces/' . urlencode($traceId), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get trace info: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Résumé des performances du système RAG
     */
    public function getPerformanceSummary(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/monitoring/performance/summary', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get performance summary: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Test des alertes de monitoring
     */
    public function testMonitoringAlert(string $alertType): array
    {
        try {
            $this->logger->info('Testing monitoring alert', ['alert_type' => $alertType]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/monitoring/alerts/test?alert_type=' . urlencode($alertType), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Alert test failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to test monitoring alert: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Statut système global
     */
    public function getSystemStatus(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/monitoring/system/status', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get system status: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère les paramètres UI pour la gestion de confiance
     */
    public function getUISettings(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/confidence/ui-settings', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get UI settings: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère les informations sur le modèle de calibration de confiance
     */
    public function getCalibrationInfo(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/confidence/calibration-info', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get calibration info: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Valide la confiance d'une réponse donnée
     */
    public function validateResponseConfidence(array $responseData): array
    {
        try {
            $this->logger->info('Validating response confidence');

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/confidence/validate-response', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $responseData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Confidence validation failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to validate response confidence: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Récupère les métriques de confiance
     */
    public function getConfidenceMetrics(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/confidence/metrics', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get confidence metrics: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }
}
