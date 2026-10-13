# INAUDIS Vote

## Documentación de usuario

- [Manual completo por perfiles y menú lateral](docs/MANUAL_USUARIO.md)
- [Procedimiento de despliegue a producción](docs/RUNBOOK_PRODUCCION.md)

Plataforma SaaS de votación electrónica, gestión de censos, procesos electorales, auditoría y generación de actas.

## Objetivo
Construir un demo funcional que permita configurar, ejecutar y auditar procesos de votación digital, dejando una base técnica reutilizable para evolucionar a MVP, piloto y producto productivo.

## Stack técnico oficial INAUDIS
- HTML5
- JavaScript Vanilla
- Tailwind CSS
- PHP 8.3
- Apache
- MySQL

### Criterios técnicos
- Sin frameworks frontend pesados.
- Backend en PHP 8.3.
- Persistencia en MySQL.
- Servidor objetivo Linux + Apache.
- Arquitectura modular, mantenible y preparada para crecer.
- Variables sensibles fuera del repositorio mediante `.env`.

## Alcance Demo v1
1. Login y roles.
2. Organizaciones.
3. Gestión de censos.
4. Configuración de votaciones.
5. Editor de preguntas y alternativas.
6. Invitaciones y OTP por email.
7. Portal de votación.
8. Dashboard de participación.
9. Resultados y gráficos.
10. Acta PDF, auditoría y hash SHA-256.

## Evolución prevista
Demo → MVP → Piloto → Productivo → Auditoría/Certificación.

## Documentación
- `docs/PRD.md`
- `docs/ARQUITECTURA.md`
- `docs/SEGURIDAD.md`
- `docs/ROADMAP.md`

© INAUDIS SpA
