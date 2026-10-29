# Imagen para probar el sitio en Render (o cualquier servicio con Docker).
FROM php:8.3-apache

# GD con soporte JPG/PNG/WEBP para achicar las fotos que se suben desde el panel
RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" gd \
 && rm -rf /var/lib/apt/lists/*

# .htaccess habilitado (igual que en el hosting) y módulos que usa
RUN a2enmod rewrite headers expires \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN { \
      echo 'upload_max_filesize = 12M'; \
      echo 'post_max_size = 64M'; \
      echo 'expose_php = Off'; \
      echo 'date.timezone = America/Argentina/Buenos_Aires'; \
    } > /usr/local/etc/php/conf.d/sitio.ini

COPY . /var/www/html/
RUN rm -f /var/www/html/Dockerfile /var/www/html/.dockerignore \
 && mkdir -p /var/www/html/data/backups /var/www/html/img/productos /var/www/html/img/galeria \
 && chown -R www-data:www-data /var/www/html/data /var/www/html/img/productos /var/www/html/img/galeria

# Render indica el puerto en la variable PORT (por defecto 10000)
ENV PORT=10000
CMD sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf \
 && sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf \
 && exec apache2-foreground
