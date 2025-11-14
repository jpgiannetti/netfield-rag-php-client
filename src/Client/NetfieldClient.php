<?php

declare(strict_types=1);

namespace Netfield\Client\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Netfield\Client\Auth\JwtAuthenticator;
use Netfield\Client\Exception\NetfieldApiException;
use Netfield\Client\Models\Request\AskRequest;
use Netfield\Client\Models\Request\IndexDocumentRequest;
use Netfield\Client\Models\Request\BulkIndexRequest;
use Netfield\Client\Models\Response\AskResponse;
use Netfield\Client\Models\Response\IndexResponse;
use Netfield\Client\Models\Response\BulkIndexResponse;
use Netfield\Client\Models\Response\HealthResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client RAG pour les fonctionnalités de Q&A et indexation de documents
 *
 * Ce client se concentre uniquement sur le module RAG (Retrieval-Augmented Generation):
 * - Questions/Réponses (ask, askStream)
 * - Indexation de documents (index, bulkIndex, update, delete)
 * - Configuration et monitoring RAG
 *
 * Pour les autres fonctionnalités, utilisez les clients spécialisés:
 * - DisClient: Classification de documents (DIS module)
 * - MonitoringClient: Métriques, health checks, traces
 * - ValidationClient: Validation de documents
 * - AdminClient: Gestion organisations/utilisateurs
 * - OrganizationClient: Gestion clients
 */
class NetfieldClient
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return AskResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('RAG query failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to execute RAG query: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Streaming RAG query failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to execute streaming RAG query: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Indexe un document unique
     *
     * IMPORTANT: Les métadonnées doc_type et category sont OBLIGATOIRES.
     * Vous DEVEZ utiliser le DisClient pour classifier le document avant l'indexation.
     *
     * @param IndexDocumentRequest $request Requête d'indexation avec metadata.doc_type et metadata.category
     * @return IndexResponse Réponse d'indexation
     * @throws NetfieldApiException Si doc_type ou category manquants, ou si l'indexation échoue
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return IndexResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document indexing failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to index document: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Indexation en lot
     *
     * IMPORTANT: Chaque document DOIT avoir doc_type et category dans ses métadonnées.
     * Vous DEVEZ utiliser le DisClient pour classifier chaque document avant l'indexation.
     *
     * @param BulkIndexRequest $request Requête avec liste de documents pré-classifiés
     * @return BulkIndexResponse Réponse avec statistiques d'indexation
     * @throws NetfieldApiException Si des documents n'ont pas doc_type/category, ou si l'indexation échoue
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return BulkIndexResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Bulk indexing failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to bulk index documents: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Met à jour un document
     *
     * IMPORTANT: Si vous modifiez le contenu (content), doc_type et category sont OBLIGATOIRES
     * dans les métadonnées. Utilisez le DisClient pour reclassifier le document avant la mise à jour.
     *
     * @param string $documentId ID du document à mettre à jour
     * @param IndexDocumentRequest $request Données de mise à jour
     * @return IndexResponse Réponse de mise à jour
     * @throws NetfieldApiException Si contenu modifié sans doc_type/category, ou si la mise à jour échoue
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return IndexResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document update failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to update document: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document deletion failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to delete document: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return HealthResponse::fromArray($data);
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get confidence thresholds: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Obtient des statistiques d'indexation - le tenant_id est extrait du JWT
     */
    public function getIndexingStats(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/index/stats', [
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
                'Failed to get indexing stats: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'Failed to get available models: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('RAG pipeline test failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
                'Failed to test RAG pipeline: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new NetfieldApiException(
                'RAG health check failed: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }
}
