# Matriz de pruebas de integridad electoral

Estado global: preparada, no ejecutada en Etapa 1.  
Ambiente requerido: base MySQL aislada, esquema equivalente, datos sintéticos y sin procesos reales.

| ID | Escenario | Datos necesarios | Resultado esperado | Resultado obtenido | Evidencia | Estado |
|---|---|---|---|---|---|---|
| P-01 | Un votante emite voto tradicional | Elección abierta, OTP válido | Una papeleta, respuestas válidas, participación y token consumido | No ejecutado | Pendiente | Pendiente |
| P-02 | Segundo voto mismo token | Resultado de P-01 | Cero papeletas nuevas; respuesta “previamente procesada” o rechazo inequívoco | No ejecutado | Pendiente | Pendiente |
| P-03 | Dos solicitudes simultáneas | Mismo token, barrera concurrente | Exactamente una confirma; una papeleta total | No ejecutado | Logs SQL/conteos | Requiere autorización |
| P-04 | Dos pestañas/dispositivos | Mismo token verificado | Un único sufragio | No ejecutado | Conteo BD | Requiere autorización |
| P-05 | Reenvío HTTP tras timeout | Misma clave de idempotencia | Mismo recibo, sin duplicación | No ejecutado | Respuestas y BD | Pendiente implementación |
| P-06 | Miembro bloqueado | `status=blocked` | Rechazo sin escrituras parciales | No ejecutado | BD/auditoría | Pendiente |
| P-07 | Token de otro proceso/manipulado | Token A, IDs de B en formulario | Rechazo de alternativas ajenas | No ejecutado | Respuesta/BD | Pendiente |
| P-08 | Antes de apertura | Elección draft/scheduled | Rechazo | No ejecutado | Respuesta/auditoría | Pendiente |
| P-09 | Después del cierre/cancelación | Elección terminal | Rechazo | No ejecutado | Respuesta/auditoría | Pendiente |
| P-10 | Cierre simultáneo con voto | Dos conexiones y barrera | Orden serializable: voto completo antes del cierre o rechazo completo después | No ejecutado | Trazas/BD | Requiere autorización |
| P-11 | Fallo tras insertar papeleta | Inyección de fallo controlada | Rollback de papeleta, respuestas, participación y token | No ejecutado | Conteos transaccionales | Requiere autorización |
| P-12 | Pregunta/alternativa manipulada | Alternativa de otra pregunta | Rechazo | No ejecutado | Respuesta/BD | Pendiente |
| P-13 | Selección fuera de min/max | 0 o demasiadas opciones | Rechazo | No ejecutado | Respuesta/BD | Pendiente |
| P-14 | Pregunta secuencial repetida | Mismo miembro/pregunta | PK impide segunda participación y no queda papeleta huérfana | No ejecutado | BD | Pendiente |
| P-15 | Cierre de pregunta concurrente | Respuesta y cierre simultáneos | Respuesta íntegra antes del cierre o rechazo íntegro | No ejecutado | BD/logs | Requiere autorización |
| P-16 | Modificar papeleta/padrón activo | Elección abierta | Rechazo backend | No ejecutado | Respuesta/BD | Pendiente |
| P-17 | Publicar resultados abiertos | Elección abierta | Rechazo backend | No ejecutado | Respuesta/estado | Pendiente |
| P-18 | Invariantes de resultados simples | Papeletas sintéticas válidas | Totales reproducibles y participación correcta | No ejecutado | SQL/JSON | Pendiente |
| P-19 | Voto múltiple | Pregunta multiple | Conteo por opción correcto; papeletas no confundidas con respuestas | No ejecutado | SQL/JSON | Pendiente |
| P-20 | Peso máximo permitido | Límite aprobado | Sin overflow, suma exacta DECIMAL | No ejecutado | SQL/PHP | Bloqueado por definición |
| P-21 | Volumen máximo de padrón | Límite aprobado | Sin overflow; latencia dentro del SLO | No ejecutado | Métricas/EXPLAIN | Requiere autorización |
| P-22 | Consulta IDOR | Usuario de otra organización | 403/404, sin datos | No ejecutado | HTTP | Pendiente |
| P-23 | CSRF ausente/incorrecto | POST sensible | Rechazo sin cambio | No ejecutado | HTTP/BD | Pendiente |
| P-24 | Voto secreto | Elección secret | Ninguna relación identidad-preferencia recuperable | No ejecutado | Inspección BD | Pendiente |
| P-25 | Voto identificado | Elección public con aviso | Relación solo para autorizados, después del cierre, acceso auditado | No ejecutado | HTTP/BD/auditoría | Pendiente |
| P-26 | Certificación con datos anómalos | Fixture inconsistente aislado | Certificación rechazada | No ejecutado | Error/invariantes | Pendiente implementación |
| P-27 | Deadlock/reintento DB | Bloqueos controlados | Error explícito o reintento acotado, sin parcialidad | No ejecutado | Logs SQL | Requiere autorización |
| P-28 | Caída de conexión tras commit | Proxy/fallo controlado | Reintento devuelve estado previo sin duplicar | No ejecutado | Recibo/BD | Pendiente implementación |
| P-29 | Recuperación desde respaldo | Copia aislada | Conteos/certificados restaurados y verificables | No ejecutado | Hashes/reporte restore | Requiere autorización |

## Secuencia recomendada

1. Pruebas unitarias/funcionales sin concurrencia.
2. Pruebas de rollback con fixture efímero.
3. Con autorización: concurrencia de dos conexiones.
4. Con autorización y límites acordados: carga gradual.
5. Restauración de respaldo en ambiente aislado.

No usar datos, credenciales ni elecciones reales.
