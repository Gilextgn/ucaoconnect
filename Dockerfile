FROM php:8.2-apache

# 1. Désactiver les modules MPM en conflit
# 2. Activer le module 'prefork' (le plus stable pour PHP)
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# Copier le code source
COPY . /var/www/html/

# Exposer le port 80
EXPOSE 80
