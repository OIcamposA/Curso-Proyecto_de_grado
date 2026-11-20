<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_login(true);
const LOGO_MAX = 2 * 1048576;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $nombre = trim((string)($_POST['nombre_conjunto'] ?? ''));
    if ($nombre === '' || mb_strlen($nombre) > 80) { flash('Escriba el nombre del conjunto (máximo 80 caracteres).', 'err'); go('configuracion.php'); }
    [$ruta, $err] = guardar_imagen($_FILES['logo'] ?? [], 'logo', LOGO_MAX);
    if ($err) { flash($err, 'err'); go('configuracion.php'); }
    $anterior = cfg('logo');
    if ($ruta) { q("REPLACE INTO configuracion (clave, valor) VALUES ('logo', ?)", [$ruta]); borrar_imagen($anterior); }
    elseif (isset($_POST['quitar_logo'])) { q("REPLACE INTO configuracion (clave, valor) VALUES ('logo', '')"); borrar_imagen($anterior); }
    q("REPLACE INTO configuracion (clave, valor) VALUES ('nombre_conjunto', ?)", [$nombre]);
    audit('configuracion', null, 'UPDATE', $nombre);
    flash('Configuración guardada.'); go('configuracion.php');
}
head('Configuración', 'configuracion.php');
?>
<h1>Configuración</h1>
<section class="card"><h3>Identidad del conjunto residencial</h3>
<p class="muted">El nombre y el logo aparecen en el inicio de sesión, el menú y el panel de control.</p>
<form method="post" enctype="multipart/form-data" class="grid3">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= LOGO_MAX ?>">
  <label class="span3">Nombre del conjunto<input name="nombre_conjunto" required maxlength="80" value="<?= h(cfg('nombre_conjunto')) ?>"></label>
  <div class="photo span3">
    <div id="vista-logo" class="preview"><?php if (cfg('logo')): ?><img src="<?= h(cfg('logo')) ?>" alt="Logo actual"><?php else: ?>Sin logo<?php endif; ?></div>
    <div><label>Logo (JPG, PNG o WEBP, máx. 2 MB)<input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-preview="#vista-logo" data-msg="#msg-logo" data-max="2"></label>
    <p id="msg-logo" class="err-msg" role="alert"></p>
    <?php if (cfg('logo')): ?><label class="chk"><input type="checkbox" name="quitar_logo" value="1"> Quitar el logo actual</label><?php endif; ?></div>
  </div>
  <div class="actions span3"><button class="btn" type="submit">Guardar configuración</button></div>
</form></section>
<?php foot();
