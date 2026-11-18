# Runbook local — DEMOCRA MVP

## Requisitos
- Apache 2.4+
- PHP 8.3 con extensiones `pdo_mysql`, `mbstring`, `json`, `iconv`
- MySQL 8+
- Navegador moderno

## Instalación
1. Clonar `inaudis-spa/inaudis-vote`.
2. Copiar `.env.example` a `.env`.
3. Configurar credenciales MySQL en `.env`.
4. Importar `database/schema.sql`.
5. Para una demo rápida, importar `database/seed_demo.sql`.
6. Configurar Apache para que `public/` sea el DocumentRoot del sitio.
7. Habilitar HTTPS cuando el entorno no sea exclusivamente local.
8. Abrir `/login.php`.

## Credenciales del seed local
- Administrador: `admin@inaudis.local`
- Organizador: `organizador@inaudis.local`
- Contraseña temporal: `Demo2026!`

Estas credenciales son exclusivamente para desarrollo local y deben eliminarse/cambiarse en cualquier ambiente compartido.

## Prueba de humo MVP
1. Iniciar sesión como administrador.
2. Revisar dashboard.
3. Crear organización o usar `Sindicato Demo DEMOCRA`.
4. Crear una votación o abrir `Elección Directorio Demo 2026`.
5. Agregar/validar preguntas y alternativas.
6. Cargar votantes por CSV o manualmente.
7. Dejar la elección en estado `open`.
8. Generar invitación para un votante.
9. Copiar enlace y OTP demo.
10. Abrir el enlace en ventana privada/incógnito.
11. Validar OTP.
12. Emitir voto.
13. Intentar reutilizar el enlace: debe quedar bloqueado.
14. Volver al panel y validar participación/resultados.
15. Generar certificado SHA-256.
16. Descargar acta PDF.
17. Revisar auditoría.

## Condiciones mínimas de aceptación
- No es posible votar dos veces con la misma identidad/invitación.
- Una invitación sin OTP válido no permite acceder a la papeleta.
- La papeleta no almacena `census_member_id`.
- Resultados reflejan el voto emitido.
- Auditoría registra los hitos relevantes.
- Acta PDF descarga correctamente.
- GitHub Actions PHP Lint finaliza en verde.

## Fuera del MVP actual
- Certificación legal/regulatoria.
- Blockchain productiva.
- Pentesting certificado.
- Kubernetes/alta disponibilidad enterprise.
- SMS productivo.
- ClaveÚnica.
