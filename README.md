# LiteContact · Mini CRM Web

**LiteContact** es una aplicación web ligera para la gestión de contactos registrados desde un sitio público, con panel de administración. Pensado como un mini CRM autogestionable, ofrece funcionalidades como alarmas de seguimiento, administración de contenidos, protección anti-bots y configuración adaptable por entorno (`dev` o `prod`).

---

## Tecnologías utilizadas

- **PHP 7.4+** (PDO, librerías estándar)
- **MySQL/MariaDB**
- **HTML/CSS/JS** (sin frameworks pesados)
- **Flatpickr y Bootstrap** (aplicados en panel admin)

---

## Estructura del proyecto

```
.
├── index.php               # Página pública con formulario de registro
├── includes/
│   ├── config.php          # Carga de entorno, DB, correo, logs
│   ├── db.php              # Conexión PDO reutilizable
│   ├── mail.php            # Envío de correos SMTP
│   └── save_contact.php    # Registro con validaciones e idempotencia
├── admin/
│   ├── _init.php           # Bootstrap de administración (auth + DB setup)
│   ├── contacts.php        # Gestión de contactos
│   ├── view.php            # Detalle de contacto + próxima fecha
│   ├── examples.php        # CRUD de ejemplos públicos
│   └── banner.php          # Gestión de frases dinámicas
├── assets/
│   ├── css/                # Estilos personalizados
│   └── js/                 # Scripts para público y admin
├── logs/
│   └── system.log          # Registro de eventos y errores
```

---

## Configuración del entorno (`.env`)

Crea un archivo `.env` en la raíz del proyecto:

```env
APP_ENV=dev
TIMEZONE=America/Santiago

DB_HOST=127.0.0.1
DB_NAME=litecontact
DB_USER=root
DB_PASS=secret

MAIL_DRIVER=smtp
MAIL_FROM=contacto@litecontact.cl
MAIL_FROM_NAME=LiteContact
SMTP_HOST=mail.litecontact.cl
SMTP_PORT=465
SMTP_USER=contacto@litecontact.cl
SMTP_PASS=secret
SMTP_SECURE=ssl
```

- `APP_ENV` define el entorno (`dev` o `prod`).
- Las variables son leídas automáticamente desde `includes/config.php`.

---

## Instalación local

1. Clona el repositorio o descarga el código.
2. Crea la base de datos `litecontact` en tu entorno local.
3. Configura el archivo `.env` con tus credenciales.
4. Accede a `/admin/login.php`. El sistema generará automáticamente las tablas necesarias.
5. Prueba el formulario público y verifica el envío de correos.

---

## Funcionalidades clave

- **Formulario público con validaciones** y protección anti-bots
- **Gestión de contactos** con historial y programación de próximos seguimientos
- **Alarmas configurables** para seguimiento pendiente
- **Modo oscuro opcional** con persistencia en navegador
- **CRUD dinámico** para frases del banner e “ejemplos” públicos
- **Envío de correos** mediante plantillas con placeholders (`{{name}}`, `{{email}}`, etc.)
- **Logs rotativos** y registro de eventos
- **Prevención de duplicados** por fingerprint diario

---

## Seguridad

- Honeypot invisible contra bots
- Rate limiting: 5 registros por IP cada 10 minutos
- Patrón PRG para evitar doble envío
- Errores visibles en `dev`, ocultos y logueados en `prod`

---

## Próximas mejoras (roadmap)

- Resumen diario de contactos pendientes por email
- Plantillas de mensajes reutilizables (WhatsApp/Email)
- Exportar eventos a Google Calendar/ICS/Outlook
- Gestión de roles (admin, operador) y auditoría de acciones
- Webhooks: integraciones con Zapier o servicios externos
- Optimización automática de imágenes subidas (resize + WebP)
- Configuración avanzada desde panel admin

---

## Licencia

Uso privado y exclusivo para desarrolladores autorizados. No redistribuir sin permiso.