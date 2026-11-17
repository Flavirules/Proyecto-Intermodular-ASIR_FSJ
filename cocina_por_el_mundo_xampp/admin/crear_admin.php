<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$message = '';
$adminCount = (int)db()->query("SELECT COUNT(*) FROM usuarios WHERE rol='admin'")->fetchColumn();
if ($adminCount > 0) {
    http_response_code(403);
    $message = 'Ya existe una cuenta administradora. Elimina este archivo (admin/crear_admin.php) y entra desde admin/login.php.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (text_length($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $message = 'Introduce un nombre, un correo válido y una contraseña de al menos 10 caracteres.';
    } else {
        $stmt = db()->prepare("INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES (?,?,?,'admin') ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), password_hash=VALUES(password_hash), rol='admin'");
        try {
            $stmt->execute([$name,$email,password_hash($password, PASSWORD_DEFAULT)]);
            $message = 'Administrador creado. Elimina ahora admin/crear_admin.php y accede en admin/login.php.';
        } catch (PDOException $exception) {
            $message = 'No se pudo crear la cuenta. Comprueba la conexión con la base de datos.';
        }
    }
}
$pageTitle = 'Crear administrador';
require __DIR__ . '/../includes/header.php';
?>
<main class="page-main"><div class="page-intro"><p class="eyebrow">CONFIGURACIÓN INICIAL</p><h1>Crear administrador</h1><p>Este formulario solo funciona mientras no haya ningún administrador.</p></div>
<div class="site-form"><?php if ($message): ?><div class="notice-box"><?= e($message) ?></div><?php endif; ?><?php if ($adminCount === 0 && !str_contains($message, 'Administrador creado')): ?><form method="post" action="<?= e(BASE_URL) ?>/admin/crear_admin.php"><?= csrf_field() ?><label>Nombre<input name="name" required minlength="3"></label><label>Correo electrónico<input name="email" type="email" required></label><label>Contraseña (mínimo 10 caracteres)<input name="password" type="password" minlength="10" required></label><button class="primary-button" type="submit">Crear cuenta administradora</button></form><?php endif; ?><p><a href="<?= e(BASE_URL) ?>/admin/login.php">Ir al acceso de administración</a></p></div></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
