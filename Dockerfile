# Contenedor WEB: Apache + PHP con la aplicación adentro
FROM php:8.2-apache

# Actualizar paquetes e instalar las extensiones que usa la app (PDO para MySQL)
RUN apt-get update && apt-get upgrade -y && docker-php-ext-install pdo_mysql mysqli

# Permitir que funcionen los .htaccess (protegen la carpeta de archivos subidos)
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copiar la aplicación dentro de la imagen
COPY . /var/www/html/

# Apache tiene que poder escribir en la carpeta de archivos subidos
RUN chown -R www-data:www-data /var/www/html/php/uploads

EXPOSE 80
