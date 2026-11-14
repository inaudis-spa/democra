# Despliegue a producción

## Regla principal

El código y la base de datos se despliegan como una sola versión. Subir PHP sin aplicar sus migraciones puede dejar perfiles completos fuera del sistema.

## Orden seguro

1. Activar una ventana de mantenimiento.
2. Respaldar la base de datos de producción y verificar el respaldo (tamaño y cantidad de tablas).
3. Subir el código nuevo sin reemplazar los secretos del servidor.
4. Seleccionar explícitamente la base correcta en el panel SQL.
5. Identificar las diferencias de esquema antes de tocar nada. El script `database/production_preflight.php` fue retirado del repositorio; la verificación manual es:

   ```sql
   SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'NOMBRE_REAL_DE_LA_BASE'
    ORDER BY TABLE_NAME;
   ```

   Contrastar el resultado con `database/schema.sql` (16 tablas) y con las migraciones de la lista siguiente. Si una tabla o columna falta, la migración que la crea está pendiente.

6. Ejecutar las migraciones pendientes en orden. No todas son idempotentes: las que agregan columnas con `ALTER TABLE ... ADD COLUMN` fallan con "Duplicate column" si esa columna ya existe, y las que reescriben una clave foránea fallan si la restricción no existe con ese nombre. Ejecutar solo las que falten, según el contraste del paso 5. El `USE` de las migraciones `20261001` y `20261009` debe neutralizarse si el nombre de la base no coincide con el declarado:

   ```bash
   for f in database/migrations/*.sql; do
     sed 's/^USE .*;//' "$f" | mysql -uroot NOMBRE_REAL_DE_LA_BASE
   done
   ```

   El orden completo es:

   - `database/migrations/20261001_harden_election_integrity.sql`
   - `database/migrations/20261005_live_control_room.sql`
   - `database/migrations/20261005_ministro_fe.sql`
   - `database/migrations/20261009_access_integrity.sql`
   - `database/migrations/20261104_invitation_dispatch.sql`
   - `database/migrations/20261114_invitation_cleanup_cascade.sql`

7. Volver a ejecutar la verificación del paso 5 y confirmar que las 19 tablas están presentes (16 de `schema.sql` más `invitation_campaigns`, `invitation_deliveries`, `invitation_delivery_events`).

8. Continuar solamente si el esquema coincide con lo que el código desplegado espera.
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
- Ministro de Fe: asignarlo desde la Fiscalización de cada votación.

Esto permite que un cliente tenga procesos distintos con responsables diferentes.

## Diagnóstico

Los errores no controlados muestran un código de referencia y se registran mediante el log de PHP. Si `storage/logs` es escribible, también se guardan en `storage/logs/application.log`.

Para diagnosticar un fallo, buscar el código visible para el usuario en esos registros. Nunca activar `display_errors` en producción.

## Reinicio total conservando al administrador

Esta operación elimina definitivamente todas las organizaciones, votaciones, usuarios secundarios, padrones, votos, resultados, certificados, invitaciones y registros de auditoría de la base indicada. Reconstruye el esquema actual y conserva únicamente el administrador seleccionado, incluida su contraseña vigente.

El script `database/reset_production.php` fue retirado del repositorio. El procedimiento equivalente es manual y se ejecuta con confirmación explícita del responsable de producción.

1. Crear y verificar un respaldo SQL.
2. Confirmar el nombre real de la base y el correo del administrador.
3. Preservar la fila del administrador antes de eliminar nada:

   ```sql
   SELECT id, name, email, password_hash, role, status
     FROM users WHERE email = 'CORREO_DEL_ADMIN';
   ```

   Guardar el valor de `password_hash` en un lugar seguro. Es la única forma de conservar la contraseña vigente: la aplicación la genera con `password_hash()` y no admite lectura en claro.

4. Reconstruir el esquema:

   ```sql
   DROP DATABASE NOMBRE_REAL_DE_LA_BASE;
   CREATE DATABASE NOMBRE_REAL_DE_LA_BASE
     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. Importar el esquema base y reaplicar las migraciones:

   ```bash
   mysql -uroot NOMBRE_REAL_DE_LA_BASE < database/schema.sql
   for f in database/migrations/*.sql; do
     sed 's/^USE .*;//' "$f" | mysql -uroot NOMBRE_REAL_DE_LA_BASE
   done
   ```

   `database/schema.sql` contiene `USE inaudis_vote` y un `CREATE DATABASE` con nombre fijo: si la base real tiene otro nombre, eliminar esas dos líneas antes de importar.

6. Recrear el administrador preservado:

   ```sql
   INSERT INTO users (organization_id, name, email, password_hash, role, status)
   VALUES (NULL, 'NOMBRE', 'CORREO_DEL_ADMIN', 'HASH_PRESERVADO', 'admin', 'active');
   ```

7. Verificar el resultado con la consulta del paso 5 y comprobar que las 19 tablas existen y que el administrador puede iniciar sesión.

Esta operación no es reversible con un `git reset`: el rollback es restaurar el respaldo SQL del paso 1.
