<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_login();
const FOTO_MAX = 3 * 1048576;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $b = $_POST;
    if (($b['accion'] ?? '') === 'foto') { // cambiar la foto de una mascota existente
        $m = one('SELECT id_tutor, foto_url FROM mascota WHERE id_mascota = ?', [(int)($b['id'] ?? 0)]);
        if (!$m || (!is_admin() && (int)$m['id_tutor'] !== (int)user()['id'])) { flash('No tiene permiso para modificar esta mascota.', 'err'); go('mascotas.php'); }
        [$ruta, $err] = guardar_imagen($_FILES['foto'] ?? [], 'mascotas', FOTO_MAX);
        if ($err || !$ruta) { flash($err ?? 'Seleccione una imagen.', 'err'); go('mascotas.php'); }
        q('UPDATE mascota SET foto_url = ? WHERE id_mascota = ?', [$ruta, (int)$b['id']]);
        borrar_imagen($m['foto_url']);
        audit('mascota', (int)$b['id'], 'UPDATE', 'Foto actualizada');
        flash('Fotografía actualizada.'); go('mascotas.php');
    }
    $nombre = trim((string)($b['nombre'] ?? ''));
    $esp = (int)($b['id_especie'] ?? 0);
    $tutor = is_admin() ? (int)($b['id_tutor'] ?? 0) : (int)user()['id'];
    $error = null;
    if ($nombre === '' || !$esp) $error = 'El nombre y la especie son obligatorios.';
    elseif (!in_array($b['sexo'] ?? '', ['Hembra', 'Macho'], true)) $error = 'Sexo no válido.';
    elseif (!$tutor || !one("SELECT 1 FROM usuario WHERE id_usuario = ? AND estado = 'Activo'", [$tutor])) $error = 'Seleccione un tutor responsable activo.';
    [$ruta, $errImg] = $error ? [null, null] : guardar_imagen($_FILES['foto'] ?? [], 'mascotas', FOTO_MAX);
    if ($error ?? $errImg) { flash($error ?? $errImg, 'err'); go('mascotas.php'); }
    $nac = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($b['fecha_nacimiento_aprox'] ?? '')) ? $b['fecha_nacimiento_aprox'] : null;
    $vac = isset($b['vacunas_al_dia']) ? 1 : 0;
    q('INSERT INTO mascota (id_tutor, id_especie, nombre, raza, sexo, fecha_nacimiento_aprox, color_senas, condiciones_especiales, foto_url, vacunas_al_dia, esterilizada, microchip)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
      [$tutor, $esp, $nombre, trim((string)($b['raza'] ?? '')) ?: null, $b['sexo'], $nac, trim((string)($b['color_senas'] ?? '')) ?: null,
       trim((string)($b['condiciones_especiales'] ?? '')) ?: null, $ruta, $vac, isset($b['esterilizada']) ? 1 : 0, isset($b['microchip']) ? 1 : 0]);
    $id = (int)db()->lastInsertId();
    if (!$vac) { // alerta automática de vacunación pendiente (RF-22)
        q("INSERT INTO alerta (id_mascota, id_tipo, id_estado, origen, descripcion) VALUES (?, (SELECT id_tipo FROM tipo_alerta WHERE nombre = 'Vacunación'),
           (SELECT id_estado FROM estado_alerta WHERE nombre = 'Pendiente'), 'Automática', 'Vacunas pendientes')", [$id]);
    }
    audit('mascota', $id, 'INSERT', $nombre);
    flash('Mascota registrada.'); go('mascotas.php');
}
[$w, $p] = alcance();
$lista = rows("SELECT m.id_mascota, m.nombre, m.sexo, m.foto_url, m.vacunas_al_dia, m.esterilizada, m.microchip, e.nombre AS especie,
    CONCAT(u.nombres, ' ', u.apellidos) AS tutor, un.apartamento FROM mascota m JOIN especie e ON e.id_especie = m.id_especie
    JOIN usuario u ON u.id_usuario = m.id_tutor JOIN unidad_residencial un ON un.id_unidad = u.id_unidad
    WHERE m.estado = 'Activa' AND $w ORDER BY m.id_mascota DESC LIMIT 300", $p);
$especies = rows('SELECT * FROM especie ORDER BY id_especie');
$residentes = rows("SELECT u.id_usuario, CONCAT(u.nombres, ' ', u.apellidos, ' (', un.apartamento, ')') AS nombre FROM usuario u
    JOIN unidad_residencial un ON un.id_unidad = u.id_unidad WHERE u.estado = 'Activo'" . (is_admin() ? '' : ' AND u.id_usuario = ?') . ' ORDER BY un.apartamento',
    is_admin() ? [] : [(int)user()['id']]);
head('Registro de mascotas', 'mascotas.php');
?>
<h1>Registro de mascotas</h1>
<section class="card"><h3>Datos de la mascota</h3>
<form method="post" enctype="multipart/form-data" class="grid3">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= FOTO_MAX ?>">
  <label>Nombre<input name="nombre" required></label>
  <label>Especie<select name="id_especie"><?php foreach ($especies as $e): ?><option value="<?= (int)$e['id_especie'] ?>"><?= h($e['nombre']) ?></option><?php endforeach; ?></select></label>
  <label>Raza<input name="raza" placeholder="Raza o mestizo"></label>
  <label>Sexo<select name="sexo"><option>Hembra</option><option>Macho</option></select></label>
  <label>Fecha de nacimiento aproximada<input type="date" name="fecha_nacimiento_aprox"></label>
  <label>Color y señas particulares<input name="color_senas"></label>
  <label>Tutor responsable<select name="id_tutor"><?php foreach ($residentes as $r): ?><option value="<?= (int)$r['id_usuario'] ?>"><?= h($r['nombre']) ?></option><?php endforeach; ?></select></label>
  <label class="span2">Condiciones especiales<input name="condiciones_especiales" placeholder="Discapacidad, tratamiento"></label>
  <div class="photo span3">
    <div id="vista-foto" class="preview">Sin fotografía</div>
    <div><label>Fotografía (JPG, PNG o WEBP, máx. 3 MB)<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-preview="#vista-foto" data-msg="#msg-foto" data-max="3"></label>
    <p id="msg-foto" class="err-msg" role="alert"></p></div>
  </div>
  <label class="chk"><input type="checkbox" name="vacunas_al_dia" value="1"> Vacunas al día</label>
  <label class="chk"><input type="checkbox" name="esterilizada" value="1"> Esterilizada</label>
  <label class="chk"><input type="checkbox" name="microchip" value="1"> Cuenta con microchip</label>
  <div class="actions span3"><button class="btn" type="submit">Guardar mascota</button></div>
</form>
<p class="muted">Si la mascota no tiene las vacunas al día, el sistema crea una alerta de vacunación pendiente.</p></section>
<section class="card"><h3><?= is_admin() ? 'Mascotas del conjunto' : 'Mis mascotas' ?></h3>
<div class="filters"><label>Buscar<input type="search" placeholder="Nombre, tutor o apartamento" data-filter="#tabla-mascotas"></label></div>
<table id="tabla-mascotas"><thead><tr><th>Foto</th><th>Nombre</th><th>Especie</th><th>Tutor</th><th>Apto.</th><th>Vacunas</th><th>Cambiar foto</th></tr></thead><tbody>
<?php foreach ($lista as $m): ?>
  <tr><td><?php if ($m['foto_url']): ?><img class="thumb" src="<?= h($m['foto_url']) ?>" alt="Foto de <?= h($m['nombre']) ?>" loading="lazy"><?php else: ?><span class="thumb"></span><?php endif; ?></td>
  <td><?= h($m['nombre']) ?> <small class="muted">(<?= h($m['sexo']) ?>)</small></td><td><?= h($m['especie']) ?></td><td><?= h($m['tutor']) ?></td><td><?= h($m['apartamento']) ?></td><td><?= $m['vacunas_al_dia'] ? 'Sí' : 'No' ?></td>
  <td><form method="post" enctype="multipart/form-data" class="inline"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="accion" value="foto"><input type="hidden" name="id" value="<?= (int)$m['id_mascota'] ?>">
    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" aria-label="Nueva foto de <?= h($m['nombre']) ?>" required><button class="btn ghost" type="submit">Subir</button></form></td></tr>
<?php endforeach; if (!$lista): ?><tr><td colspan="7" class="muted">Aún no hay mascotas registradas. Use el formulario para registrar la primera.</td></tr><?php endif; ?>
</tbody></table></section>
<?php foot();
