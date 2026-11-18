# Cambios de base de datos propuestos — no ejecutar

La propuesta de participación e idempotencia fue autorizada y aplicada a la base local el 18-11-2026. Los refuerzos compuestos restantes continúan como propuesta y no fueron ejecutados.

Migraciones aplicadas localmente:

- `20261118_vote_integrity_hardening.sql`

Reversión preparada, no ejecutada:

- `20261118_vote_integrity_hardening_rollback.sql`

## Observación sobre el árbol actual

`ballots.census_member_id` y `20261118_identified_ballots.sql` son cambios locales anteriores a esta auditoría. La columna fue aplicada previamente en la base local durante otro requerimiento. Antes de producción debe tratarse como cambio pendiente, revisar su reversión y reforzar pertenencia entre elección y miembro.

## Propuesta 1: participación tradicional única y secreta

### Problema

La unicidad tradicional depende de `has_voted` y bloqueos de aplicación. Se requiere una barrera declarativa que no conecte identidad con contenido.

### SQL conceptual

```sql
CREATE TABLE election_participations (
  election_id BIGINT UNSIGNED NOT NULL,
  census_member_id BIGINT UNSIGNED NOT NULL,
  receipt_hash CHAR(64) NOT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (election_id, census_member_id),
  UNIQUE KEY uq_election_participation_receipt (receipt_hash),
  CONSTRAINT fk_ep_election FOREIGN KEY (election_id)
    REFERENCES elections(id) ON DELETE CASCADE,
  CONSTRAINT fk_ep_member FOREIGN KEY (census_member_id)
    REFERENCES census_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

No contiene `ballot_id`, pregunta, alternativa ni selección. La aplicación insertaría participación y papeleta dentro de la misma transacción.

### Datos afectados e impacto

Tabla nueva; no altera papeletas. Para datos históricos, solo puede poblarse desde `has_voted`, sin recibo original confiable; se recomienda no inventar recibos y definir una estrategia explícita. Creación normalmente online, pero el backfill —si se aprueba— requiere ventana controlada.

### Reversión

Desplegar primero código compatible. Para revertir antes de usar: `DROP TABLE election_participations`. Después de usar, exportar y conservar evidencia antes de retirarla. No ejecutar sin aprobación.

## Propuesta 2: pertenencia elección-miembro en voto identificado

### Problema

La FK actual permite teóricamente asociar una papeleta de la elección A con un miembro de B.

### SQL conceptual

```sql
ALTER TABLE census_members
  ADD UNIQUE KEY uq_census_id_election (id, election_id);

ALTER TABLE ballots
  DROP FOREIGN KEY fk_ballots_member,
  ADD CONSTRAINT fk_ballots_member_election
    FOREIGN KEY (census_member_id, election_id)
    REFERENCES census_members(id, election_id)
    ON DELETE SET NULL;
```

Debe verificarse previamente la compatibilidad de `ON DELETE SET NULL` compuesto: MySQL intentaría poner también `election_id` en NULL, lo cual no es admisible. La alternativa recomendada es `RESTRICT` y conservar el borrado por cascada desde la elección, o usar una participación identificada separada. El SQL definitivo depende de esa decisión y de pruebas en la versión exacta MySQL 8.4.

### Impacto/reversión

Escaneo y posible bloqueo de `census_members`/`ballots`. Antes de agregar la FK se debe consultar asociaciones cruzadas. Reversión: retirar FK compuesta, restaurar FK simple y retirar índice solo si no lo usa otro plan.

## Propuesta 3: coherencia pregunta-alternativa-papeleta

### Problema

Las FK independientes no expresan que una opción sea de la pregunta indicada ni que la pregunta sea de la elección de la papeleta.

### Alternativas

1. Agregar claves únicas compuestas auxiliares y `election_id` redundante validado en `ballot_answers`.
2. Eliminar `question_id` redundante de respuestas y derivarlo siempre desde `options` (cambio más invasivo; no recomendado en esta fase).
3. Mantener esquema y añadir validadores/invariantes antes de certificar (menor bloqueo, menor protección declarativa).

Se recomienda diseñar la opción 1 tras medir volumen. El SQL final no se presenta hasta confirmar cardinalidades y nombres reales de constraints en el ambiente objetivo.

## Propuesta 4: idempotencia de presentación

### Problema

Un timeout no permite recuperar el recibo de una solicitud ya confirmada.

### SQL conceptual

```sql
CREATE TABLE vote_submissions (
  idempotency_key CHAR(64) PRIMARY KEY,
  election_id BIGINT UNSIGNED NOT NULL,
  token_id BIGINT UNSIGNED NOT NULL,
  receipt_hash CHAR(64) NULL,
  status ENUM('processing','committed','failed') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vote_submission_token_key (token_id, idempotency_key),
  KEY idx_vote_submission_election (election_id),
  CONSTRAINT fk_vs_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
  CONSTRAINT fk_vs_token FOREIGN KEY (token_id) REFERENCES voter_tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

No guardar respuestas ni identidad adicional. Debe definirse retención y limpieza antes de aprobar.

### Impacto/reversión

Tabla nueva con crecimiento por intento; requiere política de retención e índice de limpieza. Reversión posible retirando el uso en código y archivando/eliminando la tabla durante mantenimiento.

## Propuesta 5: auditoría verificable

Agregar hash de evento y hash previo permitiría detectar alteraciones, pero no las impediría frente a un actor que pueda recalcular toda la cadena. Debe combinarse con permisos SQL mínimos y exportación/sellado externo periódico. Requiere diseño operacional antes de SQL.

## Precondiciones obligatorias para cualquier cambio

1. Autorización expresa.
2. Respaldo y restauración verificados.
3. Conteo de filas y tamaño de tablas.
4. Consulta de inconsistencias previa.
5. Ensayo en copia con MySQL 8.4.
6. Medición de bloqueo y espacio temporal.
7. Ventana sin votaciones abiertas.
8. Plan de rollback ensayado.
