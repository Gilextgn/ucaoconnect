FROM debian:bullseye-slim

RUN apt-get update && apt-get install -y apache2 php libapache2-mod-php && \
    rm -rf /var/lib/apt/lists/*

RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

COPY . /var/www/html/

EXPOSE 80
CMD ["apachectl", "-D", "FOREGROUND"]
