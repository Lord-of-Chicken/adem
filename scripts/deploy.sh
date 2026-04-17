#!/bin/bash

# Script de déploiement pour La Ruelle d'Adem
# Usage: ./scripts/deploy.sh [serveur] [user]

SERVEUR=${1:-"lordofchicken.com"}
USER=${2:-"www-data"}

echo "Déploiement de La Ruelle d'Adem vers $SERVEUR..."

# 1. Nettoyage du cache local
echo "Nettoyage du cache local..."
rm -rf var/cache/*
rm -rf var/log/*

# 2. Création de l'archive
echo "Création de l'archive..."
tar --exclude='.git' \
    --exclude='var/cache/*' \
    --exclude='var/log/*' \
    --exclude='node_modules' \
    --exclude='.DS_Store' \
    --exclude='vendor' \
    -czf ruelle-dadem-deploy.tar.gz \
    src/ \
    public/ \
    templates/ \
    config/ \
    migrations/ \
    assets/ \
    composer.json \
    composer.lock \
    .env.production \
    importmap.php \
    symfony.lock

# 3. Transfert vers le serveur
echo "Transfert vers le serveur..."
scp ruelle-dadem-deploy.tar.gz $USER@$SERVEUR:/tmp/

# 4. Déploiement sur le serveur distant
echo "Déploiement sur le serveur distant..."
ssh $USER@$SERVEUR << 'EOF'
    cd /var/www/
    
    # Backup de l'ancienne version
    if [ -d "ruelle-dadem" ]; then
        mv ruelle-dadem ruelle-dadem-backup-$(date +%Y%m%d_%H%M%S)
    fi
    
    # Extraction de la nouvelle version
    mkdir -p ruelle-dadem
    cd ruelle-dadem
    tar -xzf /tmp/ruelle-dadem-deploy.tar.gz
    
    # Copie des variables d'environnement
    cp .env.production .env.local
    
    # Installation des dépendances
    composer install --no-dev --optimize-autoloader --no-interaction
    
    # Permissions
    chown -R www-data:www-data /var/www/ruelle-dadem
    chmod -R 755 /var/www/ruelle-dadem
    chmod -R 777 var/cache var/log
    
    # Cache
    php bin/console cache:clear --env=prod
    php bin/console cache:warmup --env=prod
    
    # Assets
    php bin/console asset-map:compile --env=prod
    
    # Nettoyage
    rm /tmp/ruelle-dadem-deploy.tar.gz
    
    echo "Déploiement terminé!"
EOF

# 5. Nettoyage local
rm ruelle-dadem-deploy.tar.gz

echo "Déploiement complété avec succès!"
echo "N'oubliez pas de:"
echo "1. Configurer les variables d'environnement dans .env.local"
echo "2. Mettre à jour les clés Stripe de production"
echo "3. Configurer le webhook Stripe"
echo "4. Exécuter les migrations: php bin/console doctrine:migrations:migrate"
