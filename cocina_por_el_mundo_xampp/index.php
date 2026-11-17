<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = (string)($_GET['p'] ?? 'inicio');
$search = trim((string)($_GET['q'] ?? ''));
$validPages = ['inicio','cursos','chefs','curso','cesta','candidatura','recomendador','confirmacion'];
if (!in_array($page, $validPages, true)) $page = 'inicio';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'add_cart') {
            $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
            if (!$courseId) throw new RuntimeException('Curso no válido.');
            $stmt = db()->prepare('SELECT id_curso FROM cursos WHERE id_curso = ? AND activo = 1');
            $stmt->execute([$courseId]);
            if (!$stmt->fetch()) throw new RuntimeException('El curso ya no está disponible.');
            $_SESSION['cart'] ??= [];
            $_SESSION['cart'][$courseId] = min(10, (int)($_SESSION['cart'][$courseId] ?? 0) + 1);
            set_flash('Curso añadido a tu cesta.');
            redirect(BASE_URL . '/index.php?p=' . rawurlencode((string)($_POST['return_page'] ?? 'cursos')) . (isset($_POST['slug']) ? '&slug=' . rawurlencode((string)$_POST['slug']) : ''));
        }

        if ($action === 'update_cart') {
            $quantities = $_POST['quantity'] ?? [];
            foreach (($_SESSION['cart'] ?? []) as $id => $oldQty) {
                $qty = max(0, min(10, (int)($quantities[$id] ?? 0)));
                if ($qty === 0) unset($_SESSION['cart'][$id]);
                else $_SESSION['cart'][$id] = $qty;
            }
            set_flash('Cesta actualizada.');
            redirect(BASE_URL . '/index.php?p=cesta');
        }

        if ($action === 'remove_cart') {
            $id = (int)($_POST['course_id'] ?? 0);
            unset($_SESSION['cart'][$id]);
            set_flash('Curso eliminado de la cesta.');
            redirect(BASE_URL . '/index.php?p=cesta');
        }

        if ($action === 'checkout') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            if (text_length($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($phone) < 6 || (string)($_POST['consent'] ?? '') !== '1') {
                throw new RuntimeException('Comprueba el nombre, el correo electrónico y el teléfono.');
            }
            $cart = $_SESSION['cart'] ?? [];
            if (!$cart) throw new RuntimeException('Tu cesta está vacía.');

            $pdo = db();
            $pdo->beginTransaction();
            $items = [];
            $total = 0.0;
            foreach ($cart as $id => $quantity) {
                $stmt = $pdo->prepare('SELECT id_curso, nombre_curso, slug, precio, id_cocinero FROM cursos WHERE id_curso = ? AND activo = 1 FOR UPDATE');
                $stmt->execute([(int)$id]);
                $course = $stmt->fetch();
                if (!$course) throw new RuntimeException('Uno de los cursos de tu cesta ya no está disponible.');
                $course['cantidad'] = max(1, min(10, (int)$quantity));
                $course['subtotal'] = (float)$course['precio'] * $course['cantidad'];
                $total += $course['subtotal'];
                $items[] = $course;
            }

            $userStmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, rol) VALUES (?, ?, 'alumno') ON DUPLICATE KEY UPDATE id_user = LAST_INSERT_ID(id_user), nombre = VALUES(nombre)");
            $userStmt->execute([$name, $email]);
            $userId = (int)$pdo->lastInsertId();

            $discount = !empty($_SESSION['discount_available']) ? round($total * (QUIZ_DISCOUNT_PERCENT / 100), 2) : 0.00;
            $finalTotal = max(0, $total - $discount);
            $discountCode = $discount > 0 ? QUIZ_DISCOUNT_CODE : null;
            $code = 'CPM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $purchaseStmt = $pdo->prepare("INSERT INTO compras (codigo_compra, id_user, nombre_cliente, email_cliente, telefono_cliente, total, descuento, codigo_descuento, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'simulado_pagado')");
            $purchaseStmt->execute([$code, $userId, $name, $email, $phone, $finalTotal, $discount, $discountCode]);
            $purchaseId = (int)$pdo->lastInsertId();
            $detailStmt = $pdo->prepare('INSERT INTO compra_detalles (id_compra, id_curso, precio_unitario, cantidad) VALUES (?, ?, ?, ?)');
            $enrolStmt = $pdo->prepare('INSERT INTO inscripciones (id_user, id_curso, id_cocinero, id_compra, fecha_inscripcion) VALUES (?, ?, ?, ?, CURDATE())');
            foreach ($items as $item) {
                $detailStmt->execute([$purchaseId, $item['id_curso'], $item['precio'], $item['cantidad']]);
                $enrolStmt->execute([$userId, $item['id_curso'], $item['id_cocinero'], $purchaseId]);
            }
            $pdo->commit();

            $listHtml = '<ul>';
            $listText = '';
            foreach ($items as $item) {
                $listHtml .= '<li>' . e($item['nombre_curso']) . ' × ' . (int)$item['cantidad'] . ' — ' . number_format((float)$item['subtotal'], 2, ',', '.') . ' €</li>';
                $listText .= '- ' . $item['nombre_curso'] . ' x ' . $item['cantidad'] . ': ' . number_format((float)$item['subtotal'], 2, ',', '.') . " EUR\n";
            }
            $listHtml .= '</ul>';
            $subject = 'Confirmación de compra simulada ' . $code;
            $discountHtml = $discount > 0 ? '<p>Cupón ' . e((string)$discountCode) . ': -' . number_format($discount, 2, ',', '.') . ' €</p>' : '';
            $html = '<h2>¡Gracias por tu inscripción!</h2><p>Hola ' . e($name) . ',</p><p>Tu compra de demostración se ha registrado correctamente. No se ha realizado ningún cobro real.</p>' . $listHtml . '<p>Subtotal: ' . number_format($total, 2, ',', '.') . ' €</p>' . $discountHtml . '<p><strong>Total: ' . number_format($finalTotal, 2, ',', '.') . ' €</strong></p><p>Código de compra: <strong>' . e($code) . '</strong></p>';
            $text = "Hola {$name},\n\nTu compra de demostración está registrada. No se ha realizado ningún cobro real.\n\n{$listText}\nSubtotal: " . number_format($total, 2, ',', '.') . " EUR\nDescuento: " . number_format($discount, 2, ',', '.') . " EUR\nTotal: " . number_format($finalTotal, 2, ',', '.') . " EUR\nCupón: " . (string)($discountCode ?? 'Ninguno') . "\nCódigo de compra: {$code}\n";
            $emailSent = send_project_email($email, $subject, $html, $text);
            if (ADMIN_EMAIL !== '') send_project_email(ADMIN_EMAIL, 'Nueva compra ' . $code, $html, $text);

            $_SESSION['cart'] = [];
            unset($_SESSION['discount_available']);
            $_SESSION['last_order'] = ['code' => $code, 'subtotal' => $total, 'discount' => $discount, 'total' => $finalTotal, 'discount_code' => $discountCode, 'email' => $email, 'email_sent' => $emailSent];
            redirect(BASE_URL . '/index.php?p=confirmacion');
        }

        if ($action === 'submit_candidate') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $specialty = trim((string)($_POST['specialty'] ?? ''));
            $experience = trim((string)($_POST['experience'] ?? ''));
            $motivation = trim((string)($_POST['motivation'] ?? ''));
            if (text_length($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $specialty === '' || $experience === '' || text_length($motivation) < 20 || (string)($_POST['consent'] ?? '') !== '1') {
                throw new RuntimeException('Completa todos los campos y escribe una motivación de al menos 20 caracteres.');
            }
            $cv = encrypt_uploaded_file($_FILES['cv'] ?? [], 'cv', [
                'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ], MAX_CV_SIZE);
            $portfolio = null;
            try {
                if (isset($_FILES['portfolio']) && ($_FILES['portfolio']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $portfolio = encrypt_uploaded_file($_FILES['portfolio'], 'portfolio', ['image/jpeg','image/png','image/webp'], MAX_IMAGE_SIZE);
                }
                $stmt = db()->prepare('INSERT INTO candidaturas (nombre,email,telefono,especialidad,experiencia,motivacion,cv_path,cv_original,cv_mime,portfolio_path,portfolio_original,fecha_caducidad) VALUES (?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL 12 MONTH))');
                $stmt->execute([$name,$email,$phone,$specialty,$experience,$motivation,$cv['path'],$cv['original'],$cv['mime'],$portfolio['path'] ?? null,$portfolio['original'] ?? null]);
            } catch (Throwable $exception) {
                remove_private_file($cv['path']);
                if ($portfolio) remove_private_file($portfolio['path']);
                throw $exception;
            }
            if (ADMIN_EMAIL !== '') {
                $candidateText = "Nueva candidatura de {$name}\nCorreo: {$email}\nEspecialidad: {$specialty}\nExperiencia: {$experience}\n";
                send_project_email(ADMIN_EMAIL, 'Nueva candidatura de chef', '<p>' . nl2br(e($candidateText)) . '</p>', $candidateText);
            }
            set_flash('Tu candidatura se ha enviado correctamente. Gracias por contactar con nosotros.');
            redirect(BASE_URL . '/index.php?p=candidatura');
        }

        if ($action === 'quiz_submit') {
            $skill = (string)($_POST['skill'] ?? 'principiante');
            $cuisine = (string)($_POST['cuisine'] ?? 'sin-preferencia');
            $goal = (string)($_POST['goal'] ?? 'aficion');
            $time = (string)($_POST['time'] ?? 'medio');
            $diet = (string)($_POST['diet'] ?? 'todo');
            $allowed = [
                'skill' => ['principiante','intermedio','avanzado'],
                'cuisine' => ['Coreana','Japonesa','Mexicana','Española','China','sin-preferencia'],
                'goal' => ['diario','profesional','aficion'],
                'time' => ['poco','medio','mucho'],
                'diet' => ['todo','vegetariana','dulce','pescado','carnes'],
            ];
            if (!in_array($skill,$allowed['skill'],true) || !in_array($cuisine,$allowed['cuisine'],true) || !in_array($goal,$allowed['goal'],true) || !in_array($time,$allowed['time'],true) || !in_array($diet,$allowed['diet'],true)) {
                throw new RuntimeException('Responde a todas las preguntas del recomendador.');
            }
            $all = get_courses();
            $ranked = [];
            foreach ($all as $course) {
                $score = 0;
                if ($cuisine !== 'sin-preferencia' && $course['cocina'] === $cuisine) $score += 6;
                if ($skill === 'principiante' && $course['nivel'] === 'basico') $score += 3;
                if ($skill === 'intermedio' && $course['nivel'] === 'intermedio') $score += 3;
                if ($skill === 'avanzado' && $course['nivel'] === 'avanzado') $score += 3;
                if ($goal === 'profesional' && $course['nivel'] === 'avanzado') $score += 2;
                if ($goal === 'diario' && $course['nivel'] === 'basico') $score += 2;
                if ($time === 'poco' && preg_match('/4|5/', $course['duracion'])) $score += 2;
                if ($time === 'mucho' && preg_match('/6|7|8/', $course['duracion'])) $score += 2;
                if ($diet === 'vegetariana' && in_array($course['cocina'], ['Japonesa','Española'], true)) $score += 1;
                if ($diet === 'dulce' && in_array($course['cocina'], ['Japonesa','Española'], true)) $score += 1;
                if ($diet === 'pescado' && in_array($course['cocina'], ['Japonesa','China'], true)) $score += 1;
                if ($diet === 'carnes' && in_array($course['cocina'], ['Coreana','Mexicana','China'], true)) $score += 1;
                $ranked[] = ['course' => $course, 'score' => $score];
            }
            usort($ranked, fn($a,$b) => $b['score'] <=> $a['score']);
            $_SESSION['quiz_result'] = $ranked[0]['course']['slug'] ?? null;
            $_SESSION['discount_available'] = true;
            redirect(BASE_URL . '/index.php?p=recomendador&resultado=1');
        }
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        set_flash($exception->getMessage(), 'error');
        $returnPage = (string)($_POST['return_page'] ?? $page);
        redirect(BASE_URL . '/index.php?p=' . rawurlencode($returnPage));
    }
}

// Cargar los datos necesarios para cada vista.
$featuredCourses = [];
$catalogCourses = [];
$chefs = [];
$course = null;
$cartItems = [];
$cartTotal = 0.0;
try {
    if ($page === 'inicio') {
        $stmt = db()->query('SELECT c.*, ch.nombre AS chef_nombre FROM cursos c LEFT JOIN cocineros ch ON ch.id_cocinero=c.id_cocinero WHERE c.activo=1 AND c.destacado=1 ORDER BY c.nota DESC LIMIT 3');
        $featuredCourses = $stmt->fetchAll();
    } elseif ($page === 'cursos') {
        $catalogCourses = get_courses();
        if ($search !== '') $catalogCourses = array_values(array_filter($catalogCourses, fn($c) => text_position($c['nombre_curso'] . ' ' . $c['cocina'], $search) !== false));
    } elseif ($page === 'chefs') {
        $chefs = get_chefs();
    } elseif ($page === 'curso') {
        $stmt = db()->prepare('SELECT c.*, ch.nombre AS chef_nombre, ch.especialidad AS chef_especialidad FROM cursos c LEFT JOIN cocineros ch ON ch.id_cocinero=c.id_cocinero WHERE c.slug=? AND c.activo=1');
        $stmt->execute([(string)($_GET['slug'] ?? '')]);
        $course = $stmt->fetch() ?: null;
    } elseif ($page === 'cesta') {
        $cart = $_SESSION['cart'] ?? [];
        if ($cart) {
            $ids = array_map('intval', array_keys($cart));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare("SELECT id_curso,nombre_curso,slug,cocina,precio,imagen FROM cursos WHERE activo=1 AND id_curso IN ({$placeholders})");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $item) {
                $item['cantidad'] = (int)$cart[$item['id_curso']];
                $item['subtotal'] = (float)$item['precio'] * $item['cantidad'];
                $cartTotal += $item['subtotal'];
                $cartItems[] = $item;
            }
        }
    }
} catch (Throwable $exception) {
    set_flash('No se ha podido conectar con la base de datos. Importa database/db_cocina.sql y revisa config/config.php.', 'error');
}

