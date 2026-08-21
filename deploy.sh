#!/bin/bash

# Script de despliegue para el bot de WhatsApp de DPIKEOS.
# Pensado para correr junto a otras apps (p.ej. ARKA01) en la misma VM:
# usa su propio PHP-FPM (8.5), su propia base de datos y su propio dominio.
# No requiere Supervisor: este proyecto no usa colas persistentes ni
# WebSockets/Reverb (QUEUE_CONNECTION=sync, sin broadcasting).
#
# Ejecutar en el servidor, dentro del directorio del proyecto, después de
# subir/actualizar el código (git pull).

echo "🚀 Iniciando despliegue del bot DPIKEOS..."

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

error() {
    echo -e "${RED}❌ Error: $1${NC}"
    exit 1
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    error "No se encontró el archivo artisan. Asegúrate de estar en el directorio raíz del proyecto."
fi

# Verificar que existe .env
if [ ! -f ".env" ]; then
    error "No se encontró el archivo .env. Cópialo desde .env.example y complétalo con credenciales propias de este bot (no reutilices las de otra app)."
fi

# 0. Verificar versión de PHP (composer.json exige ^8.5; convive con el
#    8.4-FPM que puedan usar otras apps en la misma VM, cada una con su socket).
echo ""
echo "🐘 Verificando versión de PHP..."
PHP_VERSION=$(php -r 'echo PHP_VERSION;' 2>/dev/null)
PHP_MAJOR_MINOR=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
if [ -z "$PHP_VERSION" ]; then
    error "No se pudo detectar la versión de PHP en el PATH actual. Verifica que 'php' apunte al binario correcto (¿php8.5?)."
elif [ "$PHP_MAJOR_MINOR" != "8.5" ]; then
    warning "PHP detectado: $PHP_VERSION. Este proyecto requiere ^8.5 (composer.json). Verifica que el PHP-FPM/CLI de este sitio sea 8.5, no el de otra app en la misma VM."
else
    success "PHP $PHP_VERSION"
fi

# 1. Limpiar cachés
echo ""
echo "📦 Limpiando cachés..."
php artisan optimize:clear || warning "No se pudieron limpiar todas las cachés"
success "Cachés limpiadas"

# 2. Verificar APP_KEY (debe ser única por app; nunca copiar la de otro proyecto)
echo ""
echo "🔑 Verificando APP_KEY..."
APP_KEY=$(grep "^APP_KEY=" .env | cut -d '=' -f2)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" == "" ]; then
    warning "APP_KEY no está configurada. Generando una nueva clave para este bot..."
    php artisan key:generate --force
    success "APP_KEY generada"
else
    success "APP_KEY ya está configurada"
fi

# 3. Instalar/Actualizar dependencias de Composer
echo ""
echo "📥 Instalando dependencias de Composer..."
if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction || error "Error al instalar dependencias de Composer"
    success "Dependencias de Composer instaladas"
else
    warning "Composer no está instalado en el PATH. Asegúrate de instalar las dependencias manualmente."
fi

# 4. Ejecutar migraciones (contra la base de datos PROPIA de este bot)
echo ""
echo "🗄️  Ejecutando migraciones de base de datos..."
DB_DATABASE=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2)
echo "   Base de datos configurada: ${DB_DATABASE:-'(no configurada)'}"
read -p "¿Ejecutar migraciones sobre esta base de datos? (s/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Ss]$ ]]; then
    php artisan migrate --force || warning "Error al ejecutar migraciones. Verifica la conexión a la base de datos."
    success "Migraciones ejecutadas"
else
    warning "Migraciones omitidas"
fi

# 5. Crear enlace simbólico de storage
echo ""
echo "🔗 Creando enlace simbólico de storage..."
php artisan storage:link || warning "El enlace simbólico ya existe o hubo un error"
success "Enlace simbólico creado"

