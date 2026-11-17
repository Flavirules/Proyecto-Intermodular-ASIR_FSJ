<?php
// Ejecutar desde consola (PHP CLI), no desde el navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se puede ejecutar desde la consola.\n");
}
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$stmt = $pdo->query('SELECT id_candidatura,cv_path,portfolio_path FROM candidaturas WHERE fecha_caducidad < CURDATE()');
$expired = $stmt->fetchAll();
foreach ($expired as $row) {
    remove_private_file($row['cv_path']);
    remove_private_file($row['portfolio_path']);
}
$delete = $pdo->prepare('DELETE FROM candidaturas WHERE fecha_caducidad < CURDATE()');
$delete->execute();
echo 'Candidaturas eliminadas: ' . $delete->rowCount() . PHP_EOL;
