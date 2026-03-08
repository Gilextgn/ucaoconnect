FROM php:8.2-apache

# Configuration standard pour supprimer l'avertissement ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Nettoyage des modules MPM (préparation pour Prefork)
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# Copie de tout le contenu actuel vers le dossier web d'Apache
COPY . /var/www/html/

# Exposer le port 80
EXPOSE 80
