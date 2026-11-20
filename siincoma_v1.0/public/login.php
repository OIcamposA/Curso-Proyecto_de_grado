<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
if (!empty($_SESSION['user'])) go('dashboard.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $apto = trim((string)($_POST['apartamento'] ?? ''));
    $clave = (string)($_POST['password'] ?? '');
    $est = one('SELECT (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueada, (bloqueado_hasta IS NOT NULL AND bloqueado_hasta <= NOW()) AS vencida FROM login_intento WHERE apartamento = ?', [$apto]);
    if ($est && $est['vencida']) { q('DELETE FROM login_intento WHERE apartamento = ?', [$apto]); $est = null; }
    if ($est && $est['bloqueada']) {
        $error = 'Cuenta bloqueada temporalmente. Intente de nuevo en 10 minutos.';
    } else {
        $usuarios = rows("SELECT u.*, r.nombre AS rol FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
            JOIN unidad_residencial un ON un.id_unidad = u.id_unidad WHERE un.apartamento = ? AND u.estado = 'Activo'", [$apto]);
        foreach ($usuarios as $u) {
            if (password_verify($clave, $u['contrasena_hash'])) {
                q('DELETE FROM login_intento WHERE apartamento = ?', [$apto]);
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => (int)$u['id_usuario'], 'nombre' => $u['nombres'] . ' ' . $u['apellidos'], 'rol' => $u['rol'], 'apto' => $apto];
                go('dashboard.php');
            }
        }
        q('INSERT INTO login_intento (apartamento, intentos) VALUES (?, 1) ON DUPLICATE KEY UPDATE intentos = intentos + 1,
           bloqueado_hasta = IF(intentos >= 5, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NULL)', [$apto]);
        $error = 'Apartamento o contraseña incorrectos.';
    }
}
$nombre = cfg('nombre_conjunto');
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Iniciar sesión · <?= h($nombre) ?></title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login">
<section class="hero">
  <?= logo(520, 'mark') ?>
  <div class="brand big"><?= marca(64) ?><span><b><?= h($nombre) ?></b><small>SIINCOMA</small></span></div>
  <div><h1>Gestione la tenencia de mascotas de su conjunto</h1><p>Sistema de Información para el Control de Mascotas</p></div>
</section>
<section class="form-side">
  <form method="post" class="login-form" novalidate>
    <h2>Iniciar sesión</h2>
    <p class="muted">Ingrese con el número de su apartamento y su contraseña.</p>
    <?php if ($error): ?><p class="flash err" role="alert"><?= h($error) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <label>Apartamento<input name="apartamento" autocomplete="username" required autofocus placeholder="Ej.: 502"></label>
    <label>Contraseña<span class="pass"><input id="clave" type="password" name="password" autocomplete="current-password" required><button type="button" class="link" data-toggle-pass="#clave">Mostrar</button></span></label>
    <button class="btn" type="submit">Ingresar</button>
  </form>
</section>
<script src="assets/app.js"></script>
</body></html>
