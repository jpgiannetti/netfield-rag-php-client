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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('RAG query failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
            throw new RagApiException(
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
     * Vous DEVEZ appeler classifyDocument() avant cette méthode, ou utiliser
     * classifyAndIndexDocument() qui effectue les deux opérations.
     *
     * @param IndexDocumentRequest $request Requête d'indexation avec metadata.doc_type et metadata.category
     * @return IndexResponse Réponse d'indexation
     * @throws RagApiException Si doc_type ou category manquants, ou si l'indexation échoue
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document indexing failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
     * Classifie ET indexe un document en une seule opération (helper method)
     *
     * Workflow complet en 2 étapes automatiques:
     * 1. Classification via DIS pour obtenir doc_type et category
     * 2. Indexation avec métadonnées enrichies
     *
     * @param IndexDocumentRequest $request Requête d'indexation (doc_type/category seront ajoutés automatiquement)
     * @return array Résultat avec:
     *   - classification: Résultat de la classification
     *   - indexation: IndexResponse de l'indexation
     *
     * @throws RagApiException Si la classification ou l'indexation échoue
     */
    public function classifyAndIndexDocument(IndexDocumentRequest $request): array
    {
        try {
            $this->logger->info('Classify and index document', ['document_id' => $request->getDocumentId()]);

            // Étape 1: Classification via DIS
            $classification = $this->classifyDocument(
                $request->getContent(),
                $request->getDocumentInfo()?->getTitle(),
                $request->getMetadata()
            );

            $this->logger->debug('Classification result', [
                'doc_type' => $classification['doc_type'],
                'category' => $classification['category'],
                'confidence' => $classification['confidence']
            ]);

            // Étape 2: Enrichir les métadonnées avec la classification
            $enrichedMetadata = array_merge(
                $request->getMetadata() ?? [],
                [
                    'doc_type' => $classification['doc_type'],
                    'category' => $classification['category'],
                    'classification_confidence' => $classification['confidence'],
                ],
                $classification['enriched_metadata'] ?? []
            );

            if (isset($classification['subtype'])) {
                $enrichedMetadata['subtype'] = $classification['subtype'];
            }

            // Créer une nouvelle requête avec métadonnées enrichies
            $enrichedRequest = new IndexDocumentRequest(
                $request->getDocumentId(),
                $request->getContent(),
                $enrichedMetadata,
                $request->getDocumentInfo()
            );

            // Étape 3: Indexation avec métadonnées complètes
            $indexResponse = $this->indexDocument($enrichedRequest);

            return [
                'classification' => $classification,
                'indexation' => $indexResponse,
            ];
        } catch (RagApiException $e) {
            // Re-throw as-is
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Classify and index failed', ['error' => $e->getMessage()]);
            throw new RagApiException(
                'Failed to classify and index document: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Indexation en lot
     *
     * IMPORTANT: Chaque document DOIT avoir doc_type et category dans ses métadonnées.
     * Utilisez classifyAndBulkIndexDocuments() pour classifier automatiquement tous
     * les documents avant indexation.
     *
     * @param BulkIndexRequest $request Requête avec liste de documents pré-classifiés
     * @return BulkIndexResponse Réponse avec statistiques d'indexation
     * @throws RagApiException Si des documents n'ont pas doc_type/category, ou si l'indexation échoue
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Bulk indexing failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
     * Classifie ET indexe plusieurs documents en lot (helper method)
     *
     * Workflow complet pour chaque document:
     * 1. Classification via DIS
     * 2. Enrichissement des métadonnées
     * 3. Indexation batch avec métadonnées complètes
     *
     * @param BulkIndexRequest $request Requête avec documents à classifier et indexer
     * @return array Résultat avec:
     *   - classifications: Array de résultats de classification
     *   - bulk_response: BulkIndexResponse de l'indexation
     *
     * @throws RagApiException Si la classification ou l'indexation échoue
     */
    public function classifyAndBulkIndexDocuments(BulkIndexRequest $request): array
    {
        try {
            $documents = $request->getDocuments();
            $this->logger->info('Classify and bulk index documents', ['count' => count($documents)]);

            $classifications = [];
            $enrichedDocuments = [];

            // Étape 1: Classifier chaque document
            foreach ($documents as $doc) {
                try {
                    $classification = $this->classifyDocument(
                        $doc->getContent(),
                        $doc->getDocumentInfo()?->getTitle(),
                        $doc->getMetadata()
                    );

                    $classifications[] = [
                        'document_id' => $doc->getDocumentId(),
                        'doc_type' => $classification['doc_type'],
                        'category' => $classification['category'],
                        'confidence' => $classification['confidence'],
                    ];

                    // Enrichir les métadonnées
                    $enrichedMetadata = array_merge(
                        $doc->getMetadata() ?? [],
                        [
                            'doc_type' => $classification['doc_type'],
                            'category' => $classification['category'],
                            'classification_confidence' => $classification['confidence'],
                        ],
                        $classification['enriched_metadata'] ?? []
                    );

                    if (isset($classification['subtype'])) {
                        $enrichedMetadata['subtype'] = $classification['subtype'];
                    }

                    $enrichedDocuments[] = new IndexDocumentRequest(
                        $doc->getDocumentId(),
                        $doc->getContent(),
                        $enrichedMetadata,
                        $doc->getDocumentInfo()
                    );
                } catch (RagApiException $e) {
                    $this->logger->error('Classification failed for document', [
                        'document_id' => $doc->getDocumentId(),
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            }

            // Étape 2: Indexation batch avec métadonnées enrichies
            $enrichedRequest = new BulkIndexRequest($enrichedDocuments);
            $bulkResponse = $this->bulkIndexDocuments($enrichedRequest);

            return [
                'classifications' => $classifications,
                'bulk_response' => $bulkResponse,
            ];
        } catch (RagApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Classify and bulk index failed', ['error' => $e->getMessage()]);
            throw new RagApiException(
                'Failed to classify and bulk index documents: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Met à jour un document
     *
     * IMPORTANT: Si vous modifiez le contenu (content), doc_type et category sont OBLIGATOIRES
     * dans les métadonnées. Reclassifiez le document via classifyDocument() avant l'update,
     * ou utilisez classifyAndUpdateDocument().
     *
     * @param string $documentId ID du document à mettre à jour
     * @param IndexDocumentRequest $request Données de mise à jour
     * @return IndexResponse Réponse de mise à jour
     * @throws RagApiException Si contenu modifié sans doc_type/category, ou si la mise à jour échoue
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document update failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
     * Reclassifie ET met à jour un document (helper method)
     *
     * Workflow complet si le contenu est modifié:
     * 1. Reclassification via DIS
     * 2. Enrichissement des métadonnées
     * 3. Mise à jour avec métadonnées complètes
     *
     * @param string $documentId ID du document à mettre à jour
     * @param IndexDocumentRequest $request Données de mise à jour
     * @return array Résultat avec:
     *   - classification: Résultat de la reclassification
     *   - update: IndexResponse de la mise à jour
     *
     * @throws RagApiException Si la classification ou la mise à jour échoue
     */
    public function classifyAndUpdateDocument(string $documentId, IndexDocumentRequest $request): array
    {
        try {
            $this->logger->info('Classify and update document', ['document_id' => $documentId]);

            // Reclassifier le document
            $classification = $this->classifyDocument(
                $request->getContent(),
                $request->getDocumentInfo()?->getTitle(),
                $request->getMetadata()
            );

            $this->logger->debug('Reclassification result', [
                'doc_type' => $classification['doc_type'],
                'category' => $classification['category'],
                'confidence' => $classification['confidence']
            ]);

            // Enrichir les métadonnées
            $enrichedMetadata = array_merge(
                $request->getMetadata() ?? [],
                [
                    'doc_type' => $classification['doc_type'],
                    'category' => $classification['category'],
                    'classification_confidence' => $classification['confidence'],
                ],
                $classification['enriched_metadata'] ?? []
            );

            if (isset($classification['subtype'])) {
                $enrichedMetadata['subtype'] = $classification['subtype'];
            }

            // Créer requête enrichie
            $enrichedRequest = new IndexDocumentRequest(
                $request->getDocumentId(),
                $request->getContent(),
                $enrichedMetadata,
                $request->getDocumentInfo()
            );

            // Mettre à jour avec métadonnées enrichies
            $updateResponse = $this->updateDocument($documentId, $enrichedRequest);

            return [
                'classification' => $classification,
                'update' => $updateResponse,
            ];
        } catch (RagApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Classify and update failed', ['error' => $e->getMessage()]);
            throw new RagApiException(
                'Failed to classify and update document: ' . $e->getMessage(),
                0,
                $e
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document deletion failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return HealthResponse::fromArray($data);
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document validation failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
                'Failed to validate documents: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }

    /**
     * Classifie automatiquement un document via le module DIS (Document Intelligence Service)
     *
     * REQUIS avant indexation - cette méthode DOIT être appelée avant indexDocument()
     * ou bulkIndexDocuments() pour obtenir les métadonnées obligatoires.
     *
     * @param string $content Contenu textuel du document (OCR)
     * @param string|null $title Titre optionnel du document
     * @param array|null $metadata Métadonnées additionnelles optionnelles
     *
     * @return array Résultat de classification avec:
     *   - doc_type: Type de document (invoice, contract, etc.)
     *   - category: Catégorie (comptabilite, juridique, etc.)
     *   - confidence: Score de confiance (0.0-1.0)
     *   - subtype: Sous-type optionnel
     *   - enriched_metadata: Métadonnées enrichies extraites
     *   - processing_time_ms: Temps de traitement
     *
     * @throws RagApiException Si la classification échoue
     */
    public function classifyDocument(string $content, ?string $title = null, ?array $metadata = null): array
    {
        try {
            $this->logger->info('Classifying document via DIS', ['content_length' => strlen($content)]);

            $payload = ['content' => $content];
            if ($title !== null) {
                $payload['title'] = $title;
            }
            if ($metadata !== null) {
                $payload['metadata'] = $metadata;
            }

            // 🆕 Appel au nouveau endpoint DIS (/internal/dis/v1/classify)
            $response = $this->httpClient->post($this->baseUrl . '/internal/dis/v1/classify', [
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Document classification failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
                'Failed to classify document: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Metadata extraction failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
                'Failed to extract metadata: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
                'Failed to get taxonomy info: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
                'Failed to get filterable fields: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
                'Failed to get common metadata fields: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
                'Failed to get document validation report: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
                'Failed to get validation summary: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Validation query failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
                'Failed to query validation reports: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
                'Failed to get errors by field: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Cleanup failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
                'Failed to cleanup old reports: ' . $errorMessage,
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Confidence validation failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
                'Failed to get confidence metrics: ' . $errorMessage,
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
            throw new RagApiException(
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Alert test failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('RAG pipeline test failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new RagApiException(
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
                throw new RagApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            throw new RagApiException(
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
