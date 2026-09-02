# Plan de pruebas — INAUDIS Vote MVP

## Objetivo
Validar que el flujo de demostración funciona de punta a punta y que los controles mínimos de seguridad y consistencia se comportan como fueron diseñados.

## Casos críticos

### Autenticación
1. Login válido redirige al dashboard.
2. Password inválida no crea sesión.
3. Usuario inactivo no puede entrar.
4. Formulario sin CSRF válido es rechazado.
5. Tras ocho intentos fallidos en la ventana configurada, el login se bloquea temporalmente.

### Roles
1. Organizador no puede acceder a `/organizations.php` ni `/users.php`.
2. Organizador solo ve elecciones de su organización.
3. Organizador no puede administrar una elección ajena cambiando el ID por URL.

### Censo
1. Alta manual válida persiste.
2. Email duplicado dentro de la misma elección falla.
3. CSV válido importa filas.
4. CSV > 2 MB es rechazado.
5. Extensión distinta de `.csv` es rechazada.

### OTP
1. OTP correcto dentro de 10 minutos verifica la invitación.
2. OTP incorrecto no habilita la papeleta.
3. OTP vencido no habilita la papeleta.
4. Token inexistente no permite votar.

### Votación
1. Solo una elección `open` y dentro de fechas permite votar.
2. Selección contiene únicamente opciones de la pregunta correspondiente.
3. Pregunta múltiple respeta mínimo/máximo.
4. Una emisión válida crea una papeleta.
5. La papeleta no contiene `census_member_id`.
6. El censo queda `has_voted=1`.
7. El token queda `consumed_at` informado.
8. Reutilizar el mismo enlace no permite segundo voto.
9. Dos solicitudes concurrentes con la misma invitación deben producir como máximo una papeleta confirmada.

### Resultados
1. Conteo simple coincide con papeletas.
2. Conteo ponderado suma correctamente `ballots.weight`.
3. Participación coincide con censo/votantes.
4. Certificado SHA-256 es de 64 caracteres hexadecimales.
5. Regenerar sin cambios produce el mismo hash de resultados; cambios legítimos en resultados cambian el hash.

### Acta PDF
1. Endpoint entrega `Content-Type: application/pdf`.
2. Archivo abre correctamente.
3. Contiene proceso, organización, participación, resultados y SHA-256.

### Auditoría
1. Login, creación de organización, elección, pregunta, censo, invitación, OTP, voto, certificado y PDF generan eventos esperados.
2. La auditoría no registra el contenido de la papeleta asociado a la identidad.

## Evidencia de cierre
Para cada caso se debe registrar: fecha, ambiente, resultado PASS/FAIL, observación y evidencia (captura/log). Un FAIL bloqueante debe resolverse y repetirse antes de declarar el MVP validado.
