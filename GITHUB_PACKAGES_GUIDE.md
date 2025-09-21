# Guide d'utilisation du client PHP RAG via GitHub Packages

## Configuration du Publisher (Vous)

### 1. Créer un Personal Access Token (PAT) GitHub

1. Allez sur GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Cliquez sur "Generate new token (classic)"
3. Donnez un nom au token (ex: "rag-client-publish")
4. Sélectionnez les permissions :
   - `write:packages` - Pour publier des packages
   - `read:packages` - Pour lire des packages
   - `delete:packages` - Pour supprimer des versions (optionnel)
   - `repo` - Pour accéder aux repositories privés (si nécessaire)
5. Générez et copiez le token

### 2. Publier une nouvelle version

#### Option A : Via Tag Git (Recommandé)
```bash
# Créer un tag avec le format php-client-vX.Y.Z
git tag php-client-v1.0.0
git push origin php-client-v1.0.0
```
Le workflow GitHub Actions publiera automatiquement le package.

#### Option B : Via Workflow Manuel
1. Allez sur GitHub → Actions → "Publish PHP Client to GitHub Packages"
2. Cliquez sur "Run workflow"
3. Entrez la version souhaitée (ex: 1.0.0)
4. Lancez le workflow

## Configuration pour les Utilisateurs

### 1. Créer un Personal Access Token pour l'installation

Les utilisateurs doivent créer leur propre PAT avec au minimum :
- `read:packages` - Pour télécharger le package

### 2. Configurer Composer pour utiliser GitHub Packages

#### Méthode 1 : Configuration Globale (Recommandé pour développement)

```bash
# Configurer l'authentification GitHub globalement
composer config --global github-oauth.github.com YOUR_GITHUB_TOKEN
```

#### Méthode 2 : Via Variable d'Environnement

```bash
export COMPOSER_AUTH='{"github-oauth": {"github.com": "YOUR_GITHUB_TOKEN"}}'
```

#### Méthode 3 : Fichier auth.json (Pour CI/CD)

Créez un fichier `auth.json` dans votre projet :

```json
{
    "github-oauth": {
        "github.com": "YOUR_GITHUB_TOKEN"
    }
}
```

**⚠️ Important** : N'oubliez pas d'ajouter `auth.json` à votre `.gitignore` !

### 3. Ajouter le repository GitHub Packages à composer.json

Dans le `composer.json` de votre projet, vous avez plusieurs options :

#### Option A : Sans token dans l'URL (nécessite configuration séparée)
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/jpgiannetti/netfield-rag"
        }
    ],
    "require": {
        "jpgiannetti/rag-client": "^1.0"
    }
}
```

#### Option B : Avec token directement dans l'URL (plus simple, comme GitLab)
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://YOUR_GITHUB_TOKEN@github.com/jpgiannetti/netfield-rag"
        }
    ],
    "require": {
        "jpgiannetti/rag-client": "^1.0"
    }
}
```

#### Option C : Format alternatif avec username
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://x-access-token:YOUR_GITHUB_TOKEN@github.com/jpgiannetti/netfield-rag"
        }
    ],
    "require": {
        "jpgiannetti/rag-client": "^1.0"
    }
}
```

**⚠️ Sécurité** : Si vous utilisez le token dans l'URL :
- Ne commitez JAMAIS ce fichier composer.json avec le token
- Utilisez plutôt des variables d'environnement en CI/CD
- Pour le développement local, préférez la configuration globale

### 4. Installer le package

```bash
composer install
```

## Utilisation dans un projet PHP

```php
<?php
require_once 'vendor/autoload.php';

use Netfield\RagClient\RagClient;
use Netfield\RagClient\Config\ClientConfig;
use Netfield\RagClient\Auth\JWTAuthProvider;

// Configuration du client
$config = new ClientConfig([
    'base_uri' => 'https://api.example.com',
    'timeout' => 30,
]);

