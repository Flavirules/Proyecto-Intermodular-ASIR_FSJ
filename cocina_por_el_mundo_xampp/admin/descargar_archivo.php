<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$type = (string)($_GET['tipo'] ?? 'cv');
if (!$id || !in_array($type, ['cv','portfolio'], true)) { http_response_code(404); exit('Archivo no encontrado.'); }
$stmt = db()->prepare('SELECT cv_path,cv_original,cv_mime,portfolio_path,portfolio_original FROM candidaturas WHERE id_candidatura=?');
$stmt->execute([$id]);
$row = $stmt->fetch();
$path = $type === 'cv' ? ($row['cv_path'] ?? null) : ($row['portfolio_path'] ?? null);
$name = $type === 'cv' ? ($row['cv_original'] ?? 'curriculum') : ($row['portfolio_original'] ?? 'portfolio');
$mime = $type === 'cv' ? ($row['cv_mime'] ?? 'application/octet-stream') : 'application/octet-stream';
if (!$row || !$path) { http_response_code(404); exit('Archivo no encontrado.'); }
try { $contents = decrypt_private_file($path); } catch (Throwable $exception) { http_response_code(500); exit(e($exception->getMessage())); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($contents));
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($name)) . '"');
header('X-Content-Type-Options: nosniff');
echo $contents;
exit;
