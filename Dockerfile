FROM php:8.2-apache

# Configuration Apache
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copie de ton projet
COPY . /var/www/html/

# Supprimer la page par défaut sans erreur si elle n'existe pas
RUN rm -f /var/www/html/index.html /var/www/html/index.php.default

EXPOSE 80