// Configuration de l'authentification
$authProvider = new JWTAuthProvider(
    'your-secret-key',
    'your-tenant-id'
);

// Initialisation du client
$client = new RagClient($config, $authProvider);

// Utilisation
$response = $client->ask([
    'question' => 'Quelle est la procédure de connexion?',
    'limit' => 5
]);

echo "Réponse: " . $response->getAnswer() . "\n";
echo "Confiance: " . $response->getConfidenceScore() . "\n";
```

## Configuration CI/CD (GitHub Actions)

### Méthode 1 : Avec configuration OAuth

```yaml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Configure GitHub Packages
        run: |
          composer config github-oauth.github.com ${{ secrets.GITHUB_TOKEN }}

      - name: Install dependencies
        run: composer install

      - name: Run tests
        run: vendor/bin/phpunit
```

### Méthode 2 : Avec token dans l'URL (dynamique)

```yaml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Configure repository with token
        run: |
          # Remplacer l'URL dans composer.json avec le token
          composer config repositories.github vcs https://${{ secrets.GITHUB_TOKEN }}@github.com/jpgiannetti/netfield-rag

      - name: Install dependencies
        run: composer install

      - name: Run tests
        run: vendor/bin/phpunit
```

## Configuration Docker

Pour utiliser le client dans un conteneur Docker :

```dockerfile
FROM php:8.2-cli

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurer l'authentification GitHub (via build args)
ARG GITHUB_TOKEN
RUN composer config --global github-oauth.github.com ${GITHUB_TOKEN}

# Copier les fichiers du projet
WORKDIR /app
COPY composer.json composer.lock ./

# Installer les dépendances
RUN composer install --no-dev --optimize-autoloader

# Copier le reste de l'application
COPY . .

# Nettoyer le token après installation
RUN composer config --global --unset github-oauth.github.com

CMD ["php", "app.php"]
```

Build avec :
```bash
docker build --build-arg GITHUB_TOKEN=your_token -t myapp .
```

## Gestion des versions

### Versions disponibles

Pour voir toutes les versions disponibles :
```bash
composer show jpgiannetti/rag-client --all
```

### Contraintes de version

Dans `composer.json`, vous pouvez spécifier :

- Version exacte : `"1.0.0"`
- Version mineure : `"^1.0"` (compatible avec 1.x mais pas 2.0)
- Version patch : `"~1.0.0"` (compatible avec 1.0.x mais pas 1.1)
- Version minimale : `">=1.0"`
- Plage : `">=1.0 <2.0"`

## Dépannage

### Erreur d'authentification

Si vous obtenez une erreur 401 ou 403 :
1. Vérifiez que votre token a les bonnes permissions
2. Vérifiez que le token n'a pas expiré
3. Assurez-vous d'avoir configuré l'authentification correctement

### Package non trouvé

Si Composer ne trouve pas le package :
1. Vérifiez que le repository est bien configuré dans composer.json
2. Videz le cache Composer : `composer clear-cache`
3. Mettez à jour les métadonnées : `composer update --no-install`

### Problèmes de performance

Pour optimiser les installations :
```bash
# Utiliser le cache Composer
composer install --prefer-dist

# Installer sans dev dependencies en production
composer install --no-dev --optimize-autoloader
```

## Sécurité

### Bonnes pratiques

1. **Ne jamais committer les tokens** dans le code
2. **Utiliser des tokens avec permissions minimales**
3. **Renouveler régulièrement les tokens**
4. **Utiliser des secrets GitHub Actions** pour CI/CD
5. **Configurer l'expiration des tokens** (recommandé : 90 jours max)

### Rotation des tokens

1. Créez un nouveau token avec les mêmes permissions
2. Mettez à jour vos configurations
3. Testez que tout fonctionne
4. Supprimez l'ancien token

## Support

Pour toute question ou problème :
- Ouvrir une issue : https://github.com/jpgiannetti/netfield-rag/issues
- Documentation : https://github.com/jpgiannetti/netfield-rag/tree/main/clients/php