$discountAvailable = !empty($_SESSION['discount_available']);
$cartDiscount = $discountAvailable ? round($cartTotal * (QUIZ_DISCOUNT_PERCENT / 100), 2) : 0.00;
$cartFinalTotal = max(0, $cartTotal - $cartDiscount);

$pageTitles = ['inicio'=>'Inicio','cursos'=>'Cursos','chefs'=>'Nuestros chefs','curso'=>$course['nombre_curso'] ?? 'Curso','cesta'=>'Tu cesta','candidatura'=>'Únete al equipo','recomendador'=>'Encuentra tu curso','confirmacion'=>'Compra confirmada'];
$pageTitle = $pageTitles[$page] ?? 'Cocina por el Mundo';
require __DIR__ . '/includes/header.php';
?>

<?php if ($page === 'inicio'): ?>
<main>
    <section class="hero" id="inicio">
        <div class="hero-copy">
            <p class="eyebrow">♨ APRENDE. COCINA. COMPARTE.</p>
            <h1>El mundo cabe<br><span>en tu cocina.</span></h1>
            <p class="hero-text">Cursos prácticos para cocinar sin fronteras, guiados por chefs que convierten cada receta en una historia.</p>
            <div class="hero-actions"><a class="primary-button" href="<?= e(BASE_URL) ?>/index.php?p=cursos">Explorar cursos →</a><a class="text-button" href="<?= e(BASE_URL) ?>/index.php?p=chefs">Conoce a los chefs</a></div>
            <div class="hero-proof"><div class="avatars"><span>MC</span><span>LS</span><span>AR</span></div><div><strong>Aprende a tu ritmo</strong><small>Sabores de distintas culturas</small></div></div>
        </div>
        <div class="hero-art"><div class="plate"><div class="plate-food">✦</div></div><span class="art-note note-one">SABOR <b>sin fronteras</b></span><span class="art-note note-two">COCINA DEL MUNDO</span></div>
    </section>
    <section class="section featured">
        <div class="section-heading"><div><p class="eyebrow">UNA RUTA DE SABORES</p><h2>Cursos que abren el apetito</h2></div><a class="text-button" href="<?= e(BASE_URL) ?>/index.php?p=cursos">Ver todos →</a></div>
        <div class="course-grid">
        <?php foreach ($featuredCourses as $item): ?>
            <?= render_course_card($item) ?>
        <?php endforeach; ?>
        </div>
    </section>
    <section class="quote-section"><div class="quote-mark">“</div><blockquote>La cocina es el lenguaje más bonito para conocer otras culturas.</blockquote><p>— Equipo Cocina por el Mundo</p></section>
    <section class="section"><div class="section-heading"><div><p class="eyebrow">¿NO SABES POR DÓNDE EMPEZAR?</p><h2>Te ayudamos a elegir</h2><p>Responde cinco preguntas y descubre qué curso encaja mejor con tus gustos y tu tiempo.</p></div><a class="primary-button" href="<?= e(BASE_URL) ?>/index.php?p=recomendador">Hacer el test →</a></div></section>
