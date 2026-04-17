# Guide de déploiement - La Ruelle d'Adem

## Prérequis

- Serveur web (Apache ou Nginx)
- PHP 8.2+
- MariaDB 11.8.6+
- Composer
- Accès SSH au serveur

## 1. Préparation du projet

### Nettoyage du cache
```bash
# Supprimer tous les fichiers de cache
rm -rf var/cache/*
rm -rf var/log/*
```

### Exportation du code
```bash
# Créer une archive sans les fichiers inutiles
tar --exclude='.git' \
    --exclude='var/cache/*' \
    --exclude='var/log/*' \
    --exclude='node_modules' \
    --exclude='.DS_Store' \
    -czf ruelle-dadem-prod.tar.gz .
```

## 2. Configuration pour la production

### Variables d'environnement (.env.local)
```bash
# Copier le template
cp .env .env.local

# Configurer pour la production
APP_ENV=prod
APP_SECRET=votre_secret_key_unique_ici
APP_SHARE_DIR=var/share

# Database
DATABASE_URL="postgresql://user:password@host:5432/db_name?serverVersion=16&charset=utf8"

# Stripe (clés de production)
STRIPE_SECRET_KEY=sk_live_votre_cle_secrete
STRIPE_PUBLISHABLE_KEY=pk_live_votre_cle_publique
STRIPE_WEBHOOK_SECRET=whsec_votre_webhook_secret

# Mailer
MAILER_DSN=smtp://user:password@smtp.example.com:587?encryption=tls
```

### Configuration Apache (.htaccess)
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

### Configuration Nginx
```nginx
server {
    listen 80;
    server_name votre-domaine.com;
    root /var/www/ruelle-dadem/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param HTTPS off;
    }

    # Cache des assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Sécurité
    location ~ /\. {
        deny all;
    }
}
```

## 3. Déploiement

### Transfert des fichiers
```bash
# Via SCP
scp ruelle-dadem-prod.tar.gz user@serveur:/tmp/

# Décompresser sur le serveur
ssh user@serveur
cd /var/www/
tar -xzf /tmp/ruelle-dadem-prod.tar.gz
rm /tmp/ruelle-dadem-prod.tar.gz
```

### Installation des dépendances
```bash
cd /var/www/ruelle-dadem
composer install --no-dev --optimize-autoloader
```

### Permissions
```bash
# Permissions des fichiers
chown -R www-data:www-data /var/www/ruelle-dadem
chmod -R 755 /var/www/ruelle-dadem
chmod -R 777 /var/www/ruelle-dalem/var/cache
chmod -R 777 /var/www/ruelle-dalem/var/log
```

### Base de données MariaDB
```bash
# Créer la base de données MariaDB
mysql -u root -p -e "CREATE DATABASE ruelle_dadem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Créer l'utilisateur et donner les droits
mysql -u root -p -e "CREATE USER 'app'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON ruelle_dadem.* TO 'app'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Charger les données de base
php bin/console app:seed-ruelle
```

### Optimisation
```bash
# Vider et réchauffer le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Optimiser les assets
php bin/console asset:install --symlink --relative public
```

## 4. Configuration Stripe

### Webhook Stripe
1. Aller dans le dashboard Stripe
2. Créer un endpoint webhook : `https://lordofchicken.com/stripe/webhook`
3. Configurer les événements :
   - `checkout.session.completed`
   - `payment_intent.succeeded`

### Mettre à jour les URLs de redirection
Dans `src/Controller/PaymentController.php`, modifier les URLs :
```php
'success_url' => 'https://lordofchicken.com/payment/success?session_id={CHECKOUT_SESSION_ID}',
'cancel_url' => 'https://lordofchicken.com/payment/cancel',
```

## 5. Tests de déploiement

### Vérifications
```bash
# Vérifier la santé de l'application
php bin/console debug:router --env=prod
php bin/console debug:config --env=prod
php bin/console doctrine:database:create --if-not-exists --env=prod
```

### Tests manuels
- Accès à la homepage
- Connexion/inscription
- Ajout au panier
- Processus de paiement (avec clés de test)

## 6. Maintenance

### Mises à jour
```bash
# Mettre à jour les dépendances
composer update --no-dev

# Exécuter les nouvelles migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Vider le cache
php bin/console cache:clear --env=prod
```

### Sauvegarde
```bash
# Base de données
pg_dump ruelle_dadem > backup_$(date +%Y%m%d).sql

# Fichiers
tar -czf backup_files_$(date +%Y%m%d).tar.gz /var/www/ruelle-dalem
```

## 7. Monitoring

### Logs
```bash
# Logs Symfony
tail -f var/log/prod.log

# Logs Apache/Nginx
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
```

### Performance
```bash
# Vérifier l'utilisation mémoire
php bin/console debug:container --env=prod

# Optimiser le cache
php bin/console cache:pool:clear --env=prod
```

## 8. Sécurité

### HTTPS
Configurer SSL/TLS avec Let's Encrypt :
```bash
certbot --apache -d votre-domaine.com
```

### Firewall
```bash
# Autoriser seulement les ports nécessaires
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

### Mises à jour régulières
```bash
# Mises à jour système
apt update && apt upgrade

# Mises à jour PHP
apt update php8.2-fpm
```

## Checklist finale

- [ ] Variables d'environnement configurées
- [ ] Base de données créée et migrations exécutées
- [ ] Permissions correctes
- [ ] Cache vidé et réchauffé
- [ ] Stripe configuré avec clés de production
- [ ] Webhook Stripe configuré
- [ ] HTTPS activé
- [ ] Backup automatique configuré
- [ ] Monitoring mis en place

## Support

En cas de problème :
1. Vérifier les logs : `var/log/prod.log`
2. Vider le cache : `php bin/console cache:clear --env=prod`
3. Vérifier les permissions
4. Contacter l'hébergeur si problème serveur
