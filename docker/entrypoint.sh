#!/bin/bash
set -e

echo "🚀 Iniciando Container do Sistema VERBO..."

# Ajustar permissões críticas (caso volumes montados tenham alterado)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Verifica se o arquivo .env existe, se não, copia do .env.example
if [ ! -f .env ]; then
    echo "⚠️ Arquivo .env não encontrado. Copiando .env.example..."
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist --optimize-autoloader

composer dump-autoload

# Gera a chave se não estiver definida
if grep -q "APP_KEY=" .env && [ -z "$(grep "APP_KEY=" .env | cut -d '=' -f 2)" ]; then
    echo "🔑 Gerando Application Key..."
    php artisan key:generate
fi

# Verifica se a pasta build não existe OU se o ambiente não é produção (para forçar recompilação em dev)
if [ ! -d "public/build" ] || [ "$APP_ENV" != "production" ]; then
    echo "📦 Detectado falta de assets ou ambiente dev. Compilando Frontend..."
    
    # Instala dependências (caso o volume tenha ocultado a node_modules da imagem)
    if [ ! -d "node_modules" ]; then
        echo "📥 Instalando dependências Node..."
        npm install
    fi
    
    echo "🔨 Executando npm run build..."
    npm run build
else
    echo "✅ Assets de frontend já compilados."
fi

# Ajusta as permissões antes de iniciar os serviços
echo "Ajustando permissões..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Roda as migrações (Idealmente em prod você controla isso manualmente ou via pipeline, mas aqui facilita)
echo "🗄️ Executando migrações..."
php artisan migrate --force

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Inicia o Supervisor (que iniciará Apache e Workers)
echo "✅ Iniciando Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf