# Tests - Client PHP Netfield

## 🚀 Démarrage rapide

```bash
# Installation et build
make install
make docker-build

# Exécuter tous les tests
make test

# Ou avec le script automatisé
./bin/run-tests.sh
```

## 📁 Organisation des tests

```
tests/
├── Unit/                          # Tests unitaires (rapides, isolés)
│   ├── Auth/
│   │   └── JwtAuthenticatorTest.php    # Tests d'authentification JWT
│   ├── Models/
│   │   ├── Request/
│   │   │   ├── AskRequestTest.php      # Tests requête de recherche
│   │   │   └── IndexDocumentRequestTest.php  # Tests requête d'indexation
│   │   └── Response/
│   │       └── AskResponseTest.php     # Tests réponse de recherche
│   └── Client/                    # Tests du client principal
├── Integration/                   # Tests d'intégration (avec API)
│   └── RagClientIntegrationTest.php    # Tests bout-en-bout
├── mocks/                         # Fichiers mock WireMock
│   ├── ask-success.json          # Mock réponse de recherche
│   ├── index-success.json        # Mock indexation
│   ├── bulk-index-success.json   # Mock indexation en lot
│   ├── health-success.json       # Mock health check
│   └── auth-error.json           # Mock erreur d'auth
├── coverage/                      # Rapports de couverture (générés)
├── reports/                       # Rapports JUnit (générés)
└── bootstrap.php                  # Configuration des tests
```

## 🎯 Types de tests

### Tests unitaires
- **Objectif** : Tester les classes individuellement
- **Durée** : < 1 seconde par test
- **Dépendances** : Aucune (mocks uniquement)
- **Couverture** : 100% visée

### Tests d'intégration
- **Objectif** : Tester l'interaction avec l'API Netfield
- **Durée** : 5-30 secondes par test
- **Dépendances** : API Netfield mockée ou réelle
- **Couverture** : Workflows principaux

## 🧪 Scénarios testés

### Authentification JWT
- ✅ Génération de tokens de test
- ✅ Validation de tokens valides/invalides
- ✅ Extraction de claims (tenant_id, scopes)
- ✅ Gestion de l'expiration

### Modèles de données
- ✅ Validation des requêtes (AskRequest, IndexDocumentRequest)
- ✅ Sérialisation/désérialisation JSON
- ✅ Gestion des champs optionnels
- ✅ Messages d'erreur appropriés

### Client RAG
- ✅ Recherche de documents (/ask)
- ✅ Indexation simple (/index)
- ✅ Indexation en lot (/bulk-index)
- ✅ Streaming (/stream/ask)
- ✅ Mise à jour de documents
- ✅ Suppression de documents
- ✅ Health checks
- ✅ Gestion d'erreurs HTTP
- ✅ Logging des opérations

### Cas d'erreur
- ✅ Authentification échouée
- ✅ Validation des données
- ✅ Timeouts de connexion
- ✅ Réponses malformées
- ✅ Erreurs serveur (5xx)

## 🏃‍♂️ Exécution

### Commandes principales

```bash
# Tests complets
make test                    # Tous les tests
make test-unit              # Tests unitaires uniquement
make test-integration       # Tests d'intégration uniquement
make test-coverage          # Tests avec couverture

# Tests rapides
make quick-test             # Tests unitaires sans couverture
make debug-test             # Tests avec debug verbose

# Qualité de code
make phpstan               # Analyse statique
make cs-check              # Vérification style
make cs-fix                # Correction style automatique
```

### Scripts personnalisés

```bash
# Script automatisé complet
./bin/run-tests.sh [unit|integration|all] [--coverage] [--verbose]

# Exemples
./bin/run-tests.sh unit --verbose           # Tests unitaires avec détails
./bin/run-tests.sh integration --coverage   # Tests d'intégration avec couverture
./bin/run-tests.sh all                      # Tous les tests

# Commandes Docker directes
./bin/docker-test.sh "./vendor/bin/phpunit --testsuite 'Unit Tests'"
./bin/docker-test.sh "./vendor/bin/phpstan analyse"
```

## 📊 Métriques et rapports

### Couverture de code

```
tests/coverage/
├── html/index.html        # Rapport HTML interactif
├── coverage.txt           # Rapport texte
└── xml/                   # Format XML pour CI
```

Objectifs de couverture :
- **Global** : > 85%
- **Classes métier** : > 95%
- **Tests d'intégration** : Workflows principaux couverts

### Rapports de test

```
tests/reports/
└── junit.xml              # Format JUnit pour intégration CI/CD
```

## 🐳 Environnement Docker

### Services de test

| Service | Port | Description |
|---------|------|-------------|
| `php-test` | - | Container PHP avec PHPUnit |
| `rag-api` | 8888 | Mock API Netfield (WireMock) |
| `wiremock` | 9999 | Server WireMock standalone |
| `test-db` | 3307 | MySQL pour tests (optionnel) |

