# Arquitectura — DEMOCRA

## Stack base
- Frontend: HTML5 + Tailwind CSS + JavaScript Vanilla.
- Backend: PHP 8.3.
- Servidor: Apache sobre Linux.
- Base de datos: MySQL.

## Principios
- Arquitectura modular.
- Separación clara entre interfaz, lógica de negocio y persistencia.
- Sin dependencia obligatoria de frameworks frontend.
- Configuración sensible mediante variables de entorno.
- Preparada para evolucionar a infraestructura cloud cuando el producto pase a producción.

## Estructura prevista
```text
/app
  /admin
  /voter
  /shared
/api
/database
  /migrations
  /seeds
/public
/storage
/tests
/docs
```

## Flujo administrador
Login → Dashboard → Organización → Censo → Crear votación → Preguntas → Publicar → Seguimiento → Cierre → Resultados → Acta.

## Flujo votante
Invitación → Validación → OTP → Papeleta → Emisión del voto → Confirmación.

## Separación identidad / voto
La identidad del votante y el contenido de su voto deben tratarse como dominios separados. El sistema debe poder verificar que una persona está habilitada y que ya votó sin almacenar una relación directa y legible entre identidad y selección emitida cuando el proceso sea secreto.

## Evolución
La arquitectura del demo evita sobreingeniería. Para una futura versión productiva se evaluarán balanceadores, almacenamiento de objetos, servicios administrados de base de datos, redundancia, observabilidad y componentes desacoplados según carga real y requisitos regulatorios.
