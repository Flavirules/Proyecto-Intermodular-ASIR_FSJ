<?php
$pageTitle = $pageTitle ?? 'Cocina por el Mundo';
$currentPage = $_GET['p'] ?? 'inicio';
$cartCount = array_sum(array_map('intval', $_SESSION['cart'] ?? []));
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cursos de cocina del mundo impartidos por chefs expertos.">
    <title><?= e($pageTitle) ?> | Cocina por el Mundo</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/styles.css">
    <link rel="icon" href="<?= e(BASE_URL) ?>/assets/images/logo.svg" type="image/svg+xml">
</head>
<body>
<div class="app-shell">
<header class="site-header">
    <a class="brand" href="<?= e(BASE_URL) ?>/index.php" aria-label="Cocina por el Mundo - Inicio">
        <img class="brand-mark" src="<?= e(BASE_URL) ?>/assets/images/logo.jpg" alt=""><span><strong>COCINA</strong><em>POR EL MUNDO</em></span>
    </a>
    <button class="mobile-menu" type="button" aria-label="Abrir menú" aria-expanded="false">☰</button>
    <nav class="main-nav" aria-label="Navegación principal">
        <a class="<?= $currentPage === 'inicio' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/index.php">Inicio</a>
        <a class="<?= in_array($currentPage, ['cursos','curso'], true) ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/index.php?p=cursos">Cursos</a>
        <a class="<?= $currentPage === 'chefs' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/index.php?p=chefs">Nuestros chefs</a>
        <a class="<?= $currentPage === 'candidatura' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/index.php?p=candidatura">Únete como chef</a>
        <a class="<?= $currentPage === 'recomendador' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/index.php?p=recomendador">Encuentra tu curso</a>
    </nav>
    <a class="cart-button" href="<?= e(BASE_URL) ?>/index.php?p=cesta"><span aria-hidden="true">🛒</span><span>Cesta</span><?php if ($cartCount > 0): ?><b><?= $cartCount ?></b><?php endif; ?></a>
</header>
<?php if ($flash): ?>
<div class="toast <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>