</main>
<?php elseif ($page === 'cursos'): ?>
<main class="page-main">
    <div class="page-intro"><p class="eyebrow">APRENDE A TU RITMO</p><h1>Todos los cursos</h1><p>Elige una cocina, ponte el delantal y empieza tu próxima aventura.</p></div>
    <form class="catalog-toolbar" method="get"><input type="hidden" name="p" value="cursos"><div class="search-field"><span>⌕</span><input name="q" value="<?= e($search ?? '') ?>" placeholder="Buscar por cocina o curso"></div><span><?= count($catalogCourses) ?> cursos disponibles</span><button class="outline-button" type="submit">Buscar</button></form>
    <?php if ($catalogCourses): ?><div class="course-grid catalog-grid"><?php foreach ($catalogCourses as $item): ?><?= render_course_card($item) ?><?php endforeach; ?></div><?php else: ?><div class="empty-state">No hay cursos que coincidan con esa búsqueda.</div><?php endif; ?>
</main>
<?php elseif ($page === 'chefs'): ?>
<main class="page-main">
    <div class="page-intro"><p class="eyebrow">MAESTROS DE LA MESA</p><h1>Nuestros chefs</h1><p>Personas, técnicas y recuerdos detrás de cada plato.</p></div>
    <?php if ($chefs): ?><div class="chef-grid"><?php foreach ($chefs as $index => $chef): ?>
    <article class="chef-card"><div class="chef-photo"><?php if ($chef['imagen'] && is_file(__DIR__ . '/' . $chef['imagen'])): ?><img src="<?= e(BASE_URL . '/' . $chef['imagen']) ?>" alt="<?= e($chef['nombre']) ?>"><?php else: ?><div class="image-placeholder">♨</div><?php endif; ?></div><div class="chef-content"><div class="rating">★ <?= number_format((float)$chef['nota'],1) ?> <span>Nota media</span></div><h3><?= e($chef['nombre']) ?></h3><strong><?= e($chef['especialidad']) ?></strong><p><?= e($chef['biografia']) ?></p></div></article>
    <?php endforeach; ?></div><?php else: ?><div class="empty-state">Todavía no hay chefs publicados.</div><?php endif; ?>
