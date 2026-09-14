# 1. Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# 2. Instalar extensiones de PHP necesarias para MySQL y utilidades básicas
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip

# 3. Habilitar el módulo rewrite de Apache (esencial para APIs y rutas)
RUN a2enmod rewrite

# 4. Instalar Composer de forma global
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copiar los archivos del proyecto al directorio web de Apache
COPY . /var/www/html/

# 6. Cambiar los permisos para que Apache pueda leer los archivos
RUN chown -R www-data:www-data /var/www/html

# 7. Ejecutar Composer para instalar las dependencias de producción
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# 8. Exponer el puerto por defecto
EXPOSE 80
