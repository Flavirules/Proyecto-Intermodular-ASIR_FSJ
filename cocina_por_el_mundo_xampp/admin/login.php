<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_admin()) redirect(BASE_URL . '/admin/index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0);
    if ($_SESSION['login_attempts'] >= 8) {
        $error = 'Demasiados intentos. Cierra el navegador y vuelve a intentarlo.';
    } else {
        $stmt = db()->prepare("SELECT id_user,nombre,email,password_hash,rol FROM usuarios WHERE email=? AND rol='admin' LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if ($admin && $admin['password_hash'] && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id_user'];
            $_SESSION['admin_name'] = $admin['nombre'];
            $_SESSION['admin_role'] = $admin['rol'];
            unset($_SESSION['login_attempts']);
            set_flash('Bienvenido al panel de administración.');
            redirect(BASE_URL . '/admin/index.php');
        }
        $_SESSION['login_attempts']++;
        $error = 'Correo o contraseña incorrectos.';
    }
}
$pageTitle = 'Acceso de administración';
require __DIR__ . '/../includes/header.php';
?>
<main class="page-main"><div class="page-intro"><p class="eyebrow">ACCESO PRIVADO</p><h1>Administración</h1><p>Esta zona está reservada al personal administrador.</p></div>
<form class="site-form" method="post" action="<?= e(BASE_URL) ?>/admin/login.php"><?= csrf_field() ?><h2>Iniciar sesión</h2><?php if ($error): ?><div class="notice-box"><?= e($error) ?></div><?php endif; ?><label>Correo electrónico<input type="email" name="email" required autocomplete="username"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button class="primary-button" type="submit">Entrar al panel →</button><p class="form-help">No existe un enlace al panel en la navegación pública. Acceso directo: /admin/login.php</p></form></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
