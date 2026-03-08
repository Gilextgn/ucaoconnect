# Désactiver les modules MPM en conflit et activer celui par défaut (prefork)
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

COPY . /var/www/html/
EXPOSE 80

# Utilise une image PHP officielle avec Apache
FROM php:8.2-apache

# Copie tout le contenu de ton dossier actuel dans le dossier du serveur
COPY . /var/www/html/

# Expose le port 80 pour le web
EXPOSE 80
