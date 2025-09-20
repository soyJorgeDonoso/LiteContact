LiteContact · Mini CRM Web

LiteContact es una aplicación web ligera para la gestión de contactos registrados desde un sitio público, con panel de administración integrado. Pensado como un mini CRM autogestionable, ofrece funcionalidades como alarmas de seguimiento, administración de contenidos, protección anti-bots, y optimización para distintos entornos (dev/prod).

🧰 Tecnologías utilizadas

PHP 7.4+ (conexión PDO, librerías estándar)

MySQL o MariaDB

HTML/CSS/JS (vanilla, sin frameworks pesados)

Uso parcial de Flatpickr y Bootstrap en el panel admin

🗂️ Estructura del proyecto
├── index.php                  # Página pública con formulario de registro
├── includes/
│   ├── config.php             # Carga de entorno, DB, correo, logs
│   ├── db.php                 # Conexión PDO reutilizable
│   ├── mail.php               # Envío SMTP
│   └── save_contact.php       # Registro de contactos con protección antispam
├── admin/
│   ├── _init.php              # Bootstrap de administración
│   ├── contacts.php           # Gestión de contactos
│   ├── view.php               # Vista individual y programación de contacto
│   ├── examples.php           # Gestión de secciones dinámicas públicas
│   └── banner.php             # Gestión de frases/banner dinámico
├── assets/
│   ├── css/                   # Estilos personalizados
│   └── js/                    # Scripts públicos y admin
└── logs/
    └── system.log             # Registro de eventos y errores

⚙️ Configuración por entorno (.env)

Crea un archivo .env en la raíz del proyecto:

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


APP_ENV define si el entorno es dev o prod, y condiciona errores/logs.

TIMEZONE define zona horaria (por defecto: Santiago).

Variables se cargan desde includes/config.php.

🚀 Instalación rápida (modo local)

Clona o copia el proyecto en tu entorno local (Apache/Nginx).

Crea la base de datos litecontact y configura el .env.

Accede a /admin/login.php para iniciar setup (creación automática de tablas).

Probar el formulario público y verificar el correo de prueba.

Ajusta los contenidos desde el panel de administración.

📩 Emails y plantillas

El envío de correos se realiza vía SMTP.

En entorno dev, los errores se muestran en consola o logs.

Se utilizan placeholders como {{name}}, {{email}} para los textos de los correos.

🔐 Seguridad y protección

Idempotencia para evitar duplicados: detección por fingerprint diario.

Honeypot invisible para bloquear bots.

Rate Limiting: 5 intentos cada 10 minutos por IP.

PRG pattern: evita doble envío con botón deshabilitado al enviar.

📊 Logs automáticos

Se guarda todo en logs/system.log con rotación automática:

Máximo 5MB por archivo.

Archivos antiguos renombrados por fecha y purgados tras 30 días.

✨ Funcionalidades destacadas

Gestión de “Próximo contacto” con fechas programables por registro.

CRUD para ejemplos e imágenes del sitio público.

Modo oscuro disponible (respeta prefers-color-scheme y se guarda en localStorage).

Soporte para emojis en contenidos y etiquetas accesibles.

Diseño responsive con mejoras de usabilidad móvil.

🧭 Roadmap (en desarrollo)

Recordatorios diarios por email a administradores.

Plantillas de seguimiento por WhatsApp/correo con variables dinámicas.

Exportación de eventos a calendarios (Google/ICS).

Perfiles y roles diferenciados (admin/operador).

Bitácora de acciones (auditoría).

Envío de eventos por webhooks (Zapier, etc).

Optimización automática de imágenes (resize/WebP).

📌 Notas

Proyecto en desarrollo continuo.

En dev se muestran los errores PHP, en prod se registran en log.

El sistema está pensado para implementaciones simples y autogestionables.

📄 Licencia

Uso interno y privado de LiteContact. No redistribuir sin autorización.