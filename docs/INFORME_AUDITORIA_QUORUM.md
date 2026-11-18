# Informe de auditoría electoral — DEMOCRA (legado QUORUM)

Fecha: 18-11-2026  
Etapa: 1 — auditoría estática  
Rama: `main`, sincronizada con `origin/main` al iniciar la revisión.  
Ejecución de pruebas en esta etapa: no autorizada; no ejecutada.

## Alcance y estado de Git

El repositorio es una aplicación PHP 8.3/MySQL, sin framework, con controladores en `public/`, servicios en `app/Services`, autenticación/autorización en `app/Auth` y `app/Core`, y DDL/migraciones en `database/`.

El árbol ya estaba modificado antes de esta auditoría por el cambio de marca DEMOCRA, la modalidad identificada y otros trabajos locales. Existen, entre otros, cambios pendientes en `VotingService`, `ResultService`, `schema.sql`, pantallas públicas y documentación. `tests/flow_regression.php` y `tests/sequential_regression.php` figuran eliminados. Esta auditoría no restaura ni descarta esos cambios.

Existe además una migración pendiente `20261118_identified_ballots.sql`. Según el historial de trabajo de esta sesión fue aplicada previamente a la base local, antes de recibir la presente instrucción. No se ejecutó ninguna migración durante esta etapa.

## Arquitectura encontrada

