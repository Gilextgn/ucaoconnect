FROM php:8.2-apache

# Configuration Apache
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copie de ton projet
COPY . /var/www/html/

# Suppression de la page d'accueil par défaut de Debian
RUN rm /var/www/html/index.html

EXPOSE 80
