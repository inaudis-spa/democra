# Changelog — INAUDIS Vote

Se documentan cambios relevantes del proyecto desde el inicio del repositorio.

## 2026-09-02

### Documentación inicial
- Definición del alcance del Demo/MVP v1.
- Definición del stack técnico obligatorio de INAUDIS.
- Documentación de arquitectura, seguridad y roadmap.
- Configuración de `.gitignore` y `.env.example`.

### Base técnica
- Bootstrap PHP y carga de variables de entorno.
- Conexión MySQL mediante PDO.
- Autenticación y sesiones.
- Autorización por roles.
- Protección CSRF.
- Auditoría centralizada.
- GitHub Actions con PHP 8.3 lint.

### Seguridad de acceso
- `password_hash()` / `password_verify()`.
- Regeneración de sesión al autenticar.
- Cookies HttpOnly/SameSite y Secure bajo HTTPS.
- Rate limiting básico de login por sesión.
- Prepared statements en consultas del MVP.

### Organizaciones y usuarios
- Alta/listado de organizaciones.
- Alta de administradores/organizadores.
- Asociación de organizadores con organización.
- Activación/desactivación de usuarios.

### Votaciones
- Creación de procesos electorales.
- Estados de ciclo de vida.
- Voto secreto/público.
- Voto ponderado.
- Fechas de apertura/cierre.
- Preguntas de selección simple/múltiple.
- Alternativas configurables.

### Censo
- Alta manual.
- Importación CSV por texto y archivo `.csv`.
- Campos RUT, nombre, email, teléfono, peso y segmento.

### Identidad e invitaciones
- Token de invitación aleatorio, persistido solamente como SHA-256.
- OTP de seis dígitos persistido con hash.
- Expiración de OTP a 10 minutos.
- Verificación OTP previa a la papeleta.
- Modo demo muestra OTP al administrador; pendiente canal productivo de email/SMS.

### Votación
- Papeleta web responsive.
- Validación de elegibilidad, estado, fechas y OTP.
- Validación de alternativas contra configuración real.
- Transacción MySQL para registro de voto.
- Prevención atómica de doble voto.
- Consumo único de invitación.
- Separación entre identidad (`census_members`/`voter_tokens`) y papeleta (`ballots`).
- Comprobante SHA-256 posterior a la emisión.

### Resultados
- Conteo de votos.
- Conteo ponderado.
- Métricas de participación.
- Certificado SHA-256 de resultados.
- Persistencia de certificados.
- Generación y descarga de acta PDF sin framework externo.

### Interfaz
- Login.
- Dashboard con datos reales.
- Organizaciones.
- Usuarios.
- Votaciones.
- Configuración de papeleta.
- Censo.
- Portal del votante.
- Resultados.
- Auditoría.

### Datos y operación
- `database/seed_demo.sql` para escenario reproducible.
- `docs/RUNBOOK_LOCAL.md` con instalación y prueba de humo.

### Estado
La base funcional del MVP está implementada en código. Antes de declararlo validado se debe completar una ejecución real de punta a punta sobre Apache + PHP 8.3 + MySQL, verificar CI en verde y cerrar los hallazgos de la prueba de humo.
