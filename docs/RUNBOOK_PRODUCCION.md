# Despliegue a producción

## Regla principal

El código y la base de datos se despliegan como una sola versión. Subir PHP sin aplicar sus migraciones puede dejar perfiles completos fuera del sistema.

## Orden seguro

1. Activar una ventana de mantenimiento.
2. Respaldar la base de datos de producción y verificar el respaldo (tamaño y cantidad de tablas).
3. Subir el código nuevo sin reemplazar los secretos del servidor. Instalar las dependencias desde el lock file versionado (ver la sección "Dependencias"): `php83 composer install --no-dev --optimize-autoloader`.
4. Seleccionar explícitamente la base correcta en el panel SQL.
5. Identificar las diferencias de esquema antes de tocar nada. El script `database/production_preflight.php` fue retirado del repositorio; la verificación manual es:

   ```sql
   SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'NOMBRE_REAL_DE_LA_BASE'
    ORDER BY TABLE_NAME;
   ```

   Contrastar el resultado con `database/schema.sql` (16 tablas) y con las migraciones de la lista siguiente. Si una tabla o columna falta, la migración que la crea está pendiente.

6. Ejecutar las migraciones pendientes en orden. No todas son idempotentes: las que agregan columnas con `ALTER TABLE ... ADD COLUMN` fallan con "Duplicate column" si esa columna ya existe, y las que reescriben una clave foránea fallan si la restricción no existe con ese nombre. Ejecutar solo las que falten, según el contraste del paso 5.

   El nombre de la base debe sustituirse, no neutralizarse: `USE` y `CREATE DATABASE` apuntan a `inaudis_vote`, y borrar esas líneas deja el `CREATE DATABASE` partido en varias líneas y rompe el import. Sustituir el nombre cubre ambos casos:

   ```bash
   for f in database/migrations/*.sql; do
     sed 's/inaudis_vote/NOMBRE_REAL_DE_LA_BASE/g' "$f" \
       | mysql -uroot NOMBRE_REAL_DE_LA_BASE
   done
   ```

   El orden completo es:

   - `database/migrations/20261001_harden_election_integrity.sql`
   - `database/migrations/20261005_live_control_room.sql`
   - `database/migrations/20261005_ministro_fe.sql`
   - `database/migrations/20261009_access_integrity.sql`
   - `database/migrations/20261104_invitation_dispatch.sql`
   - `database/migrations/20261114_invitation_cleanup_cascade.sql`
   - `database/migrations/20261116_census_field_templates.sql`
   - `database/migrations/20261116_election_otp_ttl.sql`
   - `database/migrations/20261116_invitation_queue_compat.sql`
   - `database/migrations/20261116_sequential_questions.sql`

7. Volver a ejecutar la verificación del paso 5 y confirmar que las 23 tablas están presentes: las 16 de `schema.sql` más `invitation_campaigns`, `invitation_deliveries`, `invitation_delivery_events`, `organization_census_templates`, `election_census_templates`, `census_custom_values` y `question_participations`.

8. Continuar solamente si el esquema coincide con lo que el código desplegado espera.
9. Probar inicio de sesión y cierre de sesión con administrador, organizador y Ministro de Fe.
10. Confirmar que cada organizador y Ministro vea únicamente sus votaciones asignadas.
11. Desactivar el modo mantenimiento.

## Verificación de consistencia

El repositorio es la fuente de verdad. Para comprobar que el estado de la base desplegada corresponde exactamente a lo que el repositorio produce, se puede reconstruir el esquema en una base temporal y compararlo.

Esto no toca la base de producción y debe ejecutarse con la aplicación en mantenimiento.

