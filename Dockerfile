# Use PHP 8.4 com Apache
FROM php:8.4-apache

# Argumentos para criar usuário de sistema (opcional, evita problemas de permissão)
ARG user=verbo
ARG uid=1000

# 1. Instalar dependências do sistema e FFmpeg
RUN apt-get update && apt-get install -y \
    sudo \
    nano \
    cron \
    supervisor \
    ffmpeg \
    postgresql-client \
    libpng-dev \
    libjpeg-dev \
    libpq-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    dos2unix \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_pgsql zip

# 4. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Configurar Apache
# Habilita mod_rewrite para rotas do Laravel
RUN a2enmod rewrite

# Copiar configuração do Apache customizada
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# 7. Configurar Supervisor (Substituto do Systemd em Docker)
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 8. Configurar Diretório de Trabalho
WORKDIR /var/www/html

# Copiar arquivos da aplicação
COPY . .

# 9. Rodar Instalação de Dependências (Produção)
# Nota: Em desenvolvimento local, você pode comentar essas linhas e rodar no entrypoint
# RUN composer install --no-interaction --optimize-autoloader --no-dev
# RUN npm install && npm run build

# 10. Ajustar Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# 11. Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
