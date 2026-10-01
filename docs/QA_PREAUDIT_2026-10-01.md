# Pre-auditoría QA — endurecimiento previo

Fecha: 2026-10-01

Antes de ejecutar la auditoría QA integral se incorporó una primera capa de controles que estaban pendientes en el MVP.

## Integrado
- Congelamiento irreversible de papeleta y censo al abrir una elección.
- Máquina de estados con transiciones permitidas; una elección cerrada o cancelada no vuelve a estados editables.
- Validación para impedir apertura sin preguntas o sin votantes habilitados.
- Eliminación controlada de preguntas únicamente antes de la apertura.
- Invitaciones/OTP sólo generables cuando la elección está abierta.
- Auditoría de transición de estado con estado anterior/nuevo.
- Migración preparada para branding por organización (logo/colores), locks de elección y documentos informativos adjuntos.

## Requiere migración
Ejecutar `database/migrations/20261001_harden_election_integrity.sql` sobre bases existentes antes de desplegar este código.

## Pendientes que no deben marcarse como implementados todavía
- XLSX real (CSV ya disponible).
- Branding completo en UI.
- Documentación adjunta visible en la papeleta.
- Multilenguaje.
- Voto delegado.
- Presencial/híbrido y modo quiosco.
- D'Hondt.
- Blockchain/sellado externo.
- OTP transaccional real por email/SMS.
- Alta disponibilidad y pentesting independiente.

Estos puntos se evaluarán/priorizarán después de la auditoría QA y no deben presentarse como capacidades productivas hasta estar implementados y probados.
