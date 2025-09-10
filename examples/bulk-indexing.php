<?php
/**
 * Exemple d'indexation en lot de documents
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netfield\RagClient\RagClientFactory;
use Netfield\RagClient\Models\Request\{IndexDocumentRequest, BulkIndexRequest, DocumentInfo};
use Netfield\RagClient\Exception\RagApiException;

try {
    // Configuration
    $baseUrl = $argv[1] ?? 'http://localhost:8888';
    $tenantId = $argv[2] ?? 'demo-tenant';
    
    $client = RagClientFactory::createWithTestToken($baseUrl, $tenantId);
    
    echo "📦 Préparation de l'indexation en lot\n";
    echo "Tenant: $tenantId\n";
    echo "API: $baseUrl\n\n";
    
    // Données d'exemple pour l'indexation
    $sampleDocuments = [
        [
            'id' => 'doc-001',
            'title' => 'Manuel utilisateur système RAG',
            'content' => 'Ce manuel décrit comment utiliser le système RAG pour rechercher et indexer des documents. Le système permet de poser des questions en langage naturel et obtenir des réponses contextualisées basées sur les documents indexés.',
            'type' => 'manuel',
            'department' => 'IT'
        ],
        [
            'id' => 'doc-002', 
            'title' => 'Procédure de facturation',
            'content' => 'La facturation suit un processus en 3 étapes: 1) Création de la facture avec les détails client, 2) Validation par le service comptable, 3) Envoi au client avec demande de paiement. Chaque facture doit contenir le numéro SIRET et les mentions légales.',
            'type' => 'procedure',
            'department' => 'Comptabilité'
        ],
        [
            'id' => 'doc-003',
            'title' => 'Politique de remboursement',
            'content' => 'Les remboursements sont accordés dans les 30 jours suivant la demande, sous réserve de présentation des justificatifs. Les frais de dossier de 15€ sont retenus sur le montant remboursé. Les remboursements partiels sont acceptés.',
            'type' => 'politique',
            'department' => 'Service client'
        ],
        [
            'id' => 'doc-004',
            'title' => 'Guide sécurité informatique',
            'content' => 'Les règles de sécurité incluent: mots de passe complexes changés tous les 3 mois, authentification à deux facteurs obligatoire, mise à jour automatique des logiciels, sauvegarde quotidienne des données critiques.',
            'type' => 'guide',
            'department' => 'IT'
        ],
        [
            'id' => 'doc-005',
            'title' => 'Contrat type prestataire',
            'content' => 'Le contrat type pour les prestataires externes définit les obligations de service, les niveaux de qualité attendus, les pénalités en cas de non-respect, et les modalités de paiement. Durée standard de 12 mois renouvelable.',
            'type' => 'contrat',
            'department' => 'Juridique'
        ]
    ];
    
    // Préparer les documents pour l'indexation
    $documents = [];
    foreach ($sampleDocuments as $doc) {
        $documentInfo = new DocumentInfo(
            title: $doc['title'],
            creationDate: date('Y-m-d H:i:s'),
            nbPages: 1
        );
        
        $indexRequest = new IndexDocumentRequest(
            documentId: $doc['id'],
            tenantId: $tenantId,
            documentInfo: $documentInfo,
            content: $doc['content'],
            metadata: [
                'type' => $doc['type'],
                'department' => $doc['department'],
                'created_at' => date('c'),
                'source' => 'bulk-indexing-example'
            ]
        );
        
        $documents[] = $indexRequest;
        echo "📄 Préparé: {$doc['title']} ({$doc['type']})\n";
    }
    
    echo "\n🚀 Lancement de l'indexation de " . count($documents) . " documents...\n";
    
    // Créer la requête d'indexation en lot
    $bulkRequest = new BulkIndexRequest($tenantId, $documents);
    
    // Exécuter l'indexation
    $startTime = microtime(true);
    $response = $client->bulkIndexDocuments($bulkRequest);
    $endTime = microtime(true);
    
    // Afficher les résultats
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 RÉSULTATS DE L'INDEXATION\n";
    echo str_repeat("=", 60) . "\n";
    
    echo sprintf("Status: %s\n", $response->getStatus());
    echo sprintf("Documents traités: %d\n", $response->getTotalDocuments());
    echo sprintf("Succès: %d\n", $response->getIndexedSuccessfully());
    echo sprintf("Erreurs: %d\n", $response->getErrorCount());
    echo sprintf("Taux de succès: %.1f%%\n", $response->getSuccessRate());
    echo sprintf("Temps total: %.2fs\n", $endTime - $startTime);
    echo sprintf("Temps API: %.2fs\n", $response->getProcessingTime() ?? 0);
    
    // Afficher les erreurs si présentes
    if ($response->hasErrors()) {
        echo "\n❌ ERREURS DÉTECTÉES:\n";
        foreach ($response->getErrors() as $error) {
            echo sprintf(
                "  • Document %s: %s (code: %s)\n",
                $error['document_id'] ?? 'inconnu',
                $error['error'] ?? 'erreur inconnue',
                $error['code'] ?? 'N/A'
            );
        }
    }
    
    // Test rapide de recherche pour vérifier l'indexation
    if ($response->isFullySuccessful()) {
        echo "\n🔍 Test de recherche rapide...\n";
        
        $testQuestions = [
            "Comment faire une facture ?",
            "Politique de remboursement"
        ];
        
        foreach ($testQuestions as $question) {
            try {
                $askRequest = new \Netfield\RagClient\Models\Request\AskRequest($question, 2);
                $askResponse = $client->ask($askRequest);
                
                if ($askResponse->isSuccessful()) {
                    $docCount = count($askResponse->getRetrievedDocuments());
                    echo sprintf("  ✅ '%s' → %d documents trouvés\n", $question, $docCount);
                } else {
                    echo sprintf("  ❌ '%s' → échec\n", $question);
                }
            } catch (\Exception $e) {
                echo sprintf("  ⚠️  '%s' → erreur: %s\n", $question, $e->getMessage());
            }
        }
    }
    
    echo "\n✅ Indexation terminée!\n";
    
    if ($response->isFullySuccessful()) {
        echo "🎉 Tous les documents ont été indexés avec succès.\n";
        echo "Vous pouvez maintenant utiliser simple-search.php pour les rechercher.\n";
    } elseif ($response->isPartiallySuccessful()) {
        echo "⚠️  Indexation partiellement réussie. Vérifiez les erreurs ci-dessus.\n";
    } else {
        echo "❌ L'indexation a échoué. Vérifiez la configuration et les erreurs.\n";
    }
    
} catch (RagApiException $e) {
    echo "❌ Erreur API RAG: " . $e->getMessage() . "\n";
    if ($context = $e->getContext()) {
        echo "Contexte: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
    }
    exit(1);
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nUtilisation: php bulk-indexing.php [URL] [TENANT_ID]\n";
echo "Exemple: php bulk-indexing.php http://localhost:8888 mon-tenant\n";