<?php

declare(strict_types=1);

namespace Netfield\Client\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Netfield\Client\Auth\JwtAuthenticator;
use Netfield\Client\Exception\NetfieldApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client pour le Document Intelligence Service (DIS)
 *
 * Ce client expose les fonctionnalités de classification et d'extraction
 * de métadonnées du service DIS (module séparé de l'API RAG).
 *
 * ENDPOINTS DIS:
 * - POST /api/v1/dis/classify : Classification synchrone d'un document
 * - POST /api/v1/dis/classify/bulk : Classification bulk asynchrone
 * - GET /api/v1/dis/jobs/{job_id}/status : Statut d'un job bulk
 * - GET /api/v1/dis/health : Health check DIS (public)
 *
 * AUTHENTIFICATION:
 * - Tous les endpoints DIS (sauf /health) sont protégés par JWT via Gateway Auth Middleware
 * - Le tenant_id est extrait automatiquement du JWT
 *
 * @package Netfield\RagClient\Client
 */
class DisClient
{
    use ErrorMessageExtractorTrait;

    private Client $httpClient;
    private string $baseUrl;
    private JwtAuthenticator $authenticator;
    private LoggerInterface $logger;

    public function __construct(
        string $baseUrl,
        JwtAuthenticator $authenticator,
        ?Client $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->authenticator = $authenticator;
        $this->httpClient = $httpClient ?? new Client();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Classification synchrone d'un document via DIS
     *
     * Analyse le contenu et retourne le type de document (doc_type),
     * la catégorie, et les métadonnées enrichies.
     *
     * ENDPOINT: POST /api/v1/dis/classify
     *
     * @param string $content Contenu textuel du document (requis, min 10 caractères)
     * @param string|null $title Titre du document (optionnel, améliore la classification)
     * @param array|null $metadata Métadonnées supplémentaires (optionnel)
     * @return array{doc_type: string, category: string, confidence: float, subtype?: string, enriched_metadata: array}
     * @throws NetfieldApiException Si la classification échoue
     *
     * Codes d'erreur possibles:
     * - CLASSIFY_CONTENT_EMPTY : Contenu vide ou trop court
     * - CLASSIFY_FAILED : Échec de la classification
     * - AUTH_TOKEN_MISSING : Token JWT manquant
     * - AUTH_TOKEN_EXPIRED : Token expiré
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

            // Appel au endpoint DIS (protégé par JWT)
            $response = $this->httpClient->post($this->baseUrl . '/api/v1/dis/classify', [
                'headers' => $this->authenticator->getHeaders(),
                'json' => $payload,
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
            $this->logger->error('Document classification failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
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
     *
     * ENDPOINT: POST /api/v1/classification/extract-metadata
     *
     * @param string $content Contenu du document
     * @param string $docType Type de document (ex: 'facture', 'contrat', etc.)
     * @return array Métadonnées extraites spécifiques au type de document
     * @throws NetfieldApiException Si l'extraction échoue
     *
     * Codes d'erreur possibles:
     * - CLASSIFY_METADATA_EXTRACTION_FAILED : Échec de l'extraction
     * - CLASSIFY_INVALID_DOC_TYPE : Type de document invalide
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
                throw new NetfieldApiException('Invalid JSON response');
            }

            return $data;
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $errorData = $this->extractErrorData($e);
            $errorCode = $this->extractErrorCode($e);
            $this->logger->error('Metadata extraction failed', ['error' => $errorMessage, 'error_code' => $errorCode]);
            throw new NetfieldApiException(
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
     *
     * ENDPOINT: GET /api/v1/classification/taxonomy
     *
     * Retourne la structure complète de la taxonomie (types de documents,
     * catégories, sous-types, etc.).
     *
     * @return array Structure de la taxonomie
     * @throws NetfieldApiException Si la récupération échoue
     *
     * Codes d'erreur possibles:
     * - CLASSIFY_TAXONOMY_NOT_FOUND : Taxonomie non trouvée
     * - CLASSIFY_TAXONOMY_LOADING_ERROR : Erreur de chargement
     */
    public function getTaxonomyInfo(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/classification/taxonomy', [
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
     *
     * ENDPOINT: GET /api/v1/classification/filterable-fields/{docType}
     *
     * Retourne la liste des champs de métadonnées qui peuvent être utilisés
     * pour filtrer les recherches (ex: 'date_facture', 'montant_total', etc.).
     *
     * @param string $docType Type de document
     * @return array Liste des champs filtrables avec leur configuration
     * @throws NetfieldApiException Si la récupération échoue
     */
    public function getFilterableFields(string $docType): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/classification/filterable-fields/' . urlencode($docType), [
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
     *
     * ENDPOINT: GET /api/v1/classification/common-metadata
     *
     * Retourne la définition des champs de métadonnées communs à tous les
     * types de documents (ex: 'title', 'date', 'author', etc.).
     *
     * @return array Définition des champs communs
     * @throws NetfieldApiException Si la récupération échoue
     */
    public function getCommonMetadataFields(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/v1/classification/common-metadata', [
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
                'Failed to get common metadata fields: ' . $errorMessage,
                $e->getCode(),
                $e,
                null,
                $errorCode,
                $errorData
            );
        }
    }
}
