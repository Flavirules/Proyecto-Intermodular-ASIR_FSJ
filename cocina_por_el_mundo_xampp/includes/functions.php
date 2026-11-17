<?php
require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function text_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function text_position(string $haystack, string $needle): int|false
{
    return function_exists('mb_stripos') ? mb_stripos($haystack, $needle, 0, 'UTF-8') : stripos($haystack, $needle);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string)($_POST['csrf_token'] ?? '');
    $known = (string)($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('La sesión del formulario ha caducado. Vuelve atrás y prueba de nuevo.');
    }
}

function is_admin(): bool
{
    return isset($_SESSION['admin_id'], $_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

function require_admin(): void
{
    if (!is_admin()) {
        set_flash('Debes iniciar sesión como administrador.', 'error');
        redirect('login.php');
    }
}

function slugify(string $text): string
{
    $text = text_lower(trim($text));
    $text = str_replace(['á','é','í','ó','ú','ñ','ü'], ['a','e','i','o','u','n','u'], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'curso';
}

function store_public_image(array $file, string $folder): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('No se ha podido recibir la imagen.');
    if (($file['size'] ?? 0) > MAX_IMAGE_SIZE) throw new RuntimeException('La imagen no puede superar los 5 MB.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Formato no permitido. Usa JPG, PNG o WEBP.');

    $base = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) throw new RuntimeException('No se pudo preparar la carpeta de imágenes.');
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $base . '/' . $filename)) throw new RuntimeException('No se pudo guardar la imagen.');
    return 'uploads/' . $folder . '/' . $filename;
}

function delete_public_image(?string $relativePath): void
{
    if (!$relativePath || !str_starts_with($relativePath, 'uploads/')) return;
    $full = realpath(dirname(__DIR__) . '/' . $relativePath);
    $allowed = realpath(dirname(__DIR__) . '/uploads');
    if ($full && $allowed && str_starts_with($full, $allowed . DIRECTORY_SEPARATOR) && is_file($full)) @unlink($full);
}

function valid_encryption_key(): string
{
    $key = FILE_ENCRYPTION_KEY;
    if (!preg_match('/^[a-f0-9]{64}$/i', $key) || str_starts_with($key, 'CAMBIA_')) {
        throw new RuntimeException('Configura FILE_ENCRYPTION_KEY en config/config.php antes de recibir candidaturas.');
    }
    return hex2bin($key);
}

function encrypt_uploaded_file(array $file, string $subfolder, array $allowedMimes, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Debes adjuntar el archivo solicitado.');
    if (($file['size'] ?? 0) > $maxBytes) throw new RuntimeException('El archivo supera el tamaño máximo permitido.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) throw new RuntimeException('El formato del archivo no está permitido.');

    $key = valid_encryption_key();
    $plain = file_get_contents($file['tmp_name']);
    if ($plain === false) throw new RuntimeException('No se pudo leer el archivo.');
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('No se pudo cifrar el archivo.');

    $dir = dirname(__DIR__) . '/storage/candidaturas/' . $subfolder;
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
    $filename = bin2hex(random_bytes(20)) . '.enc';
    if (file_put_contents($dir . '/' . $filename, $iv . $tag . $cipher, LOCK_EX) === false) throw new RuntimeException('No se pudo guardar el archivo cifrado.');
    return ['path' => 'storage/candidaturas/' . $subfolder . '/' . $filename, 'mime' => $mime, 'original' => basename((string)$file['name'])];
}

function decrypt_private_file(string $relativePath): string
{
    if (!str_starts_with($relativePath, 'storage/candidaturas/')) throw new RuntimeException('Ruta de archivo no válida.');
    $full = dirname(__DIR__) . '/' . $relativePath;
    $data = @file_get_contents($full);
    if ($data === false || strlen($data) < 29) throw new RuntimeException('No se ha encontrado el archivo.');
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $cipher = substr($data, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', valid_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) throw new RuntimeException('No se pudo descifrar el archivo. Comprueba la clave de configuración.');
    return $plain;
}

function remove_private_file(?string $relativePath): void
{
    if (!$relativePath || !str_starts_with($relativePath, 'storage/candidaturas/')) return;
    $full = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($full)) @unlink($full);
}

function promote_candidate_image(?string $relativePath): ?string
{
    if (!$relativePath) return null;
    $plain = decrypt_private_file($relativePath);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($plain);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) return null;
    $dir = dirname(__DIR__) . '/uploads/chefs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar la carpeta de imágenes.');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (file_put_contents($dir . '/' . $name, $plain, LOCK_EX) === false) throw new RuntimeException('No se pudo publicar la imagen del portfolio.');
    return 'uploads/chefs/' . $name;
}