</main>
<?php elseif ($page === 'curso'): ?>
<main class="page-main">
<?php if (!$course): ?><div class="empty-state"><h2>Curso no encontrado</h2><p>Puede que se haya retirado del catálogo.</p><a class="primary-button" href="<?= e(BASE_URL) ?>/index.php?p=cursos">Volver a cursos</a></div>
<?php else: ?>
    <div class="course-detail"><div class="course-detail-image"><?php if ($course['imagen'] && is_file(__DIR__ . '/' . $course['imagen'])): ?><img src="<?= e(BASE_URL . '/' . $course['imagen']) ?>" alt="<?= e($course['nombre_curso']) ?>"><?php else: ?><div class="image-placeholder">♨</div><?php endif; ?></div><div><p class="eyebrow">CURSO DE COCINA · <?= e($course['cocina']) ?></p><h1><?= e($course['nombre_curso']) ?></h1><div class="rating">★ <?= number_format((float)$course['nota'],1) ?> <span>Nota media</span></div><p><?= nl2br(e($course['descripcion'])) ?></p><div class="detail-meta"><span>Nivel: <strong><?= e(ucfirst($course['nivel'])) ?></strong></span><span>Duración: <strong><?= e($course['duracion']) ?></strong></span><?php if ($course['chef_nombre']): ?><span>Chef: <strong><?= e($course['chef_nombre']) ?></strong></span><?php endif; ?></div><div class="detail-price"><?= number_format((float)$course['precio'],2,',','.') ?> €</div><form method="post" action="<?= e(BASE_URL) ?>/index.php?p=curso&slug=<?= e($course['slug']) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add_cart"><input type="hidden" name="course_id" value="<?= (int)$course['id_curso'] ?>"><input type="hidden" name="return_page" value="curso"><input type="hidden" name="slug" value="<?= e($course['slug']) ?>"><button class="primary-button" type="submit">Añadir a la cesta 🛒</button></form></div></div>
    <?php if ($course['cocina'] === 'Coreana'): ?><section class="section korean-dishes"><p class="eyebrow">PLATOS QUE REALIZAREMOS</p><h2>Un recorrido por los sabores de Corea</h2><ul class="dish-list"><li>Kimchi 김치</li><li>Bibimbap 비빔밥</li><li>Samgyeopsal 삼겹살</li><li>Japchae 잡채</li><li>Bulgogi 불고기</li><li>Tteokbokki 떡볶이</li></ul></section><?php endif; ?><div class="notice-box">Este curso forma parte de una plataforma de demostración académica. La compra es simulada y no se realiza ningún cobro.</div>
