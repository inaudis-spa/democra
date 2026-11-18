# Hardening electoral — estado y propuesta

## Estado de esta entrega

Etapa 2 implementada en código y base local con autorización del usuario. No se ejecutaron pruebas funcionales que generen datos, pruebas concurrentes ni carga. Solo se verificó sintaxis PHP y presencia de las tablas migradas.

## Protecciones implementadas en Etapa 2

- `election_participations`: PK por elección/votante separada de la papeleta y sus respuestas.
- `vote_submissions`: idempotencia por token y ámbito (proceso tradicional o pregunta secuencial).
- Recuperación del mismo comprobante ante reenvío ya confirmado.
- Auditoría categorizada de rechazos sin guardar preferencia ni identidad del voto secreto.
- Invariantes obligatorias antes de certificar resultados.
- Bloqueo de certificación ante relaciones cruzadas, pesos negativos, conteos incompatibles o selecciones fuera de regla.
- Carga de preguntas y opciones en dos consultas, eliminando el N+1.
- Clave de idempotencia incluida en formularios reales de voto.
- Migración reversible `20261118_vote_integrity_hardening.sql` y rollback separado.

## Protecciones presentes

- Transacción única para papeleta, respuestas, participación y consumo de token.
- `SELECT ... FOR UPDATE` sobre la elección para serializar sufragio y cierre.
- Bloqueo del token/miembro antes de persistir.
- Actualizaciones condicionales para impedir segundo uso.
- OTP temporal, hash seguro, máximo de intentos y revocación de invitaciones anteriores.
- Validación servidor de pregunta, alternativa, mínimos y máximos.
- Estados terminales no reabribles.
- Papeleta y padrón inmutables desde la apertura.
- PK `(question_id,census_member_id)` en participación secuencial.
- Certificación SHA-256 del resumen definitivo.
- Autorización por rol/asignación y CSRF en endpoints sensibles.

## Diseño implementado y mejoras posteriores

### Idempotencia implementada

Aceptar una clave aleatoria por presentación. La primera operación guarda de forma atómica un recibo; una repetición exacta devuelve “solicitud previamente procesada” y el mismo comprobante, sin insertar otra papeleta. No se debe guardar la selección en el registro de idempotencia.

### Rechazos auditables implementados

Registrar categorías: token inválido/consumido, miembro no habilitado, proceso fuera de estado/horario, selección inválida y conflicto concurrente. Para voto secreto no registrar nombre, miembro, pregunta elegida, alternativa ni cuerpo HTTP; solo elección cuando pueda determinarse, categoría, hora, IP normalizada y referencia aleatoria.

### Invariantes antes de certificar implementadas

- Papeletas de la elección = participantes tradicionales registrados.
- Cada respuesta referencia una pregunta de la misma elección.
- Cada alternativa corresponde a su pregunta.
- Por pregunta: respuestas por papeleta dentro de `min_choices/max_choices`.
- Participación no supera habilitados.
- En secuencial, papeletas por pregunta = participaciones por pregunta.
- Sumas ponderadas no son negativas ni exceden la suma de pesos de papeletas aplicables.

La certificación debe fallar de forma explícita ante una anomalía.

### Rendimiento implementado

Reemplazar el N+1 de preguntas/opciones por dos consultas: preguntas por elección y opciones mediante `WHERE question_id IN (...)`, agrupadas en PHP. Mantener transacciones cortas y el orden de bloqueos actual.

## Archivos previstos

- `app/Services/VotingService.php`
- `app/Services/ResultService.php`
- `app/Services/ElectionService.php`
- `app/Core/Audit.php`
- `public/vote.php`
- `database/schema.sql`
- Migraciones nuevas, solo después de autorización
- Pruebas de regresión y concurrencia aisladas

## Riesgos pendientes

- La modalidad identificada requiere controles compuestos adicionales.
- La bitácora no es criptográficamente inmutable.
- No hay evidencia de comportamiento bajo concurrencia real o deadlocks.
- No existen SLO, métricas de latencia de voto ni alarmas de rollback/error.
- Los máximos funcionales todavía no están acordados.
- Dos archivos de regresión aparecen eliminados en el árbol de trabajo.

## Consideraciones operativas

Los cambios de índices/FK pueden bloquear tablas según volumen y versión de MySQL. Deben ensayarse sobre copia, medir duración, confirmar espacio libre y contar con respaldo verificado. No aplicar durante una votación abierta.
