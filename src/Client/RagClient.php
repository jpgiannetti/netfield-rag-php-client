<?php

declare(strict_types=1);

namespace Netfield\RagClient\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Netfield\RagClient\Auth\JwtAuthenticator;
use Netfield\RagClient\Exception\RagApiException;
use Netfield\RagClient\Models\Request\AskRequest;
use Netfield\RagClient\Models\Request\IndexDocumentRequest;
use Netfield\RagClient\Models\Request\BulkIndexRequest;
use Netfield\RagClient\Models\Response\AskResponse;
use Netfield\RagClient\Models\Response\IndexResponse;
use Netfield\RagClient\Models\Response\BulkIndexResponse;
use Netfield\RagClient\Models\Response\HealthResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RagClient
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
     * Pose une question au système RAG
     */
    public function ask(AskRequest $request): AskResponse
    {
        try {
            $this->logger->info('Sending RAG query', ['question' => substr($request->getQuestion(), 0, 100)]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/ask', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return AskResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('RAG query failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to execute RAG query: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Demande en streaming (SSE)
     */
    public function askStream(AskRequest $request): \Generator
    {
        try {
            $this->logger->info('Sending streaming RAG query', ['question' => substr($request->getQuestion(), 0, 100)]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/stream/ask', [
                'headers' => array_merge($this->authenticator->getHeaders(), [
                    'Accept' => 'text/event-stream',
                ]),
                'json' => $request->toArray(),
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $chunk = $body->read(1024);
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    if (strpos($event, 'data: ') === 0) {
                        $data = substr($event, 6);
                        if ($data === '[DONE]') {
                            return;
                        }

                        $decoded = json_decode($data, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            yield $decoded;
                        }
                    }
                }
            }
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Streaming RAG query failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to execute streaming RAG query: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Indexe un document unique
     */
    public function indexDocument(IndexDocumentRequest $request): IndexResponse
    {
        try {
            $this->logger->info('Indexing document', ['document_id' => $request->getDocumentId()]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/index', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return IndexResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Document indexing failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to index document: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Indexation en lot
     */
    public function bulkIndexDocuments(BulkIndexRequest $request): BulkIndexResponse
    {
        try {
            $this->logger->info('Bulk indexing documents', ['count' => count($request->getDocuments())]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/bulk-index', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return BulkIndexResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Bulk indexing failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to bulk index documents: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Met à jour un document
     */
    public function updateDocument(string $documentId, IndexDocumentRequest $request): IndexResponse
    {
        try {
            $this->logger->info('Updating document', ['document_id' => $documentId]);

            $response = $this->httpClient->put($this->baseUrl . '/api/v1/index/' . urlencode($documentId), [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return IndexResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Document update failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to update document: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Supprime un document
     */
    public function deleteDocument(string $documentId): array
    {
        try {
            $this->logger->info('Deleting document', ['document_id' => $documentId]);

            $response = $this->httpClient->delete($this->baseUrl . '/api/v1/index/' . urlencode($documentId), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Document deletion failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to delete document: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Vérifie l'état de santé du service
     */
    public function health(): HealthResponse
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/health');
            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return HealthResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Health check failed: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère les seuils de confiance
     */
    public function getConfidenceThresholds(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/confidence/thresholds', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get confidence thresholds: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Obtient des statistiques d'indexation
     */
    public function getIndexingStats(string $tenantId): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/index/stats/' . urlencode($tenantId), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get indexing stats: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Valide des documents sans les indexer (dry-run)
     */
    public function validateDocuments(BulkIndexRequest $request): array
    {
        try {
            $this->logger->info('Validating documents', ['count' => count($request->getDocuments())]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/index/validate', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Document validation failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to validate documents: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Classifie automatiquement un document
     */
    public function classifyDocument(string $content, ?string $title = null): array
    {
        try {
            $this->logger->info('Classifying document', ['content_length' => strlen($content)]);

            $payload = ['content' => $content];
            if ($title !== null) {
                $payload['title'] = $title;
            }

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/classification/classify', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Document classification failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to classify document: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Extrait les métadonnées pour un type de document donné
     */
    public function extractMetadata(string $content, string $docType): array
    {
        try {
            $this->logger->info('Extracting metadata', ['doc_type' => $docType]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/classification/extract-metadata', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => [
                    'content' => $content,
                    'doc_type' => $docType,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Metadata extraction failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to extract metadata: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère les informations sur la taxonomie
     */
    public function getTaxonomyInfo(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/classification/taxonomy', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get taxonomy info: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère les champs filtrables pour un type de document
     */
    public function getFilterableFields(string $docType): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/classification/filterable-fields/' . urlencode($docType), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get filterable fields: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère la définition des champs de métadonnées communs
     */
    public function getCommonMetadataFields(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/classification/common-metadata', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get common metadata fields: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère le rapport de validation d'un document
     */
    public function getDocumentValidationReport(string $documentId): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/validation/report/' . urlencode($documentId), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get document validation report: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère un résumé des validations
     */
    public function getValidationSummary(int $daysBack = 30): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/validation/summary?days_back=' . $daysBack, [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get validation summary: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Recherche les documents avec erreurs de validation
     */
    public function queryValidationReports(array $filters = []): array
    {
        try {
            $this->logger->info('Querying validation reports', ['filters' => $filters]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/validation/query', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $filters,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Validation query failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to query validation reports: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère les erreurs par champ
     */
    public function getErrorsByField(?string $docType = null, int $limit = 10): array
    {
        try {
            $queryParams = ['limit' => $limit];
            if ($docType !== null) {
                $queryParams['doc_type'] = $docType;
            }

            $response = $this->httpClient->get($this->baseUrl . '/api/v1/validation/errors/by-field?' . http_build_query($queryParams), [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get errors by field: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Nettoie les anciens rapports de validation (admin uniquement)
     */
    public function cleanupOldReports(int $daysToKeep = 90): array
    {
        try {
            $this->logger->info('Cleaning up old validation reports', ['days_to_keep' => $daysToKeep]);

            $response = $this->httpClient->delete($this->baseUrl . '/api/v1/validation/cleanup?days_to_keep=' . $daysToKeep, [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Cleanup failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to cleanup old reports: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get UI settings: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get calibration info: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Confidence validation failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to validate response confidence: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get confidence metrics: ' . $errorMessage, $e->getCode(), $e);
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
            throw new RagApiException('Failed to get Prometheus metrics: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get detailed health check: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get trace info: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get performance summary: ' . $errorMessage, $e->getCode(), $e);
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('Alert test failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to test monitoring alert: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Statut système global (sans authentification pour monitoring externe)
     */
    public function getSystemStatus(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/monitoring/system/status', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get system status: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Récupère la liste des modèles disponibles dans Ollama
     */
    public function getAvailableModels(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/ask/models', [
                'headers' => $this->authenticator->getHeaders(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('Failed to get available models: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Test du pipeline RAG complet avec informations de debug
     */
    public function testRagPipeline(AskRequest $request): array
    {
        try {
            $this->logger->info('Testing RAG pipeline', ['question' => substr($request->getQuestion(), 0, 100)]);

            $response = $this->httpClient->post($this->baseUrl . '/api/v1/ask/test', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $request->toArray(),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('RAG pipeline test failed', ['error' => $errorMessage]);
            throw new RagApiException('Failed to test RAG pipeline: ' . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Vérifie l'état de santé du système RAG complet
     */
    public function ragHealthCheck(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/ask/health');
            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            throw new RagApiException('RAG health check failed: ' . $errorMessage, $e->getCode(), $e);
        }
    }
}
