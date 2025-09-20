## Tu Plan Seguro · Plataforma Web

Proyecto PHP para gestión de contactos y contenidos públicos, con panel de administración. Incluye medidas de idempotencia y protección anti-bots, rotación de logs, manejo de entorno por `APP_ENV`, y mejoras de UX/Accesibilidad.

## Tecnologías
- PHP 7.4+ (PDO, extensiones estándar)
- MySQL/MariaDB
- HTML/CSS/JS (sin frameworks pesados). Flatpickr/Bootstrap en admin donde aplica

## Estructura relevante
- `index.php`: sitio público (hero, frases dinámicas, ejemplos, formulario)
- `includes/`
  - `config.php`: configuración central (APP_ENV, DB, SMTP, logs, timezone)
  - `db.php`: conexión PDO reutilizable
  - `mail.php`: envío de emails (SMTP), depuración en dev
  - `save_contact.php`: alta de contactos con idempotencia, honeypot y rate limiting
- `admin/`
  - `_init.php`: bootstrap de admin (creación de tablas si faltan, auth básica)
  - `contacts.php`: listado/gestión, registrar contacto (popup) y acciones masivas
  - `view.php`: vista de detalle, edición de próxima fecha de contacto (NULL o definida)
  - `examples.php`: CRUD de “Algunos ejemplos” (imagen, título, subtítulo, coberturas)
  - `banner.php`: administración de frases del banner (activar/desactivar, orden)
- `assets/`
  - `css/style.css` y `css/admin.css`: estilos públicos/admin (modo oscuro opcional)
  - `js/main.js` y `js/admin.js`: interacciones públicas/admin
- `logs/`: `system.log` (rotación automática)

## Configuración de entorno (.env)
- Crea un archivo `.env` en la raíz (no se versiona) basado en:
```
APP_ENV=dev
TIMEZONE=America/Santiago

DB_HOST=127.0.0.1
DB_NAME=tuplanseguro
DB_USER=root
DB_PASS=secret

MAIL_DRIVER=smtp
MAIL_FROM=contacto@tuplanseguro.cl
MAIL_FROM_NAME=Tu Plan Seguro
SMTP_HOST=mail.tuplanseguro.cl
SMTP_PORT=465
SMTP_USER=contacto@tuplanseguro.cl
SMTP_PASS=secret
SMTP_SECURE=ssl
```
- `includes/config.php` carga `.env` si existe y usa esas variables priorizando sobre defaults.
- `APP_ENV` determina `dev|prod`. Zona horaria: `TIMEZONE` (por defecto America/Santiago).

Cómo definir `APP_ENV`:
- Windows (PowerShell): `setx APP_ENV dev` (reiniciar Apache/PHP si aplica)
- Hosting: variable de entorno en panel/cron o `passenv` del servidor

## Instalación local (resumen)
1) Clonar/copiar el proyecto en el vhost/raíz del servidor web
2) Crear base de datos `tuplanseguro` y usuario según credenciales locales
3) Configurar `APP_ENV=dev`
4) Navegar a `/admin/login.php` (el bootstrap crea tablas si faltan). Crear usuario admin si procede
5) Probar formulario público y envío de correo

## Emails
- Envío por SMTP. En `dev` hay depuración y `SMTPOptions` menos estrictas para evitar errores de certificado locales.
- Plantillas: se reemplazan placeholders (`{{name}}`, `{{full_name}}`, etc.) en asunto y cuerpo.

## Seguridad y prevención de duplicados
- Idempotencia en altas (fingerprint + `INSERT ... ON DUPLICATE KEY UPDATE` por día)
- Patrón PRG y deshabilitado del botón mientras se envía
- Honeypot invisible en el formulario público
- Rate limiting por IP (5 req/10 min)

## Logs y rotación
- `logs/system.log` agrupa eventos y errores. Rotación automática: 
  - Tamaño máx: 5 MB → renombre `system-YYYYmmdd_HHMMSS.log`
  - Retención: 30 días (se purgan logs antiguos)
- Verbosidad reducida: acciones rutinarias en una sola línea; errores con prefijo `[ERROR]`

## Contenidos dinámicos
- “Algunos ejemplos”: CRUD con subida de imagen (carpeta `uploads/`), cobertura en textarea (texto/emojis), drag&drop para orden
- Banner: múltiples frases, activación/desactivación y orden; slider liviano en frontend

## UX/Accesibilidad
- Modo oscuro opcional en público y admin (toggle, persistencia en localStorage, respeta `prefers-color-scheme`)
- Textareas auto-ajustables y con altura mínima en móvil
- Accesibilidad: `:focus-visible`, roles/labels, colores con suficiente contraste

## Optimización
- En producción se puede usar `style.min.css`, `admin.min.css` y `main.min.js` si existen. Los includes ya hacen fallback a los no-minificados
- Iconos de listas reemplazados por SVG liviano (sin dependencias pesadas)

## Roadmap / Pendientes
- Email diario de recordatorios: resumen por vencer (12h/2d/5d) a admin
- Plantillas de seguimiento (WhatsApp/Correo) con placeholders reutilizables
- Exportación de eventos a calendario (ICS/Google/Outlook) desde “Próximo contacto”
- Roles y auditoría: perfiles (admin/operador) y bitácora de acciones
- Tabla `isapres` administrable (reemplazar mapa hardcoded)
- Verificación SMTP desde “Configuración” con log seguro (solo dev)
- Webhooks/Zapier: eventos en altas/cambios de estado/próximos contactos
- Optimización de imágenes (resize + WebP) en `uploads/`

## Notas
- Scripts de ingreso masivo fueron eliminados según política del proyecto
- Si `APP_ENV=dev`, se muestran errores PHP; en `prod` se ocultan (registrados en logs)

## Licencia
Proyecto privado. Uso interno de Tu Plan Seguro.


