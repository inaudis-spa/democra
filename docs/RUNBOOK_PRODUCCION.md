# Despliegue a producción

## Regla principal

El código y la base de datos se despliegan como una sola versión. Subir PHP sin aplicar sus migraciones puede dejar perfiles completos fuera del sistema.

## Orden seguro

1. Activar una ventana de mantenimiento.
2. Respaldar la base de datos de producción.
3. Subir el código nuevo sin reemplazar los secretos del servidor.
4. Seleccionar explícitamente la base correcta en el panel SQL.
5. Ejecutar primero `php database/production_preflight.php` para identificar diferencias.
6. Ejecutar las migraciones pendientes en orden (son idempotentes y no incluyen un nombre de base fijo):
   - `database/migrations/20261001_harden_election_integrity.sql`
   - `database/migrations/20261005_live_control_room.sql`
   - `database/migrations/20261005_ministro_fe.sql`
   - `database/migrations/20261009_access_integrity.sql`
7. Volver a ejecutar desde la raíz del proyecto:

   ```bash
   php database/production_preflight.php
   ```

8. Continuar solamente si termina con `PRE-FLIGHT: OK`.
9. Probar inicio de sesión y cierre de sesión con administrador, organizador y Ministro de Fe.
10. Confirmar que cada organizador y Ministro vea únicamente sus votaciones asignadas.
11. Desactivar el modo mantenimiento.

## Secretos

La aplicación carga primero el archivo indicado por `APP_SECURE_ENV_FILE`. Si no está definido, busca `/var/www/secure/vote.php`. Como último recurso usa `.env`.

El archivo PHP seguro debe estar fuera del directorio público y retornar un arreglo:

```php
<?php
return [
    'APP_ENV' => 'production',
    'APP_TIMEZONE' => 'America/Santiago',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'nombre_real',
    'DB_USER' => 'usuario_real',
    'DB_PASSWORD' => 'clave_real',
];
```

Nunca subir `.env`, respaldos SQL ni archivos de registro al repositorio.

## Usuarios nuevos

Crear un usuario habilita sus credenciales, pero no le concede acceso automático a todas las votaciones del cliente.

- Organizador: asignarlo desde el Centro de control de cada votación.
- Ministro de Fe: asignarlo desde Fiscalización de cada votación.

Esto permite que un cliente tenga procesos distintos con responsables diferentes.

## Diagnóstico

Los errores no controlados muestran un código de referencia y se registran mediante el log de PHP. Si `storage/logs` es escribible, también se guardan en `storage/logs/application.log`.

Para diagnosticar un fallo, buscar el código visible para el usuario en esos registros. Nunca activar `display_errors` en producción.

## Reinicio total conservando al administrador

Esta operación elimina definitivamente todas las organizaciones, votaciones, usuarios secundarios, padrones, votos, resultados, certificados y registros de auditoría de la base indicada. Reconstruye el esquema actual y conserva únicamente el administrador seleccionado, incluida su contraseña vigente.

1. Crear y verificar un respaldo SQL.
2. Confirmar el nombre real de la base y el correo del administrador.
3. Ejecutar desde la raíz del proyecto:

   ```bash
   php database/reset_production.php \
     --database=NOMBRE_REAL_DE_LA_BASE \
     --admin-email=CORREO_DEL_ADMIN \
     --confirm=DELETE-ALL-DATA
   ```

4. Verificar el resultado:

   ```bash
   php database/production_preflight.php
   ```

El script aborta si el nombre suministrado no coincide exactamente con la base conectada, si no encuentra exactamente un administrador o si falta la confirmación literal.
