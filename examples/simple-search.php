<?php
/**
 * Exemple simple de recherche avec le client RAG
 */

require_once __DIR__ . '/../vendor/autoload.php';

use RagApi\PhpClient\RagClientFactory;
use RagApi\PhpClient\Models\Request\AskRequest;
use RagApi\PhpClient\Exception\RagApiException;

try {
    // Créer le client (utilise les variables d'environnement ou valeurs par défaut)
    $baseUrl = $argv[1] ?? 'http://localhost:8888';
    $tenantId = $argv[2] ?? 'demo-tenant';
    
    $client = RagClientFactory::createWithTestToken($baseUrl, $tenantId);
    
    // Vérifier la santé du service
    $health = $client->health();
    if (!$health->isHealthy()) {
        throw new \Exception("Service RAG non disponible: " . $health->getStatus());
    }
    
    echo "✅ Connexion au service RAG réussie\n\n";
    
    // Questions d'exemple
    $questions = [
        "Quels sont les documents disponibles ?",
        "Comment calculer une facture ?",
        "Procédure pour les remboursements",
    ];
    
    foreach ($questions as $i => $question) {
        echo sprintf("🔍 Question %d: %s\n", $i + 1, $question);
        echo str_repeat("-", 50) . "\n";
        
        $request = new AskRequest($question, 5);
        $response = $client->ask($request);
        
        if ($response->isSuccessful()) {
            echo "✅ Réponse (confiance: {$response->getConfidenceLevel()}):\n";
            echo $response->getAnswer() . "\n";
            
            $documents = $response->getRetrievedDocuments();
            if (!empty($documents)) {
                echo "\n📄 Sources utilisées:\n";
                foreach (array_slice($documents, 0, 2) as $doc) {
                    echo sprintf("  - %s (score: %.2f)\n", $doc['title'] ?? 'Document', $doc['score'] ?? 0);
                }
            }
            
            echo sprintf("\n⏱️  Temps de traitement: %.2fs\n", $response->getProcessingTime());
        } else {
            echo "❌ Erreur: " . $response->getStatus() . "\n";
        }
        
        echo "\n" . str_repeat("=", 60) . "\n\n";
    }
    
} catch (RagApiException $e) {
    echo "❌ Erreur API RAG: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✅ Exemple terminé avec succès!\n";
echo "\nUtilisation: php simple-search.php [URL] [TENANT_ID]\n";
echo "Exemple: php simple-search.php http://localhost:8888 mon-tenant\n";