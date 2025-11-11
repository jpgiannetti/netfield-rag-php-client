# Guide de test pour le Client PHP Netfield

Ce document décrit comment exécuter et maintenir les tests pour le client PHP RAG dans un environnement Docker.

## 🏗️ Architecture des tests

### Structure des tests

```
tests/
├── Unit/                   # Tests unitaires isolés
│   ├── Auth/              # Tests d'authentification
│   ├── Models/            # Tests des modèles de données
│   └── Client/            # Tests du client principal
├── Integration/           # Tests d'intégration avec API
├── mocks/                 # Fichiers mock pour WireMock
├── coverage/              # Rapports de couverture
├── reports/               # Rapports de tests (JUnit)
└── bootstrap.php          # Configuration des tests
```

### Types de tests

1. **Tests Unitaires** : Testent les classes individuellement sans dépendances externes
2. **Tests d'Intégration** : Testent l'interaction avec l'API Netfield réelle ou mockée
3. **Tests de Bout en Bout** : Workflows complets avec tous les services

## 🐳 Environnement Docker

### Services inclus

```yaml
services:
  php-test:           # Container PHP avec PHPUnit
  rag-api:           # Mock de l'API Netfield (WireMock)
  wiremock:          # Server WireMock pour mocks personnalisés
  test-db:           # Base de données MySQL pour tests (optionnel)
```

### Configuration réseau

- Réseau interne `test-network` pour communication entre services
- Ports exposés :
  - `8888`: API Netfield mockée
  - `9999`: WireMock standalone
  - `3307`: Base de données de test

## 🚀 Exécution des tests

### Méthodes d'exécution

#### 1. Via le script automatisé (recommandé)

```bash
# Exécuter tous les tests
./bin/run-tests.sh

# Tests unitaires uniquement
./bin/run-tests.sh unit

# Tests d'intégration uniquement
./bin/run-tests.sh integration

# Avec couverture de code
./bin/run-tests.sh all --coverage

# Mode verbose
./bin/run-tests.sh unit --verbose
```

#### 2. Via Makefile

```bash
# Installation et setup
make install
make dev-setup

# Tests
make test              # Tous les tests
make test-unit         # Tests unitaires
make test-integration  # Tests d'intégration
make test-coverage     # Tests avec couverture

# Qualité de code
make phpstan           # Analyse statique
make cs-check          # Style de code
make cs-fix            # Correction du style

# Environnement Docker
make docker-up         # Démarrer l'environnement
make docker-down       # Arrêter l'environnement
make docker-shell      # Shell dans le container PHP
```

#### 3. Via Docker Compose (manuel)

```bash
# Démarrer l'environnement
docker-compose -f docker-compose.test.yml up -d

# Exécuter les tests
docker-compose -f docker-compose.test.yml exec rag-php-client-tests ./vendor/bin/phpunit

# Arrêter l'environnement
docker-compose -f docker-compose.test.yml down
```

#### 4. Commandes spécifiques

```bash
# Tests rapides (unitaires sans couverture)
make quick-test

# Tests avec debug
make debug-test

# Mode watch (relance automatique)
make test-watch

# Pipeline CI complète
make ci
```

## 📊 Rapports et métriques

### Couverture de code

Les rapports de couverture sont générés dans :

```
tests/coverage/
├── html/              # Rapport HTML interactif
├── coverage.txt       # Rapport texte
└── xml/               # Rapport XML pour CI
```

**Accéder au rapport HTML** :
```bash
# Générer la couverture
make test-coverage

# Ouvrir le rapport (nécessite un serveur web local)
open tests/coverage/html/index.html
```

### Rapports de test

```
tests/reports/
└── junit.xml          # Rapport JUnit pour intégration CI/CD
```

## 🧪 Types de tests spécifiques

### Tests unitaires

**Objectif** : Tester les classes individuellement sans dépendances externes.

**Exemples** :
- Validation des modèles de données
- Logique d'authentification JWT
- Sérialisation/désérialisation
- Gestion d'erreurs

**Exécution** :
```bash
make test-unit
# ou
./bin/run-tests.sh unit
```

### Tests d'intégration

**Objectif** : Tester l'interaction avec l'API Netfield réelle ou mockée.

**Prérequis** :
- API Netfield accessible ou mocks configurés
- Réseau Docker fonctionnel
- Variables d'environnement correctes

**Scénarios testés** :
- Workflow complet d'indexation
- Recherche avec différents filtres
- Gestion des erreurs HTTP
- Authentification JWT
- Streaming de réponses

**Exécution** :
```bash
make test-integration
# ou
./bin/run-tests.sh integration
```

### Tests de performance

Pour les tests de performance (marqués `@group slow`), utilisez :

```bash
# Inclure les tests lents
./bin/docker-test.sh "./vendor/bin/phpunit --group slow"

# Ou modifier phpunit.xml pour inclure ce groupe
```

## 🔧 Configuration et personnalisation

### Variables d'environnement

Modifiez `.env.test` ou `phpunit.xml` :

```bash
# API endpoints
NETFIELD_API_URL=http://rag-api:8080          # URL de l'API pour les tests
RAG_API_URL_LOCAL=http://localhost:8888  # URL locale
RAG_API_URL_MOCK=http://wiremock:8080    # URL des mocks

# Authentication
NETFIELD_TENANT_ID=test-tenant                # Tenant de test
NETFIELD_JWT_SECRET=test-secret-key           # Clé JWT pour les tests

# Timeouts
TEST_TIMEOUT=30                          # Timeout des tests
TEST_CONNECT_TIMEOUT=5                   # Timeout de connexion

# Debug
DEBUG_HTTP=false                         # Debug des requêtes HTTP
DEBUG_TESTS=false                        # Debug des tests
```