# 6. Configurar permisos
echo ""
echo "🔐 Configurando permisos..."
if [ -w "storage" ] && [ -w "bootstrap/cache" ]; then
    chmod -R 775 storage bootstrap/cache 2>/dev/null || warning "No se pudieron cambiar permisos (puede requerir sudo)"
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || warning "No se pudo cambiar el propietario a www-data (puede requerir sudo)"
    success "Permisos configurados"
else
    warning "No se pudieron verificar permisos. Asegúrate de que storage/ y bootstrap/cache/ sean escribibles por el usuario de PHP-FPM (habitualmente www-data)."
fi

# 7. Optimizar Laravel
echo ""
echo "⚡ Optimizando Laravel..."
php artisan config:cache || warning "Error al cachear configuración"
php artisan route:cache || warning "Error al cachear rutas"
php artisan view:cache || warning "Error al cachear vistas"
success "Laravel optimizado"

# 8. Verificar configuración de entorno
echo ""
echo "🔍 Verificando configuración..."
APP_ENV=$(grep "^APP_ENV=" .env | cut -d '=' -f2)
APP_DEBUG=$(grep "^APP_DEBUG=" .env | cut -d '=' -f2)
APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2)

if [ "$APP_ENV" != "production" ]; then
    warning "APP_ENV está configurado como '$APP_ENV'. Para producción debería ser 'production'"
fi

if [ "$APP_DEBUG" == "true" ]; then
    warning "APP_DEBUG está en 'true'. Para producción debería ser 'false'"
fi

# 9. Verificar variables de WhatsApp (deben ser las de ESTE número/app de Meta)
echo ""
echo "📱 Verificando configuración de WhatsApp..."
WHATSAPP_VARS=("WHATSAPP_TOKEN" "WHATSAPP_PHONE_NUMBER" "WHATSAPP_BUSINESS_ID" "WHATSAPP_PHONE_NUMBER_ID")
MISSING_VARS=()

for var in "${WHATSAPP_VARS[@]}"; do
    if ! grep -q "^${var}=" .env || grep "^${var}=" .env | grep -q "=$"; then
        MISSING_VARS+=("$var")
    fi
done

if [ ${#MISSING_VARS[@]} -gt 0 ]; then
    warning "Variables de WhatsApp faltantes o vacías: ${MISSING_VARS[*]}"
else
    success "Variables de WhatsApp configuradas"
fi

# 10. Verificar que el cron de Laravel esté programado (necesario: hay una
#     tarea real, campaigns:send-scheduled, corriendo cada minuto).
echo ""
echo "⏰ Verificando cron de Laravel (schedule:run)..."
if crontab -l 2>/dev/null | grep -q "artisan schedule:run"; then
    success "El cron de Laravel ya está programado"
else
    warning "No se encontró una entrada de cron para 'artisan schedule:run'. Este proyecto SÍ la necesita (tiene una tarea programada cada minuto)."
    echo "   Agrégala con 'crontab -e':"
    echo "   * * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
fi

# Resumen final
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}✨ Despliegue completado!${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 Próximos pasos:"
echo "   1. Verifica que la app responde: ${APP_URL:-'(revisa APP_URL en .env)'}"
echo "   2. Revisa los logs si hay errores: storage/logs/laravel-*.log"
echo "   3. Configura en Meta el webhook: ${APP_URL:-'https://tu-dominio'}/api/whatsapp/webhook"
echo "   4. Verifica que APP_DEBUG=false y APP_ENV=production en .env"
echo ""
echo "🔗 Comandos útiles:"
echo "   - Ver rutas: php artisan route:list"
echo "   - Ver estado: php artisan about"
echo "   - Limpiar cachés: php artisan optimize:clear"
echo ""
echo -e "${YELLOW}⚠️  Nunca corras 'php artisan test' en esta VM contra la base real:${NC}"
echo "   ya causó una pérdida de datos de producción una vez por config cacheada."
echo "   Si necesitas probar algo, hazlo en un entorno separado."
echo ""