```bash
DB_VERIFY=NOMBRE_REAL_DE_LA_BASE_verify
mysql -uroot -e "DROP DATABASE IF EXISTS ${DB_VERIFY}; \
  CREATE DATABASE ${DB_VERIFY} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

sed "s/inaudis_vote/${DB_VERIFY}/g" database/schema.sql \
  | mysql -uroot "${DB_VERIFY}"
for f in database/migrations/*.sql; do
  sed "s/inaudis_vote/${DB_VERIFY}/g" "$f" | mysql -uroot "${DB_VERIFY}"
done

# Comparar tablas
mysql -uroot -N -e "SELECT TABLE_NAME FROM information_schema.TABLES \
  WHERE TABLE_SCHEMA='NOMBRE_REAL_DE_LA_BASE' ORDER BY TABLE_NAME;" \
  > /tmp/tablas_prod.txt
mysql -uroot -N -e "SELECT TABLE_NAME FROM information_schema.TABLES \
  WHERE TABLE_SCHEMA='${DB_VERIFY}' ORDER BY TABLE_NAME;" \
  > /tmp/tablas_verify.txt
diff /tmp/tablas_prod.txt /tmp/tablas_verify.txt

# Comparar estructura: extraer SHOW CREATE TABLE de cada tabla y comparar
dump_schema() {
  local db="$1" out="$2"
  : > "${out}"
  while read -r t; do
    echo "### ${t}" >> "${out}"
    mysql -uroot -N -e "SHOW CREATE TABLE \`${db}\`.\`${t}\`;" | sed -n '2p' >> "${out}"
  done < <(mysql -uroot -N -e "SELECT TABLE_NAME FROM information_schema.TABLES \
    WHERE TABLE_SCHEMA='${db}' ORDER BY TABLE_NAME;")
}
dump_schema NOMBRE_REAL_DE_LA_BASE /tmp/schema_prod.txt
dump_schema "${DB_VERIFY}" /tmp/schema_verify.txt
diff /tmp/schema_prod.txt /tmp/schema_verify.txt
```

Sin diferencias, el esquema desplegado es el que produce el repositorio. Con diferencias, falta una migración o el estado fue alterado a mano.

Limpiar al terminar:

```bash
mysql -uroot -e "DROP DATABASE ${DB_VERIFY};"
```

## Secretos

La configuración vive en `.env`, en la raíz del proyecto, con permisos `640` y propietario `root:apache`. Está en `.gitignore` y el vhost bloquea su acceso HTTP (`RewriteRule ^(\.env|\.git|\.github) - [F,L]`).

Formato `CLAVE=VALOR`, sin comillas y sin espacios alrededor del `=`. La aplicación carga además el archivo indicado por `APP_SECURE_ENV_FILE`, si está definido, y tiene prioridad sobre `.env`.

Variables requeridas:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ejemplo.com
APP_TIMEZONE=America/Santiago

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=nombre_real
DB_USER=usuario_real
DB_PASSWORD=clave_real

MAIL_HOST=smtp.ejemplo.com
MAIL_PORT=587
MAIL_USER=cuenta_correo
MAIL_PASSWORD=clave
MAIL_FROM=remitente@ejemplo.com
MAIL_FROM_NAME=DEMOCRA · VOTO DIGITAL
MAIL_ENCRYPTION=tls

OTP_TTL_SECONDS=300
SESSION_TTL_MINUTES=60
```

Sin `MAIL_HOST` y `MAIL_PASSWORD` configurados, la generación de credenciales de votante falla de forma explícita.

Nunca subir `.env`, respaldos SQL ni archivos de registro al repositorio.

## Dependencias

`vendor/` está en `.gitignore`: las dependencias se instalan en el servidor desde el lock file versionado.

```bash
php83 composer install --no-dev --optimize-autoloader
```

`composer install` exige PHP 8.3. Si el `php` por defecto del servidor es una versión anterior, invocar el binario correcto de forma explícita. Sin `vendor/autoload.php` la aplicación lanza un error al enviar invitaciones.

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
   sed 's/inaudis_vote/NOMBRE_REAL_DE_LA_BASE/g' database/schema.sql \
     | mysql -uroot NOMBRE_REAL_DE_LA_BASE
   for f in database/migrations/*.sql; do
     sed 's/inaudis_vote/NOMBRE_REAL_DE_LA_BASE/g' "$f" \
       | mysql -uroot NOMBRE_REAL_DE_LA_BASE
   done
   ```

   `database/schema.sql` contiene un `CREATE DATABASE` de varias líneas junto a un `USE`, ambos con el nombre fijo `inaudis_vote`. Sustituir el nombre es obligatorio: borrar esas líneas deja el `CREATE DATABASE` partido y el import falla con un error de sintaxis.

6. Recrear el administrador preservado:

   ```sql
   INSERT INTO users (organization_id, name, email, password_hash, role, status)
   VALUES (NULL, 'NOMBRE', 'CORREO_DEL_ADMIN', 'HASH_PRESERVADO', 'admin', 'active');
   ```

7. Verificar el resultado con la consulta del paso 5 y comprobar que las 19 tablas existen y que el administrador puede iniciar sesión.

Esta operación no es reversible con un `git reset`: el rollback es restaurar el respaldo SQL del paso 1.
