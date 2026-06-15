#!/bin/bash

# Script de déploiement pour tous les fichiers modifiés (bots Facebook/Google fix)
# Usage: ./scripts/deploy-config.sh [serveur] [user]

SERVEUR=${1:-"lordofchicken.com"}
USER=${2:-"www-data"}

echo "Déploiement des fichiers modifiés vers $SERVEUR..."

# Transfert des fichiers
scp config/packages/security.yaml $USER@$SERVEUR:/tmp/security.yaml
scp config/packages/framework.yaml $USER@$SERVEUR:/tmp/framework.yaml
scp src/Controller/ProfileController.php $USER@$SERVEUR:/tmp/ProfileController.php
scp templates/profile/index.html.twig $USER@$SERVEUR:/tmp/profile_index.html.twig

# Déploiement sur le serveur distant
ssh $USER@$SERVEUR << 'EOF'
    cd /var/www/ruelle-dadem
    
    # Backup des anciens fichiers
    cp config/packages/security.yaml config/packages/security.yaml.backup-$(date +%Y%m%d_%H%M%S)
    cp config/packages/framework.yaml config/packages/framework.yaml.backup-$(date +%Y%m%d_%H%M%S)
    cp src/Controller/ProfileController.php src/Controller/ProfileController.php.backup-$(date +%Y%m%d_%H%M%S)
    cp templates/profile/index.html.twig templates/profile/index.html.twig.backup-$(date +%Y%m%d_%H%M%S)
    
    # Copie des nouveaux fichiers
    cp /tmp/security.yaml config/packages/security.yaml
    cp /tmp/framework.yaml config/packages/framework.yaml
    cp /tmp/ProfileController.php src/Controller/ProfileController.php
    cp /tmp/profile_index.html.twig templates/profile/index.html.twig
    
    # Permissions
    chown www-data:www-data config/packages/security.yaml config/packages/framework.yaml src/Controller/ProfileController.php templates/profile/index.html.twig
    chmod 644 config/packages/security.yaml config/packages/framework.yaml src/Controller/ProfileController.php templates/profile/index.html.twig
    
    # Cache clear
    php bin/console cache:clear --env=prod
    
    # Nettoyage
    rm /tmp/security.yaml /tmp/framework.yaml /tmp/ProfileController.php /tmp/profile_index.html.twig
    
    echo "Tous les fichiers déployés, cache vidé!"
EOF

echo "Déploiement terminé!"
