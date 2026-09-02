# Implementación — INAUDIS Vote

Este documento registra decisiones técnicas y el estado real de implementación del Demo v1.

## Convenciones obligatorias
- Stack: HTML5, JavaScript Vanilla, Tailwind CSS, PHP 8.3, Apache y MySQL.
- Sin frameworks frontend ni backend en el Demo v1.
- PDO con prepared statements para acceso a MySQL.
- `utf8mb4` en base de datos.
- Sesiones PHP con cookies `HttpOnly`, `SameSite=Lax` y `Secure` cuando exista HTTPS.
- Secretos y credenciales exclusivamente vía `.env`; nunca se versionan.
- Auditoría de eventos administrativos y de autenticación.
- Separación lógica entre identidad del votante y contenido del voto.

## Fase 1 — Base técnica y autenticación
### Implementado
- `config/bootstrap.php`: carga de entorno, sesión segura y autoload interno.
- `app/Core/Database.php`: conexión MySQL vía PDO.
- `app/Auth/AuthService.php`: autenticación, sesión, roles base y registro de login exitoso.
- `public/index.php`: punto de entrada.
- `public/login.php`: pantalla de acceso responsive.
- `public/dashboard.php`: shell inicial del panel autenticado.
- `public/logout.php`: cierre de sesión.
- `database/schema.sql`: modelo inicial de organizaciones, usuarios, elecciones, censo, preguntas, opciones, tokens, papeletas, respuestas, auditoría y certificados de resultados.

## Modelo de seguridad inicial
1. Las contraseñas se almacenan únicamente con `password_hash()` y se validan con `password_verify()`.
2. Se regenera el ID de sesión al autenticar.
3. Las consultas de autenticación utilizan prepared statements.
4. El login exitoso genera una entrada en `audit_logs`.
5. El voto secreto se modela evitando guardar identidad del votante dentro de `ballots`.
6. El vínculo de elegibilidad se mantiene en `voter_tokens` y `census_members`; la papeleta usa un `ballot_token` independiente.

## Pendiente inmediato
- Script seguro para crear el primer administrador.
- Middleware/helpers de autorización por rol.
- CRUD de organizaciones.
- Dashboard conectado a datos reales.
- Registro de intentos de login fallidos y rate limiting básico.
- Protección CSRF en formularios administrativos.
- Pruebas funcionales del flujo de autenticación.

## Criterio de avance
No se considera cerrado un módulo solamente por existir su interfaz. Cada módulo debe contar con persistencia, validación, control de acceso, auditoría cuando corresponda y documentación actualizada.
