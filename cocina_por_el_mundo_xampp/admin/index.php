<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$tab = (string)($_GET['tab'] ?? 'cursos');
if (!in_array($tab, ['cursos','chefs','compras','candidaturas'], true)) $tab = 'cursos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_course') {
            $title = trim((string)($_POST['title'] ?? ''));
            $cuisine = trim((string)($_POST['cuisine'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $level = (string)($_POST['level'] ?? 'basico');
            $duration = trim((string)($_POST['duration'] ?? ''));
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_FLOAT);
            $chefId = ($_POST['chef_id'] ?? '') !== '' ? (int)$_POST['chef_id'] : null;
            if ($title === '' || $cuisine === '' || $description === '' || $duration === '' || $price === false || $price < 0 || $rating === false || $rating < 0 || $rating > 5 || !in_array($level,['basico','intermedio','avanzado'],true)) throw new RuntimeException('Revisa los datos del curso.');
            $image = store_public_image($_FILES['image'] ?? [], 'cursos');
            if (!$image) throw new RuntimeException('Selecciona una imagen para el curso.');
            $slug = slugify($title) . '-' . bin2hex(random_bytes(3));
            $stmt = db()->prepare('INSERT INTO cursos (nombre_curso,slug,cocina,descripcion,nivel,duracion,precio,nota,imagen,destacado,id_cocinero) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$title,$slug,$cuisine,$description,$level,$duration,$price,$rating,$image,!empty($_POST['featured']) ? 1 : 0,$chefId ?: null]);
            set_flash('Curso publicado correctamente.');
            redirect(BASE_URL . '/admin/index.php?tab=cursos');
        }

        if ($action === 'save_chef') {
            $name = trim((string)($_POST['name'] ?? ''));
            $specialty = trim((string)($_POST['specialty'] ?? ''));
            $bio = trim((string)($_POST['bio'] ?? ''));
            $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_FLOAT);
            if (text_length($name) < 3 || $specialty === '' || $bio === '' || $rating === false || $rating < 0 || $rating > 5) throw new RuntimeException('Revisa los datos del chef.');
            $image = store_public_image($_FILES['image'] ?? [], 'chefs');
            if (!$image) throw new RuntimeException('Selecciona una imagen para el chef.');
            $stmt = db()->prepare('INSERT INTO cocineros (nombre,especialidad,biografia,nota,imagen) VALUES (?,?,?,?,?)');
            $stmt->execute([$name,$specialty,$bio,$rating,$image]);
            set_flash('Chef añadido correctamente.');
            redirect(BASE_URL . '/admin/index.php?tab=chefs');
        }

        if ($action === 'delete_course') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('UPDATE cursos SET activo=0 WHERE id_curso=?');
            $stmt->execute([$id]);
            set_flash('Curso retirado del catálogo. Se conserva el historial de compras.');
            redirect(BASE_URL . '/admin/index.php?tab=cursos');
        }

        if ($action === 'delete_chef') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE cursos SET id_cocinero=NULL WHERE id_cocinero=?')->execute([$id]);
            $pdo->prepare('UPDATE cocineros SET activo=0 WHERE id_cocinero=?')->execute([$id]);
            $pdo->commit();
            set_flash('Chef retirado del directorio.');
            redirect(BASE_URL . '/admin/index.php?tab=chefs');
        }

        if ($action === 'promote_candidate') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT * FROM candidaturas WHERE id_candidatura=?');
            $stmt->execute([$id]);
            $candidate = $stmt->fetch();
            if (!$candidate) throw new RuntimeException('No se ha encontrado la candidatura.');
            $imagePath = promote_candidate_image($candidate['portfolio_path']);
            $pdo = db();
            $pdo->beginTransaction();
            $chefStmt = $pdo->prepare('INSERT INTO cocineros (nombre,especialidad,biografia,nota,imagen) VALUES (?,?,?,?,?)');
            $chefStmt->execute([$candidate['nombre'],$candidate['especialidad'],$candidate['motivacion'],5.0,$imagePath]);
            $pdo->prepare("UPDATE candidaturas SET estado='aceptada' WHERE id_candidatura=?")->execute([$id]);
            $pdo->commit();
            set_flash('Candidatura aceptada y perfil de chef creado.');
            redirect(BASE_URL . '/admin/index.php?tab=chefs');
        }

        if ($action === 'candidate_status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'pendiente');
            if (!in_array($status,['pendiente','revisada','descartada','aceptada'],true)) throw new RuntimeException('Estado no válido.');
            $stmt = db()->prepare('UPDATE candidaturas SET estado=? WHERE id_candidatura=?');
            $stmt->execute([$status,$id]);
            set_flash('Estado de candidatura actualizado.');
            redirect(BASE_URL . '/admin/index.php?tab=candidaturas');
        }
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        set_flash($exception->getMessage(), 'error');
        redirect(BASE_URL . '/admin/index.php?tab=' . rawurlencode($tab));
    }
}

