# Seguridad — DEMOCRA Demo v1

## Objetivo
Aplicar controles de seguridad razonables desde el demo sin presentar esta versión como certificada ni apta todavía para procesos regulados en producción.

## Controles mínimos
- HTTPS en ambientes publicados.
- Contraseñas almacenadas mediante hash seguro de PHP (`password_hash`).
- Sesiones seguras y expiración controlada.
- Protección CSRF en operaciones administrativas.
- Consultas parametrizadas/PDO para prevenir SQL Injection.
- Escape de salida para reducir XSS.
- Validación server-side de todos los datos.
- Roles y autorización verificadas en backend.
- OTP de un solo uso con expiración.
- Prevención de doble voto.
- Registro de auditoría para acciones críticas.
- Secretos y credenciales fuera de Git mediante `.env`.

## Privacidad del voto
En procesos secretos, evitar almacenar una asociación directa entre identidad personal y alternativa seleccionada. La validación de habilitación y el registro de emisión deben desacoplarse del contenido de la papeleta.

## Integridad
Al cierre del proceso se generará un hash SHA-256 de la evidencia/acta para demostrar si el documento cambia posteriormente.

## No incluido aún
- Certificación ENS u otra certificación equivalente.
- Pentesting certificado.
- HSM/KMS.
- SIEM/SOC.
- WAF/DDoS enterprise.
- Blockchain productiva.
- Auditoría criptográfica externa.
- Alta disponibilidad multi-zona.

Estos elementos corresponden a fases posteriores antes de utilizar el producto en procesos críticos o regulados.
