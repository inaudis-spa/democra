# Changelog — INAUDIS Vote

Se documentan cambios relevantes del proyecto desde el inicio del repositorio.

## 2026-09-02

### Documentación inicial
- Definición del alcance del Demo v1.
- Definición del stack técnico obligatorio de INAUDIS.
- Documentación de arquitectura, seguridad y roadmap.
- Configuración de `.gitignore` y `.env.example`.

### Base técnica
- Implementado bootstrap PHP y carga de variables de entorno.
- Implementada conexión MySQL mediante PDO.
- Implementado servicio base de autenticación.
- Implementado inicio y cierre de sesión.
- Implementada regeneración de sesión tras autenticación.
- Implementado registro de login exitoso en auditoría.

### Base de datos
- Creada estructura inicial para:
  - organizaciones;
  - usuarios y roles;
  - elecciones;
  - censo electoral;
  - preguntas y alternativas;
  - tokens de votante/OTP;
  - papeletas anónimas;
  - respuestas;
  - auditoría;
  - certificados/hash de resultados.

### Interfaz
- Creada pantalla de login responsive.
- Creado dashboard autenticado inicial.

### Estado
Fase 1 en desarrollo. La autenticación base y el modelo de datos están iniciados; aún no se considera un demo ejecutable de punta a punta hasta completar configuración local, administrador inicial, organizaciones y flujo de votación.
