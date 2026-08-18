# Usamos PHP 8.2 con Apache como base oficial
FROM php:8.2-apache

# 1. Instalar dependencias del sistema necesarias para compilar extensiones PHP
RUN apt-get update && apt-get install -y \
    # Librerías para procesamiento de imágenes (GD)
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    # Librerías para manejo de archivos ZIP (necesario para PhpSpreadsheet)
    libzip-dev \
    # Librerías para conexión a PostgreSQL
    libpq-dev \
    # Herramientas generales útiles en el contenedor
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 2. Configurar e instalar extensiones de PHP
# Configuramos GD explícitamente para que encuentre las librerías instaladas arriba
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        zip \
        pdo \
        pdo_pgsql \
    && docker-php-ext-enable gd zip pdo_pgsql

# 3. Habilitar módulos esenciales de Apache
# mod_rewrite es vital para que funcionen las rutas limpias y el .htaccess
RUN a2enmod rewrite

# 4. Establecer directorio de trabajo estándar de Apache
WORKDIR /var/www/html

# 5. Instalar Composer globalmente desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Copiar todos los archivos del backend al contenedor
COPY . .

# 7. Instalar dependencias PHP definidas en composer.json
# --no-dev: No instala herramientas de testing/desarrollo para reducir tamaño
# --optimize-autoloader: Mejora el rendimiento de carga de clases
RUN composer install --no-dev --optimize-autoloader

# 8. CORRECCIÓN CRÍTICA DE PERMISOS
# Apache corre como usuario 'www-data'. Sin esto, no podrá leer tus archivos PHP
# ni escribir en carpetas temporales, causando error "403 Forbidden".
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \;

# 9. Exponer puerto 80 (HTTP estándar)
EXPOSE 80

# 10. Comando de inicio del servidor Apache en primer plano
CMD ["apache2-foreground"]