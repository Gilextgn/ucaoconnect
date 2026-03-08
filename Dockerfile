FROM php:8.2-apache

# Configuration pour supprimer l'alerte ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Nettoyage des modules MPM
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# Suppression sécurisée du fichier par défaut
RUN rm -f /var/www/html/index.html

# Copie de ton code
COPY . /var/www/html/

EXPOSE 80
