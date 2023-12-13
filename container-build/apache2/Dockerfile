FROM php:8.2-apache

MAINTAINER Rosario Cafaro <cafaro.rosario@gmail.com>

RUN apt-get -y update && apt-get -y upgrade

# Copy virtual host/s
COPY ./container-build/apache2/api-qapla.local.conf /etc/apache2/sites-available/

# Enable Apache sites
RUN ln -s /etc/apache2/sites-available/api-qapla.local.conf /etc/apache2/sites-enabled/

# Cleanup
RUN apt-get -q autoclean
RUN rm -rf /var/lib/apt/lists/*

# Working dir
WORKDIR "/var/www/html/"

# Port/s
EXPOSE 80 443

# Start Apache
CMD ["apachectl", "-D", "FOREGROUND"]