# Estado MVP — INAUDIS Vote

Fecha de corte: 2026-09-02

## Implementado en código

- [x] Stack estándar INAUDIS: HTML5 + JavaScript Vanilla + Tailwind CSS + PHP 8.3 + Apache + MySQL.
- [x] Autenticación administrativa.
- [x] Roles administrador / organizador.
- [x] Gestión de usuarios administrativos.
- [x] Gestión de organizaciones.
- [x] Dashboard con métricas reales.
- [x] Creación de votaciones.
- [x] Ciclo de estados de votación.
- [x] Voto secreto y público a nivel de configuración.
- [x] Voto ponderado.
- [x] Preguntas simples y múltiples.
- [x] Alternativas configurables.
- [x] Censo manual.
- [x] Importación de archivo CSV.
- [x] Token de invitación de un solo uso.
- [x] OTP de seis dígitos con expiración.
- [x] Portal de votación responsive.
- [x] Separación identidad / papeleta.
- [x] Prevención transaccional de doble voto.
- [x] Comprobante SHA-256 del voto.
- [x] Resultados normales y ponderados.
- [x] Métricas de participación.
- [x] Certificado SHA-256 de resultados.
- [x] Acta PDF descargable.
- [x] Auditoría de eventos.
- [x] Seed reproducible de demostración.
- [x] CI de sintaxis PHP 8.3.
- [x] Runbook de instalación y prueba de humo.

## Pendiente de validación técnica

- [ ] Ejecutar el sistema sobre Apache + PHP 8.3 + MySQL real.
- [ ] Importar `schema.sql` y `seed_demo.sql` en una base limpia.
- [ ] Completar prueba punta a punta con navegador.
- [ ] Confirmar todos los jobs de PHP Lint en verde.
- [ ] Prueba concurrente para doble voto.
- [ ] Pruebas de aislamiento multi-organización.
- [ ] Validar descarga/apertura del PDF en Windows, iOS y Android.

## Pendiente para pasar de MVP demo a piloto productivo

- [ ] Envío real de OTP mediante email transaccional.
- [ ] SMS OTP opcional.
- [ ] Recuperación de contraseña.
- [ ] Gestión de branding/logo por organización.
- [ ] Importación Excel XLSX además de CSV.
- [ ] Edición/eliminación controlada de preguntas antes de abrir una elección.
- [ ] Bloquear cambios de papeleta/censo según estado del proceso.
- [ ] Mejorar políticas de rate limiting a nivel servidor/IP.
- [ ] Logs y monitoreo centralizados.
- [ ] Backups automatizados.
- [ ] Pentesting independiente.
- [ ] Revisión legal/regulatoria para procesos laborales en Chile.

## Regla de cierre

El proyecto se puede llamar **MVP funcional validado** únicamente después de completar la sección “Pendiente de validación técnica” sobre un ambiente ejecutable real. El código actual representa una implementación MVP en repositorio, no una certificación de seguridad ni autorización regulatoria.
