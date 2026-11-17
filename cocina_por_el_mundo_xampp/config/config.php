<?php
/** Configuración local para XAMPP. Cambia estos valores si tu instalación es distinta. */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'db_cocina');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Genera una clave propia con: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
// Pega el resultado aquí. Es necesaria para cifrar los CV almacenados en storage/.
define('FILE_ENCRYPTION_KEY', 'CAMBIA_ESTA_CLAVE_POR_64_CARACTERES_HEXADECIMALES');

// Correo: instala PHPMailer con `composer install` y completa los datos SMTP.
define('MAIL_ENABLED', false);
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', ''); // Usa una contraseña de aplicación, nunca tu contraseña normal.
define('MAIL_FROM', '');
define('MAIL_FROM_NAME', 'Cocina por el Mundo');
define('ADMIN_EMAIL', '');
define('MAIL_ENCRYPTION', 'tls'); // tls o ssl

define('APP_NAME', 'Cocina por el Mundo');
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
define('MAX_CV_SIZE', 8 * 1024 * 1024);
// Cupón de demostración del recomendador; porcentaje editable.
define('QUIZ_DISCOUNT_CODE', 'SABOR10');
define('QUIZ_DISCOUNT_PERCENT', 10);
