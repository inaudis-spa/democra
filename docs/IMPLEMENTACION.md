# Implementación — INAUDIS Vote

Este documento registra decisiones técnicas y el estado real de implementación del Demo/MVP v1.

## Convenciones obligatorias
- Stack: HTML5, JavaScript Vanilla, Tailwind CSS, PHP 8.3, Apache y MySQL.
- Sin frameworks frontend ni backend en el MVP v1.
- PDO con prepared statements para acceso a MySQL.
- `utf8mb4` en base de datos.
- Sesiones PHP con cookies `HttpOnly`, `SameSite=Lax` y `Secure` cuando exista HTTPS.
- Secretos y credenciales exclusivamente vía `.env`; nunca se versionan.
- Auditoría de eventos administrativos, identidad y votación.
- Separación lógica entre identidad del votante y contenido del voto.

## Estado implementado
### Núcleo técnico
- `config/bootstrap.php`: entorno, sesión y autoload.
- `app/Core/Database.php`: conexión MySQL por PDO.
- `app/Core/Csrf.php`: generación/validación CSRF.
- `app/Core/Authorization.php`: autenticación y roles.
- `app/Core/Audit.php`: auditoría centralizada.
- GitHub Actions `PHP Lint` sobre PHP 8.3.

### Autenticación y roles
- Login/logout real contra MySQL.
- Contraseñas con `password_hash()` / `password_verify()`.
- Regeneración de sesión tras login.
- CSRF en login y formularios administrativos.
- Limitación básica de intentos de login por sesión (8 intentos / 15 minutos).
- Roles `admin` y `organizer` operativos.
- Administración de usuarios y activación/desactivación.

### Organizaciones
- Alta de organizaciones.
- Listado y estado.
- Asociación de organizadores a organización.

### Votaciones
- Crear proceso electoral.
- Modo secreto o público.
- Fechas de inicio/término.
- Voto ponderado configurable.
- Estados: borrador, programada, abierta, cerrada, cancelada.
- Editor de preguntas de selección única o múltiple.
- Alternativas por pregunta.

### Censo
- Alta manual de votantes.
- Importación CSV mediante contenido pegado.
- RUT, nombre, email, teléfono, peso y segmento.
- Prevención de duplicado por email dentro de la elección mediante constraint SQL.

### Invitación y OTP
- Token aleatorio de 256 bits; en base se persiste únicamente SHA-256.
- OTP de seis dígitos almacenado con `password_hash()`.
- Expiración OTP a 10 minutos.
- Verificación OTP obligatoria antes de mostrar/habilitar papeleta.
- En modo demo se muestra enlace + OTP al administrador. En productivo debe enviarse por email/SMS y ocultarse del panel.

### Emisión del voto
- Validación de token, elegibilidad, estado, fechas y OTP.
- Validación de alternativas contra configuración real de la papeleta.
- Operación transaccional MySQL.
- Bloqueo atómico del doble voto (`has_voted=0` + `rowCount`).
- Consumo único del token.
- `ballots` no contiene `census_member_id` ni identidad del votante.
- Comprobante SHA-256 generado tras voto válido.

### Resultados y auditoría
- Conteo normal y ponderado.
- Participación total/emitida/porcentaje.
- Visualización de resultados.
- Certificado SHA-256 sobre JSON canónico de resultados.
- Registro persistente en `result_certificates`.
- Visor de los últimos eventos de auditoría.

### Dashboard
- Datos reales de organizaciones, votaciones abiertas, censo, participación y eventos auditados.
- Procesos recientes navegables.

### Datos demo
- `database/seed_demo.sql` crea organización, admin, organizador, elección, papeleta y censo de prueba.
- Credenciales demo declaradas dentro del seed y explícitamente marcadas para uso local únicamente.

## Flujo MVP disponible
1. Administrador inicia sesión.
2. Crea organización y/o usuario organizador.
3. Crea votación.
4. Configura pregunta(s) y alternativas.
5. Carga censo manual o CSV.
6. Abre el proceso.
7. Genera invitación + OTP.
8. Votante abre enlace y valida OTP.
9. Votante emite papeleta.
10. Plataforma bloquea segundo voto y entrega comprobante.
11. Administrador visualiza participación/resultados.
12. Genera certificado SHA-256 y consulta auditoría.

## Pendientes antes de declarar MVP validado
- Ejecutar instalación real sobre Apache/PHP 8.3/MySQL y completar prueba de humo de punta a punta.
- Confirmar CI PHP Lint en verde después del último cambio.
- Añadir envío real de OTP por email (SMTP/API); actualmente es flujo demo.
- Añadir carga de archivo CSV/Excel real; actualmente existe importación CSV por texto.
- Generar acta PDF (el hash/certificado ya existe; falta render PDF).
- Pruebas de autorización cruzada entre organizaciones.
- Pruebas de concurrencia de doble voto.

## Criterio de avance
No se considera cerrado un módulo solamente por existir su interfaz. Cada módulo debe contar con persistencia, validación, control de acceso, auditoría cuando corresponda y documentación actualizada.
