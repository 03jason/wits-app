FROM php:8.2-apache

# Activer mod_rewrite pour les routes Slim
RUN a2enmod rewrite

# Servir /public comme racine web
RUN sed -i 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Installer Composer dans le conteneur
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


# pour MySQL via PDO
RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
