FROM php:8.2-apache

# 1. Configurer Apache pour pointer vers ton dossier
# Remplace 'ton-dossier' par le nom du dossier où se trouve ton index.php
# Si ton index.php est à la racine, laisse simplement /var/www/html
ENV APACHE_DOCUMENT_ROOT /var/www/html/ton-dossier

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 2. Nettoyage des modules
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# 3. Copie des fichiers
COPY . /var/www/html/

EXPOSE 80
