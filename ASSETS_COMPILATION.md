# Guide de compilation des assets - La Ruelle d'Adem

## Comment compiler et déplacer les fichiers dans `public`

Dans Symfony avec ImportMaps, les fichiers sont gérés différemment des systèmes traditionnels comme Webpack Encore.

## 1. Structure actuelle

### Fichiers sources
```
assets/
  app.js                    # Point d'entrée JavaScript
  styles/
    ruelle.css             # Styles CSS
  controllers/             # Stimulus controllers
  img/                     # Images (vides actuellement)
```

### Fichiers publics
```
public/
  index.php                # Point d'entrée PHP
  build/                   # Généré par ImportMaps (après compilation)
```

## 2. Compilation des assets

### Commande de compilation
```bash
# Compiler les assets pour la production
php bin/console importmap:install

# Pour la production (optimisé)
php bin/console importmap:install --env=prod
```

### Installation des bundles
```bash
# Installer les assets des bundles dans public/bundles/
php bin/console assets:install public --symlink --relative
```

## 3. Processus complet de compilation

### Étape 1: Nettoyage
```bash
# Vider le cache
php bin/console cache:clear --env=prod

# Supprimer les anciens fichiers build
rm -rf public/build/*
```

### Étape 2: Compilation
```bash
# Compiler les ImportMaps
php bin/console importmap:install --env=prod

# Installer les assets des bundles
php bin/console assets:install public --symlink --relative
```

### Étape 3: Vérification
```bash
# Vérifier les fichiers générés
ls -la public/build/
```

## 4. Fichiers générés

Après compilation, vous aurez :
```
public/build/
  app.js                   # JavaScript compilé
  app.js.map              # Source map (dev seulement)
  styles/
    ruelle.css            # CSS compilé
    ruelle.css.map        # Source map (dev seulement)
```

## 5. Configuration pour la production

### ImportMaps optimisé
```bash
# Générer l'importmap pour la production
php bin/console importmap:install --env=prod

# Cela crée public/build/importmap.json
```

### Templates Twig
Les templates utilisent déjà la fonction `importmap()` :
```twig
{% block importmap %}{{ importmap('app') }}{% endblock %}
```

## 6. Déploiement avec assets

### Script de déploiement modifié
```bash
#!/bin/bash
# Dans scripts/deploy.sh

# ... code existant ...

# Compilation des assets
echo "Compilation des assets..."
php bin/console importmap:install --env=prod
php bin/console assets:install public --symlink --relative

# Optimisation pour la production
php bin/console cache:warmup --env=prod
```

## 7. Vérification post-compilation

### Tests
```bash
# Vérifier que les fichiers existent
test -f public/build/app.js && echo "app.js OK"
test -f public/build/styles/ruelle.css && echo "CSS OK"

# Vérifier l'importmap
cat public/build/importmap.json
```

### Debug
```bash
# Voir l'importmap généré
php bin/console debug:asset-map

# Vérifier les assets
php bin/console debug:container --tag=asset.mapper
```

## 8. Configuration du serveur

### Apache
```apache
# Servir les fichiers statiques directement
<Directory "/var/www/ruelle-dadem/public/build">
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
</Directory>
```

### Nginx
```nginx
# Cache pour les assets compilés
location ~* ^/build/(.+)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header X-Content-Type-Options nosniff;
    
    try_files $uri =404;
}
```

## 9. Optimisation

### Compression
```bash
# Compresser les assets (optionnel)
gzip -k public/build/*.js
gzip -k public/build/styles/*.css
```

### CDN (optionnel)
Pour les grands projets, vous pouvez uploader `public/build/` sur un CDN :
```bash
# Upload vers AWS S3, Cloudflare, etc.
aws s3 sync public/build/ s3://votre-cdn/build/
```

## 10. Dépannage

### Problèmes courants
```bash
# Si les assets ne se chargent pas
php bin/console cache:clear --env=prod
php bin/console importmap:install --env=prod

# Si permissions incorrectes
chown -R www-data:www-data public/build/
chmod -R 755 public/build/

# Si erreurs 404 sur les assets
vérifier la configuration du serveur web
```

### Debug en production
```bash
# Activer temporairement le debug
APP_DEBUG=1 php bin/console importmap:install

# Vérifier les chemins
php bin/console debug:router --env=prod
```

## Résumé des commandes

Pour compiler et déplacer les fichiers dans `public` :

```bash
# 1. Nettoyer
php bin/console cache:clear --env=prod

# 2. Compiler les ImportMaps
php bin/console importmap:install --env=prod

# 3. Installer les bundles
php bin/console assets:install public --symlink --relative

# 4. Vérifier
ls -la public/build/
```

Cela va générer tous les fichiers nécessaires dans `public/build/` pour que votre site fonctionne en production.
