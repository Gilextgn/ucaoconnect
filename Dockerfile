FROM php:8.2-apache

# 1. Désactiver tous les modules MPM par défaut
RUN a2dismod mpm_event mpm_worker mpm_prefork

# 2. Activer uniquement le MPM 'prefork' (nécessaire pour PHP avec Apache)
RUN a2enmod mpm_prefork

# 3. Installer les extensions nécessaires
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 4. Copier ton code
COPY . /var/www/html/

# 5. Corriger les permissions
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
