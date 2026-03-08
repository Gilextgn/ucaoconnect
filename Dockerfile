# Utilisation de l'image officielle PHP Apache
FROM php:8.2-apache

# Installation des extensions PHP nécessaires (ajoute les tiennes ici)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copie de ton code source dans le dossier d'Apache
COPY . /var/www/html/

# Donner les droits nécessaires à l'utilisateur www-data (Apache)
RUN chown -R www-data:www-data /var/www/html/

# Exposition du port 80 pour Railway
EXPOSE 80
