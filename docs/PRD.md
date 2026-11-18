# PRD — DEMOCRA Demo v1

## 1. Propósito
Construir un demo funcional de votación electrónica orientado a organizaciones, sindicatos, asociaciones, empresas y colectivos que necesiten configurar y ejecutar procesos de votación digital.

## 2. Objetivo del Demo v1
Demostrar de punta a punta un proceso real: crear organización, cargar censo, configurar elección, invitar votantes, validar acceso, emitir voto, evitar doble votación, visualizar participación, cerrar proceso, mostrar resultados y generar acta con hash.

## 3. Roles
### Administrador
- Gestiona organizaciones, censos, votaciones y resultados.
- Configura fechas, preguntas, alternativas y reglas básicas.
- Visualiza participación y auditoría.

### Organizador
- Gestiona procesos de una organización asignada.

### Votante
- Accede mediante invitación/OTP.
- Visualiza papeleta.
- Emite un único voto válido por proceso.

## 4. Módulos
1. Autenticación y roles.
2. Organizaciones.
3. Censo electoral.
4. Votaciones.
5. Preguntas y alternativas.
6. Invitaciones y OTP por email.
7. Portal del votante.
8. Dashboard de participación.
9. Resultados.
10. Acta PDF, auditoría y SHA-256.

## 5. Requisitos funcionales principales
- CRUD de organizaciones.
- Importación de censo desde CSV/Excel.
- Configuración de inicio y término de votación.
- Preguntas de selección única y múltiple.
- Voto secreto como modo principal.
- Soporte inicial para voto ponderado.
- Prevención de voto duplicado.
- Registro de eventos administrativos.
- Cierre de votación.
- Resultados con totales y porcentajes.
- Generación de acta PDF.
- Generación de hash SHA-256 del acta/resultados.

## 6. Stack obligatorio
- HTML5
- JavaScript Vanilla
- Tailwind CSS
- PHP 8.3
- Apache
- MySQL

## 7. Fuera de alcance del Demo v1
- Kubernetes.
- Alta disponibilidad multi-zona.
- Certificaciones formales.
- Pentesting certificado.
- Integración blockchain productiva.
- ClaveÚnica.
- SMS productivo.
- Firma electrónica avanzada.
- Autorización formal ante organismos públicos.

## 8. Criterio de éxito
El demo se considera exitoso si un administrador puede crear una elección completa y un votante puede participar de punta a punta, reflejándose correctamente la participación, el resultado, el acta y la auditoría.
