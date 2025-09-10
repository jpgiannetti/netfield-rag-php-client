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
            $this->logger->error('RAG query failed', ['error' => $e->getMessage()]);
            throw new RagApiException('Failed to execute RAG query: ' . $e->getMessage(), $e->getCode(), $e);
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
            $this->logger->error('Streaming RAG query failed', ['error' => $e->getMessage()]);
            throw new RagApiException('Failed to execute streaming RAG query: ' . $e->getMessage(), $e->getCode(), $e);
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
            $this->logger->error('Document indexing failed', ['error' => $e->getMessage()]);
            throw new RagApiException('Failed to index document: ' . $e->getMessage(), $e->getCode(), $e);
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
            $this->logger->error('Bulk indexing failed', ['error' => $e->getMessage()]);
            throw new RagApiException('Failed to bulk index documents: ' . $e->getMessage(), $e->getCode(), $e);
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
            $this->logger->error('Document update failed', ['error' => $e->getMessage()]);
            throw new RagApiException('Failed to update document: ' . $e->getMessage(), $e->getCode(), $e);
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
            $this->logger->error('Document deletion failed', ['error' => $e->getMessage()]);
            throw new RagApiException('Failed to delete document: ' . $e->getMessage(), $e->getCode(), $e);
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
            throw new RagApiException('Health check failed: ' . $e->getMessage(), $e->getCode(), $e);
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
            throw new RagApiException('Failed to get confidence thresholds: ' . $e->getMessage(), $e->getCode(), $e);
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
            throw new RagApiException('Failed to get indexing stats: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