### Configuration PHPUnit

Modifiez `phpunit.xml` pour :

```xml
<!-- Changer l'URL de l'API -->
<env name="NETFIELD_API_URL" value="http://localhost:8888"/>

<!-- Activer/désactiver des groupes de tests -->
<groups>
    <exclude>
        <group>slow</group>      <!-- Exclure tests lents -->
        <group>external</group>  <!-- Exclure tests externes -->
    </exclude>
</groups>

<!-- Ajuster les seuils de couverture -->
<coverage>
    <report>
        <clover outputFile="coverage.xml"/>
    </report>
</coverage>
```

## 🎭 Mocking et simulation

### WireMock pour API externe

Les mocks sont définis dans `tests/mocks/` :

```json
// tests/mocks/ask-success.json
{
  "request": {
    "method": "POST",
    "url": "/api/v1/ask"
  },
  "response": {
    "status": 200,
    "jsonBody": { ... }
  }
}
```

### Mocks personnalisés

Créez des mocks pour différents scénarios :

```bash
# Mock d'erreur d'authentification
tests/mocks/auth-error.json

# Mock de timeout
tests/mocks/timeout-error.json

# Mock de réponse partielle
tests/mocks/partial-response.json
```

## 🐛 Debugging et dépannage

### Logs de debug

```bash
# Voir les logs des containers
make docker-logs

# Logs spécifiques
docker-compose -f docker-compose.test.yml logs rag-api
docker-compose -f docker-compose.test.yml logs php-test

# Shell dans le container PHP
make docker-shell
```

### Tests en mode debug

```bash
# Tests avec output détaillé
./bin/run-tests.sh unit --verbose

# Un seul test avec debug
./bin/docker-test.sh "./vendor/bin/phpunit tests/Unit/Auth/JwtAuthenticatorTest.php::testConstructorWithValidToken --debug"

# Tests avec var_dump et echo
./bin/docker-test.sh "./vendor/bin/phpunit --debug --verbose"
```

### Problèmes courants

#### Service non disponible

```bash
# Vérifier l'état des services
docker-compose -f docker-compose.test.yml ps

# Redémarrer un service
docker-compose -f docker-compose.test.yml restart rag-api

# Vérifier la connectivité réseau
docker-compose -f docker-compose.test.yml exec php-test curl -v http://rag-api:8080/api/v1/health
```

#### Tests qui échouent

```bash
# Vérifier les variables d'environnement
docker-compose -f docker-compose.test.yml exec php-test env | grep RAG

# Vérifier la configuration PHPUnit
docker-compose -f docker-compose.test.yml exec php-test cat phpunit.xml

# Nettoyer et reconstruire
make clean
make docker-build
```

#### Permissions

```bash
# Fixer les permissions des rapports
sudo chown -R $(id -u):$(id -g) tests/coverage tests/reports

# Permissions du script
chmod +x bin/run-tests.sh bin/docker-test.sh
```

## 📈 Intégration CI/CD

### Pipeline GitHub Actions

```yaml
name: Tests
on: [push, pull_request]
jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run tests
        run: |
          cd php-rag-client
          make ci
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: tests/coverage/coverage.xml
```

### Pipeline GitLab CI

```yaml
test:
  stage: test
  image: docker:latest
  services:
    - docker:dind
  script:
    - cd php-rag-client
    - make ci
  artifacts:
    reports:
      junit: tests/reports/junit.xml
      coverage_report:
        coverage_format: cobertura
        path: tests/coverage/coverage.xml
```

## 🎯 Bonnes pratiques

### Écriture de tests

1. **Isolation** : Chaque test doit être indépendant
2. **Nomenclature** : `testMethodName_Scenario_ExpectedResult`
3. **Arrange/Act/Assert** : Structure claire des tests
4. **Data Providers** : Pour tester plusieurs scénarios
5. **Mocking** : Isoler les dépendances externes

### Organisation

1. **Un test par comportement** : Ne testez qu'une seule chose
2. **Tests lisibles** : Code de test clair et documenté
3. **Couverture** : Viser 80%+ de couverture
4. **Performance** : Tests rapides (<1s par test unitaire)

### Maintenance

1. **Tests verts** : Maintenez tous les tests en état de fonctionnement
2. **Refactoring** : Refactorisez les tests avec le code
3. **Documentation** : Documentez les cas de test complexes
4. **Révision** : Incluez les tests dans les revues de code

## 📚 Ressources

### Documentation

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WireMock Documentation](http://wiremock.org/docs/)
- [Docker Compose Reference](https://docs.docker.com/compose/)

### Outils

- **PHPUnit** : Framework de test
- **WireMock** : Mock server HTTP
- **PHPStan** : Analyse statique
- **PHPCS/PHPCBF** : Style de code

### Exemples

Consultez les tests existants pour des exemples :

```bash
# Tests d'authentification
tests/Unit/Auth/JwtAuthenticatorTest.php

# Tests de modèles
tests/Unit/Models/Request/AskRequestTest.php

# Tests d'intégration
tests/Integration/RagClientIntegrationTest.php
```