$courses = get_courses(false);
$chefs = get_chefs(false);
$orders = [];
$applications = [];
if ($tab === 'compras') {
    $orders = db()->query('SELECT c.*, u.email AS email_usuario, (SELECT GROUP_CONCAT(CONCAT(co.nombre_curso," ×",cd.cantidad) SEPARATOR ", ") FROM compra_detalles cd JOIN cursos co ON co.id_curso=cd.id_curso WHERE cd.id_compra=c.id_compra) AS cursos FROM compras c JOIN usuarios u ON u.id_user=c.id_user ORDER BY c.fecha_compra DESC LIMIT 100')->fetchAll();
}
if ($tab === 'candidaturas') {
    $applications = db()->query('SELECT * FROM candidaturas ORDER BY fecha_envio DESC LIMIT 100')->fetchAll();
}
$counts = [
    'cursos' => (int)db()->query('SELECT COUNT(*) FROM cursos WHERE activo=1')->fetchColumn(),
    'chefs' => (int)db()->query('SELECT COUNT(*) FROM cocineros WHERE activo=1')->fetchColumn(),
    'compras' => (int)db()->query('SELECT COUNT(*) FROM compras')->fetchColumn(),
    'candidaturas' => (int)db()->query("SELECT COUNT(*) FROM candidaturas WHERE estado='pendiente'")->fetchColumn(),
];
$pageTitle = 'Panel de administración';
require __DIR__ . '/../includes/header.php';
?>
<main class="page-main admin-main">
    <div class="admin-heading"><div><p class="eyebrow">ESPACIO PRIVADO DE GESTIÓN</p><h1>Panel de administración</h1><p>Bienvenido, <?= e($_SESSION['admin_name'] ?? 'administrador') ?>. Gestiona el contenido de la escuela.</p></div><span class="admin-status"><span></span> Modo administrador<br><a href="<?= e(BASE_URL) ?>/admin/logout.php">Cerrar sesión</a></span></div>
    <div class="admin-tabs">
        <a class="<?= $tab==='cursos'?'selected':'' ?>" href="?tab=cursos">Cursos <b><?= $counts['cursos'] ?></b></a>
        <a class="<?= $tab==='chefs'?'selected':'' ?>" href="?tab=chefs">Chefs <b><?= $counts['chefs'] ?></b></a>
        <a class="<?= $tab==='compras'?'selected':'' ?>" href="?tab=compras">Compras <b><?= $counts['compras'] ?></b></a>
        <a class="<?= $tab==='candidaturas'?'selected':'' ?>" href="?tab=candidaturas">Candidaturas <b><?= $counts['candidaturas'] ?></b></a>
    </div>

    <?php if ($tab === 'cursos'): ?>
    <section class="admin-layout">
        <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= e(BASE_URL) ?>/admin/index.php?tab=cursos" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="action" value="save_course"><h2>Añadir curso</h2><p>Rellena los datos y sube la imagen que aparecerá en la ficha del curso.</p>
            <label>Nombre del curso<input name="title" required maxlength="120" placeholder="Ej. Cocina peruana esencial"></label>
            <div class="form-row"><label>Cocina<input name="cuisine" required placeholder="Peruana"></label><label>Nivel<select name="level"><option value="basico">Básico</option><option value="intermedio">Intermedio</option><option value="avanzado">Avanzado</option></select></label></div>
            <div class="form-row"><label>Duración<input name="duration" required placeholder="5 semanas"></label><label>Precio (€)<input name="price" required type="number" min="0" step="0.01" placeholder="49.00"></label></div>
            <div class="form-row"><label>Nota media<input name="rating" required type="number" min="0" max="5" step="0.1" value="5.0"></label><label>Chef (opcional)<select name="chef_id"><option value="">Sin asignar</option><?php foreach ($chefs as $chef): if ($chef['activo']): ?><option value="<?= (int)$chef['id_cocinero'] ?>"><?= e($chef['nombre']) ?></option><?php endif; endforeach; ?></select></label></div>
            <label>Descripción<textarea name="description" required rows="4" placeholder="Cuenta qué aprenderá el alumno..."></textarea></label>
            <label>Imagen del curso (JPG, PNG o WEBP)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required data-preview="#course-preview"><img id="course-preview" class="admin-thumb" alt="Vista previa" hidden></label>
            <label><input type="checkbox" name="featured" value="1"> Mostrar en portada</label>
            <button class="primary-button" type="submit">Publicar curso +</button>
        </form>
        <div class="admin-list"><h2>Cursos</h2><?php foreach ($courses as $item): ?><div class="admin-list-item"><?php if ($item['imagen'] && is_file(__DIR__ . '/../' . $item['imagen'])): ?><img class="admin-thumb" src="<?= e(BASE_URL . '/' . $item['imagen']) ?>" alt=""><?php else: ?><span class="mini-placeholder">♨</span><?php endif; ?><div><strong><?= e($item['nombre_curso']) ?></strong><small><?= e($item['cocina']) ?> · <?= number_format((float)$item['precio'],2,',','.') ?> € · <?= $item['activo'] ? 'Publicado' : 'Retirado' ?></small></div><span class="list-rating">★ <?= number_format((float)$item['nota'],1) ?></span><?php if ($item['activo']): ?><form method="post" action="<?= e(BASE_URL) ?>/admin/index.php?tab=cursos"><?= csrf_field() ?><input type="hidden" name="action" value="delete_course"><input type="hidden" name="id" value="<?= (int)$item['id_curso'] ?>"><button class="small-button danger" type="submit" data-confirm="¿Retirar este curso del catálogo?">Eliminar</button></form><?php endif; ?></div><?php endforeach; ?></div>
    </section>
    <?php elseif ($tab === 'chefs'): ?>
    <section class="admin-layout">
        <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= e(BASE_URL) ?>/admin/index.php?tab=chefs" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="action" value="save_chef"><h2>Añadir chef</h2><p>Presenta a la persona que acompañará a tus alumnos.</p><label>Nombre completo<input name="name" required maxlength="100" placeholder="Nombre del chef"></label><label>Especialidad<input name="specialty" required maxlength="100" placeholder="Cocina mediterránea"></label><label>Biografía<textarea name="bio" required rows="4" placeholder="Una breve presentación..."></textarea></label><label>Nota media<input name="rating" type="number" min="0" max="5" step="0.1" value="5.0" required></label><label>Imagen del chef (JPG, PNG o WEBP)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required data-preview="#chef-preview"><img id="chef-preview" class="admin-thumb" alt="Vista previa" hidden></label><button class="primary-button" type="submit">Añadir chef +</button></form>
        <div class="admin-list"><h2>Chefs</h2><?php foreach ($chefs as $chef): ?><div class="admin-list-item"><?php if ($chef['imagen'] && is_file(__DIR__ . '/../' . $chef['imagen'])): ?><img class="admin-thumb" src="<?= e(BASE_URL . '/' . $chef['imagen']) ?>" alt=""><?php else: ?><span class="mini-placeholder chef-mini">♨</span><?php endif; ?><div><strong><?= e($chef['nombre']) ?></strong><small><?= e($chef['especialidad']) ?> · <?= $chef['activo'] ? 'Publicado' : 'Retirado' ?></small></div><span class="list-rating">★ <?= number_format((float)$chef['nota'],1) ?></span><?php if ($chef['activo']): ?><form method="post" action="<?= e(BASE_URL) ?>/admin/index.php?tab=chefs"><?= csrf_field() ?><input type="hidden" name="action" value="delete_chef"><input type="hidden" name="id" value="<?= (int)$chef['id_cocinero'] ?>"><button class="small-button danger" type="submit" data-confirm="¿Retirar este chef del directorio?">Eliminar</button></form><?php endif; ?></div><?php endforeach; ?></div>
    </section>
    <?php elseif ($tab === 'compras'): ?>
    <section class="section"><h2>Compras registradas</h2><div class="cart-page"><table><thead><tr><th>Código</th><th>Cliente</th><th>Formación</th><th>Total</th><th>Fecha</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['codigo_compra']) ?></strong><br><small>Simulada</small></td><td><?= e($order['nombre_cliente']) ?><br><small><?= e($order['email_cliente']) ?></small></td><td><?= e($order['cursos'] ?? '') ?></td><td><?= number_format((float)$order['total'],2,',','.') ?> €<?php if ((float)$order['descuento'] > 0): ?><br><small>Cupón <?= e($order['codigo_descuento']) ?> · -<?= number_format((float)$order['descuento'],2,',','.') ?> €</small><?php endif; ?></td><td><?= e($order['fecha_compra']) ?></td></tr><?php endforeach; ?></tbody></table><?php if (!$orders): ?><div class="empty-state">Todavía no hay compras.</div><?php endif; ?></div></section>
    <?php elseif ($tab === 'candidaturas'): ?>
    <section class="section"><h2>Candidaturas recibidas</h2><p>Los currículums y portfolios están cifrados en el servidor. La fecha de caducidad se fija a 12 meses.</p><?php foreach ($applications as $app): ?><article class="admin-list-item" style="background:var(--paper);padding:20px;margin:12px 0;border:1px solid var(--line);border-radius:6px;align-items:flex-start;"><div style="flex:1"><strong><?= e($app['nombre']) ?></strong><small><?= e($app['email']) ?> · <?= e($app['telefono']) ?></small><p><strong><?= e($app['especialidad']) ?></strong> · <?= e($app['experiencia']) ?></p><p><?= nl2br(e($app['motivacion'])) ?></p><p><small>Enviada: <?= e($app['fecha_envio']) ?> · Caduca: <?= e($app['fecha_caducidad']) ?> · Estado: <?= e($app['estado']) ?></small></p><a class="small-button" href="<?= e(BASE_URL) ?>/admin/descargar_archivo.php?id=<?= (int)$app['id_candidatura'] ?>&tipo=cv">Descargar CV</a><?php if ($app['portfolio_path']): ?> <a class="small-button" href="<?= e(BASE_URL) ?>/admin/descargar_archivo.php?id=<?= (int)$app['id_candidatura'] ?>&tipo=portfolio">Descargar portfolio</a><?php endif; ?><?php if ($app['estado'] !== 'aceptada'): ?><form method="post" action="<?= e(BASE_URL) ?>/admin/index.php?tab=candidaturas" style="display:inline-block;margin-top:12px;"><?= csrf_field() ?><input type="hidden" name="action" value="promote_candidate"><input type="hidden" name="id" value="<?= (int)$app['id_candidatura'] ?>"><button class="small-button" type="submit" data-confirm="¿Aceptar esta candidatura y crear el perfil público de chef?">Promocionar a chef</button></form><?php endif; ?></div><form method="post" action="<?= e(BASE_URL) ?>/admin/index.php?tab=candidaturas"><?= csrf_field() ?><input type="hidden" name="action" value="candidate_status"><input type="hidden" name="id" value="<?= (int)$app['id_candidatura'] ?>"><select name="status"><option value="pendiente" <?= $app['estado']==='pendiente'?'selected':'' ?>>Pendiente</option><option value="revisada" <?= $app['estado']==='revisada'?'selected':'' ?>>Revisada</option><option value="aceptada" <?= $app['estado']==='aceptada'?'selected':'' ?>>Aceptada</option><option value="descartada" <?= $app['estado']==='descartada'?'selected':'' ?>>Descartada</option></select><button class="small-button" type="submit">Guardar</button></form></article><?php endforeach; ?><?php if (!$applications): ?><div class="empty-state">No se han recibido candidaturas todavía.</div><?php endif; ?></section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
