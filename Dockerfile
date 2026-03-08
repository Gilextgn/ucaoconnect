FROM php:8.2-apache

# 1. Configuration Apache de base
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 2. Correction des modules (MPM)
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# 3. Suppression sécurisée : le -f empêche l'erreur si le fichier est déjà absent
RUN rm -f /var/www/html/index.html

# 4. Copie de ton code
COPY . /var/www/html/

# 5. On donne les droits au serveur Apache sur ton code
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
