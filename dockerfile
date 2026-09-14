# 1. Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# 2. Instalar extensiones de PHP necesarias para MySQL y utilidades básicas
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip

# 3. Cambiar la raíz de Apache para que apunte a la carpeta /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Habilitar el módulo rewrite de Apache (esencial para APIs y rutas)
RUN a2enmod rewrite

# 5. Instalar Composer de forma global
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Copiar los archivos del proyecto al directorio web de Apache
COPY . /var/www/html/

# 7. Cambiar los permisos para que Apache pueda leer los archivos
RUN chown -R www-data:www-data /var/www/html

# 8. Ejecutar Composer para instalar las dependencias de producción
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# 9. Exponer el puerto por defecto
EXPOSE 80