<?php endif; ?>
</main>
<?php elseif ($page === 'cesta'): ?>
<main class="page-main"><div class="page-intro"><p class="eyebrow">TU SELECCIÓN</p><h1>Tu cesta</h1><p>Revisa tus cursos antes de confirmar la inscripción.</p></div><div class="cart-page">
<?php if (!$cartItems): ?><div class="empty-state"><h2>Tu cesta está vacía</h2><p>Añade un curso para empezar tu aventura culinaria.</p><a class="primary-button" href="<?= e(BASE_URL) ?>/index.php?p=cursos">Ver cursos</a></div>
<?php else: ?>
<form method="post" action="<?= e(BASE_URL) ?>/index.php?p=cesta"><?= csrf_field() ?><input type="hidden" name="action" value="update_cart"><table><thead><tr><th>Curso</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th><th></th></tr></thead><tbody><?php foreach ($cartItems as $item): ?><tr><td><a href="<?= e(BASE_URL) ?>/index.php?p=curso&slug=<?= e($item['slug']) ?>"><strong><?= e($item['nombre_curso']) ?></strong></a><br><small><?= e($item['cocina']) ?></small></td><td><?= number_format((float)$item['precio'],2,',','.') ?> €</td><td><input type="number" min="0" max="10" name="quantity[<?= (int)$item['id_curso'] ?>]" value="<?= (int)$item['cantidad'] ?>"></td><td><?= number_format((float)$item['subtotal'],2,',','.') ?> €</td><td><button class="small-button danger" type="submit" form="remove-<?= (int)$item['id_curso'] ?>">Quitar</button></td></tr><?php endforeach; ?></tbody></table><div class="form-actions"><button class="outline-button" type="submit">Actualizar cesta</button></div></form>
<?php foreach ($cartItems as $item): ?><form id="remove-<?= (int)$item['id_curso'] ?>" method="post" action="<?= e(BASE_URL) ?>/index.php?p=cesta" hidden><?= csrf_field() ?><input type="hidden" name="action" value="remove_cart"><input type="hidden" name="course_id" value="<?= (int)$item['id_curso'] ?>"></form><?php endforeach; ?>
<div class="order-total"><span><?= $discountAvailable ? 'Subtotal' : 'Total' ?></span><strong><?= number_format($cartTotal,2,',','.') ?> €</strong></div><?php if ($discountAvailable): ?><div class="notice-box">Cupón <?= e(QUIZ_DISCOUNT_CODE) ?> aplicado: -<?= number_format($cartDiscount,2,',','.') ?> € (<?= (int)QUIZ_DISCOUNT_PERCENT ?> % de descuento).</div><div class="order-total"><span>Total con descuento</span><strong><?= number_format($cartFinalTotal,2,',','.') ?> €</strong></div><?php endif; ?>
<form class="site-form checkout-form" method="post" action="<?= e(BASE_URL) ?>/index.php?p=cesta" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><h2>Datos de inscripción</h2><p>Al confirmar se generará un código de compra y se intentará enviar una confirmación por correo. No se realizará ningún pago.</p><div class="form-row"><label>Nombre y apellidos<input name="name" required minlength="3" maxlength="120" autocomplete="name"></label><label>Correo electrónico<input name="email" type="email" required maxlength="150" autocomplete="email"></label></div><label>Teléfono<input name="phone" type="tel" required maxlength="25" autocomplete="tel"></label><label><input type="checkbox" name="consent" value="1" required> Acepto el tratamiento de estos datos para gestionar la inscripción.</label><div class="notice-box">Pago simulado para el proyecto. No introduzcas datos bancarios.</div><div class="checkout-summary"><span>Total de la compra</span><strong><?= number_format($cartFinalTotal,2,',','.') ?> €</strong></div><button class="primary-button" type="submit">Simular compra ✦</button></form>
<?php endif; ?></div></main>
<?php elseif ($page === 'confirmacion'): ?>
<main class="page-main"><div class="empty-state" style="max-width:760px;margin:70px auto;"><p class="eyebrow" style="justify-content:center">INSCRIPCIÓN REGISTRADA</p><h1>¡Gracias por tu compra!</h1><?php $order = $_SESSION['last_order'] ?? null; ?><?php if ($order): ?><p>Tu pedido de demostración se ha guardado correctamente. No se ha realizado ningún cobro real.</p><div class="order-code"><?= e($order['code']) ?></div><p>Subtotal: <?= number_format((float)$order['subtotal'],2,',','.') ?> €</p><?php if ((float)$order['discount'] > 0): ?><p>Descuento <?= e((string)$order['discount_code']) ?>: -<?= number_format((float)$order['discount'],2,',','.') ?> €</p><?php endif; ?><p>Total registrado: <strong><?= number_format((float)$order['total'],2,',','.') ?> €</strong></p><p>Correo indicado: <?= e($order['email']) ?></p><?php if ($order['email_sent']): ?><p>La confirmación se ha enviado por correo.</p><?php else: ?><div class="notice-box">La compra está registrada, pero el servidor todavía no ha podido enviar el correo. Configura SMTP en config/config.php; el contenido de prueba queda en storage/logs/correos_pendientes.log.</div><?php endif; ?><?php else: ?><p>No hay una compra reciente que mostrar.</p><?php endif; ?><a class="primary-button" href="<?= e(BASE_URL) ?>/index.php?p=cursos">Seguir explorando cursos</a></div></main>
<?php elseif ($page === 'candidatura'): ?>
<main class="page-main"><div class="page-intro"><p class="eyebrow">CRECE CON NOSOTROS</p><h1>Únete a nuestro equipo</h1><p>¿Tienes experiencia en cocina y te gustaría enseñar? Cuéntanos tu trayectoria.</p></div>
<form class="site-form" method="post" enctype="multipart/form-data" action="<?= e(BASE_URL) ?>/index.php?p=candidatura" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="action" value="submit_candidate"><h2>Formulario de candidatura</h2><p>Los datos se usarán únicamente para valorar tu candidatura. Los documentos se almacenan cifrados y se eliminan al cumplir 12 meses.</p><div class="form-row"><label>Nombre y apellidos<input name="name" required minlength="3" maxlength="120" autocomplete="name"></label><label>Correo electrónico<input name="email" required type="email" maxlength="150" autocomplete="email"></label></div><div class="form-row"><label>Teléfono<input name="phone" required type="tel" maxlength="25"></label><label>Especialidad principal<select name="specialty" required><option value="">Selecciona una especialidad</option><option>Cocina tradicional</option><option>Cocina internacional</option><option>Repostería</option><option>Cocina de vanguardia</option><option>Nutrición</option><option>Otra</option></select></label></div><label>Experiencia previa<input name="experience" required maxlength="100" placeholder="Ej. 6 años en cocina y 2 como docente"></label><label>¿Por qué te gustaría unirte a nosotros?<textarea name="motivation" rows="5" required minlength="20" maxlength="3000"></textarea></label><label>Currículum (PDF, DOC o DOCX)<input type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required><span class="form-help">Tamaño máximo: 8 MB. El documento se cifra antes de guardarse.</span></label><label>Portfolio fotográfico (opcional)<input type="file" name="portfolio" accept="image/jpeg,image/png,image/webp"><span class="form-help">JPG, PNG o WEBP. Tamaño máximo: 5 MB.</span></label><label><input type="checkbox" name="consent" value="1" required> Acepto el tratamiento de mis datos para valorar esta candidatura.</label><button class="primary-button" type="submit">Enviar candidatura →</button></form></main>
<?php elseif ($page === 'recomendador'): ?>
<main class="page-main"><div class="page-intro"><p class="eyebrow">RECOMENDACIÓN PERSONALIZADA</p><h1>Encuentra tu curso ideal</h1><p>Responde a cinco preguntas. No te pediremos datos personales.</p></div>
<?php $resultSlug = $_SESSION['quiz_result'] ?? null; if (isset($_GET['resultado']) && $resultSlug): $stmt = db()->prepare('SELECT * FROM cursos WHERE slug=? AND activo=1'); $stmt->execute([$resultSlug]); $recommended = $stmt->fetch(); unset($_SESSION['quiz_result']); ?>
<div class="recommend-result" data-recommend-result style="max-width:780px;margin:35px auto;"><div class="recommend-loader"><p class="eyebrow" style="justify-content:center">ANALIZANDO TUS RESPUESTAS</p><p>Estamos buscando una cocina que encaje contigo…</p><div class="progress-track"><span></span></div></div><div class="empty-state recommend-card" hidden><p class="eyebrow" style="justify-content:center">TU RECOMENDACIÓN</p><h2><?= e($recommended['nombre_curso'] ?? 'No hay un curso disponible') ?></h2><?php if ($recommended): ?><p><?= e($recommended['descripcion']) ?></p><p class="rating">★ <?= number_format((float)$recommended['nota'],1) ?> · <?= number_format((float)$recommended['precio'],2,',','.') ?> €</p><div class="notice-box">Has completado el test. Tu código de descuento es <strong><?= e(QUIZ_DISCOUNT_CODE) ?></strong> (<?= (int)QUIZ_DISCOUNT_PERCENT ?> %), y se aplicará automáticamente a tu próxima compra en esta sesión.</div><a class="primary-button" href="<?= e(BASE_URL) ?>/index.php?p=curso&slug=<?= e($recommended['slug']) ?>">Ver curso y matricularme</a><?php endif; ?><p><a href="<?= e(BASE_URL) ?>/index.php?p=recomendador">Volver a realizar el test</a></p></div></div>
<?php else: ?>
<form class="site-form" method="post" action="<?= e(BASE_URL) ?>/index.php?p=recomendador"><?= csrf_field() ?><input type="hidden" name="action" value="quiz_submit">
<fieldset class="quiz-question">
    <legend>1. ¿Cómo describirías tu nivel actual?</legend>
    <div class="quiz-options">
        <label><input type="radio" name="skill" value="principiante" required> Estoy empezando</label>
        <label><input type="radio" name="skill" value="intermedio"> Ya conozco técnicas básicas</label>
        <label><input type="radio" name="skill" value="avanzado"> Tengo experiencia avanzada</label>
    </div>
