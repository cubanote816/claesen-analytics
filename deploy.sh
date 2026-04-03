#!/bin/bash
set -e

echo "🚀 Iniciando despliegue en Producción..."

# 1. Poner en mantenimiento
echo "🚧 Entrando en modo mantenimiento..."
php artisan down --refresh=15 --secret="admin-update"

# 2. COPIA DE SEGURIDAD (Preventivo antes de migrar)
echo "📦 Realizando backup de seguridad..."
mkdir -p storage/app/backups

# Extraer credenciales del .env para el backup
DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d '=' -f2- | sed 's/^"//;s/"$//')
DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d '=' -f2- | sed 's/^"//;s/"$//')
DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d '=' -f2- | sed 's/^"//;s/"$//')

if [ -z "$DB_PASSWORD" ]; then
    mysqldump -u "$DB_USERNAME" "$DB_DATABASE" > storage/app/backups/db_pre_deploy_$(date +%F_%H-%M).sql || echo "⚠️ Advertencia: No se pudo realizar el backup, pero continuando..."
else
    mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > storage/app/backups/db_pre_deploy_$(date +%F_%H-%M).sql || echo "⚠️ Advertencia: No se pudo realizar el backup (posible error de permisos), pero continuando..."
fi


# 3. ACTUALIZAR CÓDIGO
echo "📥 Descargando cambios desde GitHub..."
git pull origin main

# 4. DEPENDENCIAS
echo "⚙️  Instalando dependencias..."
composer install --optimize-autoloader --no-dev

# 5. MIGRACIONES (Aquí ocurre el renombre de tablas de Prospectos)
echo "📂 Actualizando base de datos..."
php artisan migrate --force

# 6. ASSETS Y CACHÉ
echo "✨ Actualizando Filament y limpiando caché..."
php artisan optimize:clear
php artisan filament:upgrade

# 7. REINICIAR SERVICIOS
echo "🔄 Reiniciando colas..."
php artisan queue:restart

# 8. Salir de mantenimiento
echo "✅ Saliendo del modo mantenimiento..."
php artisan up

echo "🚀 ¡Despliegue finalizado exitosamente!"