- Sesión administrativa: cookie PHP `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS; regeneración de ID al autenticar.
- Roles: `admin`, `organizer`, `witness`. La autorización combina rol, organización y asignaciones en `election_managers`/`election_witnesses`.
- Votante: enlace aleatorio de 256 bits almacenado como SHA-256, OTP con `password_hash`, expiración y bloqueo tras ocho intentos.
- Proceso: estados reales `draft`, `scheduled`, `open`, `closed`, `cancelled`.
- Flujos: tradicional (una papeleta completa) y secuencial (una respuesta por pregunta abierta).
- Persistencia: InnoDB, PDO con consultas preparadas, transacciones explícitas.
- Secreto: en modo secreto la papeleta no guarda identidad. `has_voted` y el token controlan participación por separado.
- Modo identificado pendiente: `ballots.census_member_id` se utiliza solo cuando `voting_mode=public`.
- Resultados: se calculan desde `ballots` y `ballot_answers`, no desde contadores incrementales; el certificado guarda JSON y SHA-256.
- Auditoría: tabla `audit_logs` append-only por convención de aplicación, sin protección criptográfica ni prohibición SQL de modificación.

## Flujo actual del sufragio

1. El administrador abre la elección; `setStatus()` bloquea la fila de elección, valida preguntas/padrón y fija los bloqueos de papeleta y censo.
2. Se genera una invitación dentro de una transacción, bloqueando primero elección y luego miembro. Invitaciones anteriores activas quedan revocadas.
3. El token resuelve elección y miembro. El OTP se valida y se marca `verified_at`.
4. `VotingService::submit()` vuelve a comprobar elegibilidad y reglas de selección en servidor.
5. Dentro de la transacción se bloquea la fila de elección y después la invitación/miembro.
6. Se inserta papeleta y respuestas; luego se actualizan atómicamente `has_voted` y `consumed_at`. Cualquier fallo revierte todo.
7. En flujo secuencial, la PK `(question_id,census_member_id)` impide una segunda respuesta a la misma pregunta.
8. El cierre bloquea la misma fila de elección que el voto, serializando voto versus cierre.

## Hallazgos

| ID | Criticidad | Evidencia | Riesgo | Estado |
|---|---|---|---|---|
| E-01 | Alta | La unicidad tradicional depende de `has_voted`, bloqueo de fila y actualización condicional; no existe un registro declarativo único de participación por elección/votante separado de la papeleta. | Una regresión futura que omita ese orden podría permitir duplicación. No se observó un bypass directo en el flujo actual. | Propuesta BD pendiente |
| E-02 | Alta | Un reenvío después de confirmar responde como invitación no habilitada; no existe clave de idempotencia ni recuperación del comprobante ya generado. | Ante timeout el votante no distingue voto persistido de fallo y puede generar soporte/incertidumbre electoral. | Pendiente |
| E-03 | Alta | Las FK de `ballot_answers` validan `question_id` y `option_id` por separado; no prueban que la alternativa pertenezca a la pregunta ni que la pregunta pertenezca a la elección de la papeleta. | Escrituras fuera del servicio o defectos futuros pueden producir resultados cruzados. La aplicación actual valida alternativas. | Propuesta BD pendiente |
| E-04 | Alta | En voto identificado, la FK de `ballots.census_member_id` no garantiza que el miembro pertenezca a `ballots.election_id`. | Asociación nominal cruzada entre procesos ante escritura defectuosa. | Propuesta BD pendiente |
| E-05 | Media | Los intentos rechazados de voto, token consumido, elección cerrada o selección inválida no generan eventos de auditoría específicos. | Reconstrucción incompleta de ataques y fallos operativos. | Pendiente de código |
| E-06 | Media | `audit_logs` no tiene hash encadenado, firma ni restricción técnica contra `UPDATE/DELETE`. | Un usuario de base de datos con permisos amplios puede alterar trazabilidad sin detección. | Propuesta BD/operación |
| E-07 | Media | `ElectionService::questions()` ejecuta una consulta de alternativas por pregunta. | N+1 y degradación en papeletas con muchas preguntas. | Pendiente de código |
| E-08 | Media | No hay límites explícitos de cantidad de preguntas, alternativas o tamaño máximo del padrón por proceso. | Abuso de recursos y tiempos de respuesta no acotados. | Pendiente de reglas de producto |
| E-09 | Media | `DECIMAL(12,4)` admite un máximo alto, pero los límites de peso y suma no están formalizados como invariante de negocio ni `CHECK` en el esquema base. | Resultados ponderados fuera del dominio esperado; riesgo de suma excesiva según escala real. | Requiere decisión de producto |
| E-10 | Media | La certificación protege el resumen agregado, pero no existe manifiesto de conteos/invariantes (papeletas, respuestas válidas, participaciones) previo a certificar. | Puede certificarse un conjunto internamente anómalo si la BD fue alterada por fuera de la aplicación. | Pendiente de código |
| E-11 | Baja | El rate limit de login vive en sesión del navegador. | Se evade cambiando sesión/IP/dispositivo; afecta cuentas administrativas, no la unicidad transaccional del voto. | Pendiente |
| E-12 | Baja | Los nombres internos y documentación mezclan QUORUM/DEMOCRA/INAUDIS durante una migración de marca. | Riesgo operativo y de despliegue, no integridad matemática. | Trabajo local pendiente |

## Controles que sí existen

- CSRF en operaciones POST y consultas PDO preparadas.
- Comprobación de pertenencia de preguntas/alternativas en el servicio de voto.
- Orden de bloqueos consistente elección → token/miembro para voto, invitación y cierre.
- Rollback ante cualquier excepción.
- Actualización condicional `has_voted=0` y `consumed_at IS NULL`.
- Estado, horario, elegibilidad y OTP revalidados en servidor.
- Transiciones terminales: cerrado/cancelado no se reabren.
- Papeleta y padrón bloqueados al abrir.
- Resultados ocultos hasta cierre.
- PK de participación secuencial.
- Tipos `BIGINT UNSIGNED` para identificadores y agregación desde registros persistentes.

## Archivos candidatos para Etapa 2

- `app/Services/VotingService.php`: idempotencia, errores tipados y auditoría de rechazos.
- `app/Services/ResultService.php`: invariantes antes de certificar.
- `app/Services/ElectionService.php`: límites y validación de apertura.
- `app/Services/ElectionService.php::questions()`: eliminar N+1.
- `database/schema.sql` y nueva migración: participación única y FK compuestas.
- `public/vote.php`: distinguir voto previo de solicitud inválida sin revelar contenido.
- Pruebas: restaurar/revisar regresiones eliminadas y agregar concurrencia controlada.

## Plan propuesto

1. Acordar reglas máximas y semántica de idempotencia.
2. Aprobar cambios estructurales descritos en `CAMBIOS_BD_QUORUM.md`.
3. Implementar primero integridad declarativa y luego adaptar servicios.
4. Agregar auditoría de rechazos sin identidad/preferencia en voto secreto.
5. Agregar invariantes de certificación.
6. Ejecutar pruebas funcionales en una base aislada y, con autorización adicional, concurrencia/carga.
7. Revisar planes `EXPLAIN` y tiempos antes de producción.

## Actualización de Etapa 2

Con autorización expresa se implementaron E-01, E-02, E-05, E-07 y controles parciales para E-03, E-09 y E-10. Las tablas de participación e idempotencia fueron creadas en la base local. Se agregaron invariantes previas a certificación y se eliminó el N+1. Las FK compuestas y la cadena criptográfica de auditoría permanecen pendientes porque requieren un diseño operacional adicional.

No se declara cumplimiento normativo ni cumplimiento de los criterios de aceptación sin pruebas autorizadas y evidencia de un ambiente equivalente a producción.