</fieldset>
<fieldset class="quiz-question">
    <legend>2. ¿Qué cocina te apetece explorar?</legend>
    <div class="quiz-options">
        <label><input type="radio" name="cuisine" value="Coreana" required> Coreana</label>
        <label><input type="radio" name="cuisine" value="Japonesa"> Japonesa</label>
        <label><input type="radio" name="cuisine" value="Mexicana"> Mexicana</label>
        <label><input type="radio" name="cuisine" value="Española"> Española</label>
        <label><input type="radio" name="cuisine" value="China"> China</label>
        <label><input type="radio" name="cuisine" value="sin-preferencia"> Me dejo sorprender</label>
    </div>
</fieldset>
<fieldset class="quiz-question">
    <legend>3. ¿Cuál es tu objetivo?</legend>
    <div class="quiz-options">
        <label><input type="radio" name="goal" value="diario" required> Cocinar mejor cada día</label>
        <label><input type="radio" name="goal" value="profesional"> Profesionalizarme</label>
        <label><input type="radio" name="goal" value="aficion"> Disfrutar como afición</label>
    </div>
</fieldset>
<fieldset class="quiz-question">
    <legend>4. ¿Cuánto tiempo puedes dedicar?</legend>
    <div class="quiz-options">
        <label><input type="radio" name="time" value="poco" required> Poco tiempo</label>
        <label><input type="radio" name="time" value="medio"> 3-4 horas a la semana</label>
        <label><input type="radio" name="time" value="mucho"> Más de 5 horas</label>
    </div>
</fieldset>
<fieldset class="quiz-question">
    <legend>5. ¿Qué tipo de recetas prefieres?</legend>
    <div class="quiz-options">
        <label><input type="radio" name="diet" value="todo" required> De todo un poco</label>
        <label><input type="radio" name="diet" value="vegetariana"> Cocina vegana o vegetariana</label>
        <label><input type="radio" name="diet" value="dulce"> Dulces y postres</label>
        <label><input type="radio" name="diet" value="pescado"> Pescados y cocina ligera</label>
        <label><input type="radio" name="diet" value="carnes"> Platos con carne</label>
    </div>
</fieldset>
<button class="primary-button" type="submit">Ver mi recomendación →</button></form>
<?php endif; ?></main>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
