<?php
/**
 * Démo d'exécution programmatique des tests
 * 
 * Ce script montre comment intégrer les tests dans un workflow personnalisé
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netfield\RagClient\NetfieldClientFactory;
use Netfield\RagClient\Auth\JwtAuthenticator;

echo "🧪 Démo des tests PHP RAG Client\n";
echo str_repeat("=", 50) . "\n\n";

// 1. Test de la génération de token JWT
echo "1. Test génération JWT Token:\n";
try {
    $token = JwtAuthenticator::generateTestToken('demo-tenant', 'demo-secret');
    $auth = new JwtAuthenticator($token);
    
    echo "   ✅ Token généré: " . substr($token, 0, 50) . "...\n";
    echo "   ✅ Tenant ID: " . $auth->getTenantId() . "\n";
    echo "   ✅ Token valide: " . ($auth->isTokenValid() ? 'Oui' : 'Non') . "\n";
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Test de création du client
echo "2. Test création du client:\n";
try {
    $client = NetfieldClientFactory::createWithTestToken(
        'http://localhost:8888',
        'demo-tenant'
    );
    
    echo "   ✅ Client RAG créé avec succès\n";
} catch (Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Instructions pour les tests complets
echo "3. Exécution des tests complets:\n";
echo "   📋 Tests unitaires:       make test-unit\n";
echo "   📋 Tests d'intégration:   make test-integration\n";
echo "   📋 Tous les tests:        make test\n";
echo "   📋 Avec couverture:       make test-coverage\n";

echo "\n";

// 4. Vérification de l'environnement Docker
echo "4. Vérification environnement Docker:\n";

// Vérifier si Docker est disponible
$dockerCheck = shell_exec('docker --version 2>/dev/null');
if ($dockerCheck) {
    echo "   ✅ Docker installé: " . trim($dockerCheck) . "\n";
} else {
    echo "   ❌ Docker non trouvé\n";
}

// Vérifier Docker Compose
$composeCheck = shell_exec('docker-compose --version 2>/dev/null');
if ($composeCheck) {
    echo "   ✅ Docker Compose installé: " . trim($composeCheck) . "\n";
} else {
    echo "   ❌ Docker Compose non trouvé\n";
}

echo "\n";

// 5. Commandes de test recommandées
echo "5. Commandes recommandées:\n";
echo "\n";

$commands = [
    "Installation et setup" => [
        "composer install",
        "make docker-build",
    ],
    "Tests rapides" => [
        "make quick-test",
        "./bin/run-tests.sh unit",
    ],
    "Tests complets" => [
        "make test",
        "./bin/run-tests.sh all --coverage",
    ],
    "Démarrage environnement" => [
        "make docker-up",
        "make docker-shell  # Pour debug",
    ],
    "Nettoyage" => [
        "make clean",
        "make docker-down",
    ],
];

foreach ($commands as $category => $cmds) {
    echo "   📌 $category:\n";
    foreach ($cmds as $cmd) {
        echo "      $cmd\n";
    }
    echo "\n";
}

// 6. Structure des fichiers de test
echo "6. Structure des tests:\n";
$testFiles = [
    'tests/Unit/Auth/JwtAuthenticatorTest.php' => 'Tests authentification JWT',
    'tests/Unit/Models/Request/AskRequestTest.php' => 'Tests modèle de requête',
    'tests/Integration/RagClientIntegrationTest.php' => 'Tests bout-en-bout',
    'tests/mocks/*.json' => 'Mocks WireMock pour API',
];

foreach ($testFiles as $file => $description) {
    echo "   📄 $file\n      → $description\n";
}

echo "\n";

echo "🎯 Pour commencer:\n";
echo "   1. make install\n";
echo "   2. make docker-build\n";
echo "   3. make test\n";
echo "\n";
echo "📚 Documentation complète: TESTING.md\n";
echo "\n";
echo "✨ Tests créés avec succès! Prêt pour l'exécution.\n";