### Configuration réseau

- Réseau interne `test-network`
- Communication inter-services par nom DNS
- Isolation complète de l'environnement de développement

## 🔧 Configuration

### Variables d'environnement

```bash
# Dans .env.test ou phpunit.xml
NETFIELD_API_URL=http://rag-api:8080
NETFIELD_TENANT_ID=test-tenant
NETFIELD_JWT_SECRET=test-secret-key
TEST_TIMEOUT=30
DEBUG_TESTS=false
```

### Personnalisation PHPUnit

```xml
<!-- phpunit.xml -->
<php>
    <env name="NETFIELD_API_URL" value="http://localhost:8888"/>  <!-- API locale -->
    <env name="DEBUG_HTTP" value="true"/>                    <!-- Debug HTTP -->
</php>

<groups>
    <exclude>
        <group>slow</group>        <!-- Exclure tests lents -->
        <group>external</group>    <!-- Exclure tests externes -->
    </exclude>
</groups>
```

## 🐛 Debug et dépannage

### Logs et diagnostic

```bash
# Logs des containers
make docker-logs

# Shell dans le container PHP
make docker-shell

# Vérifier la connectivité
docker-compose -f docker-compose.test.yml exec php-test curl http://rag-api:8080/api/v1/health

# État des services
docker-compose -f docker-compose.test.yml ps
```

### Tests en mode debug

```bash
# Test spécifique avec debug
./bin/docker-test.sh "./vendor/bin/phpunit tests/Unit/Auth/JwtAuthenticatorTest.php::testConstructorWithValidToken --debug --verbose"

# Tous les tests avec output détaillé
./bin/run-tests.sh unit --verbose
```

### Problèmes courants

1. **Service non disponible**
   ```bash
   make docker-down
   make docker-up
   ```

2. **Permissions des rapports**
   ```bash
   sudo chown -R $(id -u):$(id -g) tests/coverage tests/reports
   ```

3. **Cache PHPUnit corrompu**
   ```bash
   make clean
   ```

## 🚀 Intégration CI/CD

### GitHub Actions

```yaml
- name: Run PHP tests
  run: |
    cd php-rag-client
    make ci  # clean + quality + test-coverage
    
- name: Upload coverage
  uses: codecov/codecov-action@v3
  with:
    file: php-rag-client/tests/coverage/coverage.xml
```

### GitLab CI

```yaml
php-tests:
  stage: test
  script:
    - cd php-rag-client && make ci
  artifacts:
    reports:
      junit: php-rag-client/tests/reports/junit.xml
      coverage_report:
        coverage_format: cobertura
        path: php-rag-client/tests/coverage/coverage.xml
```

## 📝 Bonnes pratiques

### Écriture de tests

1. **Isolation** : Chaque test est indépendant
2. **Nomenclature** : `testMethod_Scenario_ExpectedResult`
3. **AAA Pattern** : Arrange, Act, Assert
4. **Data Providers** : Pour tester plusieurs cas
5. **Assertions claires** : Messages d'erreur explicites

### Exemple de test bien structuré

```php
/**
 * @dataProvider validTokenDataProvider
 */
public function testIsTokenValid_WithValidToken_ReturnsTrue(string $token): void
{
    // Arrange
    $authenticator = new JwtAuthenticator($token);
    
    // Act
    $isValid = $authenticator->isTokenValid();
    
    // Assert
    $this->assertTrue($isValid, 'Valid token should return true');
}

public static function validTokenDataProvider(): array
{
    return [
        'standard_token' => [JwtAuthenticator::generateTestToken('tenant1')],
        'long_expiry' => [JwtAuthenticator::generateTestToken('tenant2', 'secret', 48)],
    ];
}
```

### Maintenance

- **Tests verts** : Tous les tests doivent toujours passer
- **Refactoring** : Refactorisez les tests avec le code
- **Documentation** : Documentez les cas complexes
- **Performance** : Gardez les tests rapides
- **Couverture** : Maintenez une couverture élevée

## 📚 Ressources

- [PHPUnit Documentation](https://phpunit.de/)
- [WireMock Docs](http://wiremock.org/docs/)
- [Docker Compose Guide](https://docs.docker.com/compose/)
- [PSR-3 Logging](https://www.php-fig.org/psr/psr-3/)

## 🎯 Objectifs qualité

| Métrique | Objectif | Actuel |
|----------|----------|--------|
| Couverture globale | > 85% | 🎯 |
| Tests unitaires | 100% passants | ✅ |
| Tests d'intégration | 100% passants | ✅ |
| Temps d'exécution | < 2 min total | ✅ |
| Style de code | PSR-12 | ✅ |
| Analyse statique | Level 7 | ✅ |