function send_project_email(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    $sent = false;
    if (MAIL_ENABLED && is_file($autoload)) {
        require_once $autoload;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->Port = MAIL_PORT;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            $sent = $mail->send();
        } catch (Throwable $exception) {
            error_log('Correo no enviado: ' . $exception->getMessage());
        }
    } elseif (MAIL_ENABLED && MAIL_FROM !== '') {
        $headers = "MIME-Version: 1.0\r\nContent-type: text/plain; charset=UTF-8\r\nFrom: " . MAIL_FROM . "\r\n";
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $textBody, $headers);
    }

    if (!$sent) {
        $logDir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0700, true);
        $entry = "\n--- " . date('c') . " ---\nPara: {$to}\nAsunto: {$subject}\n{$textBody}\n";
        @file_put_contents($logDir . '/correos_pendientes.log', $entry, FILE_APPEND | LOCK_EX);
    }
    return $sent;
}

function get_courses(bool $activeOnly = true): array
{
    $sql = 'SELECT c.*, ch.nombre AS chef_nombre FROM cursos c LEFT JOIN cocineros ch ON ch.id_cocinero = c.id_cocinero';
    if ($activeOnly) $sql .= ' WHERE c.activo = 1';
    $sql .= ' ORDER BY c.destacado DESC, c.nombre_curso ASC';
    return db()->query($sql)->fetchAll();
}

function get_chefs(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM cocineros';
    if ($activeOnly) $sql .= ' WHERE activo = 1';
    $sql .= ' ORDER BY nombre ASC';
    return db()->query($sql)->fetchAll();
}

function course_image(?string $path): string
{
    if ($path && is_file(dirname(__DIR__) . '/' . $path)) return $path;
    return '';
}


function render_course_card(array $item): string
{
    $base = e(BASE_URL);
    $title = e($item['nombre_curso']);
    $slug = e($item['slug']);
    $cuisine = e($item['cocina']);
    $description = e($item['descripcion']);
    $image = $item['imagen'] && is_file(dirname(__DIR__) . '/' . $item['imagen']) ? '<img src="' . $base . '/' . e($item['imagen']) . '" alt="' . $title . '">' : '<span class="image-placeholder">♨</span>';
    $price = number_format((float)$item['precio'], 2, ',', '.');
    $rating = number_format((float)$item['nota'], 1);
    return '<article class="course-card"><a class="course-image" href="' . $base . '/index.php?p=curso&slug=' . $slug . '">' . $image . '<span class="course-badge">' . $cuisine . '</span></a><div class="course-info"><div class="rating">★ ' . $rating . ' <span>(nota media)</span></div><h3>' . $title . '</h3><p>' . $description . '</p><div class="course-meta"><span>◷ ' . e($item['duracion']) . '</span><strong>' . $price . ' €</strong></div><div class="card-actions"><a class="outline-button" href="' . $base . '/index.php?p=curso&slug=' . $slug . '">Ver curso</a><form method="post" action="' . $base . '/index.php?p=cursos">' . csrf_field() . '<input type="hidden" name="action" value="add_cart"><input type="hidden" name="course_id" value="' . (int)$item['id_curso'] . '"><input type="hidden" name="return_page" value="cursos"><button class="add-button" type="submit" aria-label="Añadir ' . $title . ' a la cesta">+</button></form></div></div></article>';
}
