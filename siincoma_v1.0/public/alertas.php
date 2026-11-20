<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_login();
[$w, $p] = alcance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $b = $_POST;
    if (($b['accion'] ?? '') === 'seguimiento' && is_admin()) {
        $est = one('SELECT nombre FROM estado_alerta WHERE id_estado = ?', [(int)($b['id_estado'] ?? 0)]);
        $id = (int)($b['id'] ?? 0);
        if (!$est || !one('SELECT 1 FROM alerta WHERE id_alerta = ?', [$id])) { flash('Alerta o estado no válidos.', 'err'); go('alertas.php'); }
        q('UPDATE alerta SET id_estado = ?, fecha_cierre = ' . ($est['nombre'] === 'Resuelta' ? 'NOW()' : 'NULL') . ' WHERE id_alerta = ?', [(int)$b['id_estado'], $id]);
        q('INSERT INTO seguimiento_alerta (id_alerta, id_usuario, id_estado, observacion) VALUES (?,?,?,?)', [$id, (int)user()['id'], (int)$b['id_estado'], trim((string)($b['observacion'] ?? '')) ?: null]);
        audit('alerta', $id, 'UPDATE', 'Estado: ' . $est['nombre']);
        flash('Alerta actualizada.'); go('alertas.php');
    }
    $idm = (int)($b['id_mascota'] ?? 0);
    if (!$idm || !(int)($b['id_tipo'] ?? 0) || !one("SELECT 1 FROM mascota m WHERE m.id_mascota = ? AND $w", [$idm, ...$p])) {
        flash('Seleccione una mascota propia y el tipo de alerta.', 'err'); go('alertas.php');
    }
    q("INSERT INTO alerta (id_mascota, id_tipo, id_estado, id_creador, origen, descripcion)
       VALUES (?, ?, (SELECT id_estado FROM estado_alerta WHERE nombre = 'Activa'), ?, 'Manual', ?)",
      [$idm, (int)$b['id_tipo'], (int)user()['id'], trim((string)($b['descripcion'] ?? '')) ?: null]);
    audit('alerta', (int)db()->lastInsertId(), 'INSERT');
    flash('Alerta creada.'); go('alertas.php');
}

$tipos = rows('SELECT * FROM tipo_alerta ORDER BY id_tipo');
$estados = rows('SELECT * FROM estado_alerta ORDER BY id_estado');
$mascotas = rows("SELECT m.id_mascota, m.nombre, un.apartamento FROM mascota m JOIN usuario u ON u.id_usuario = m.id_tutor
    JOIN unidad_residencial un ON un.id_unidad = u.id_unidad WHERE m.estado = 'Activa' AND $w ORDER BY m.nombre", $p);
$ft = (int)($_GET['tipo'] ?? 0);
$fe = (int)($_GET['estado'] ?? 0);
$filtros = ($ft ? ' AND a.id_tipo = ?' : '') . ($fe ? ' AND a.id_estado = ?' : '');
$alertas = rows("SELECT a.id_alerta, t.nombre AS tipo, es.nombre AS estado, a.id_estado, a.origen, a.fecha_creacion, m.nombre AS mascota, un.apartamento
    FROM alerta a JOIN mascota m ON m.id_mascota = a.id_mascota JOIN tipo_alerta t ON t.id_tipo = a.id_tipo
    JOIN estado_alerta es ON es.id_estado = a.id_estado JOIN usuario u ON u.id_usuario = m.id_tutor
    JOIN unidad_residencial un ON un.id_unidad = u.id_unidad WHERE $w$filtros ORDER BY a.fecha_creacion DESC LIMIT 200",
    array_merge($p, $ft ? [$ft] : [], $fe ? [$fe] : []));
head('Gestión de alertas', 'alertas.php');
?>
<h1>Gestión de alertas</h1>
<section class="card"><h3>Nueva alerta</h3>
<form method="post" class="grid3">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <label>Mascota<select name="id_mascota"><?php foreach ($mascotas as $m): ?><option value="<?= (int)$m['id_mascota'] ?>"><?= h($m['nombre'] . ' (Apto. ' . $m['apartamento'] . ')') ?></option><?php endforeach; ?></select></label>
  <label>Tipo<select name="id_tipo"><?php foreach ($tipos as $t): ?><option value="<?= (int)$t['id_tipo'] ?>"><?= h($t['nombre']) ?></option><?php endforeach; ?></select></label>
  <label>Descripción<input name="descripcion" placeholder="Dónde y cuándo ocurrió"></label>
  <div class="actions span3"><button class="btn" type="submit">Crear alerta</button></div>
</form></section>
<section class="card"><h3>Alertas</h3>
<form method="get" class="filters">
  <label>Tipo<select name="tipo"><option value="">Todos</option><?php foreach ($tipos as $t): ?><option value="<?= (int)$t['id_tipo'] ?>" <?= $ft === (int)$t['id_tipo'] ? 'selected' : '' ?>><?= h($t['nombre']) ?></option><?php endforeach; ?></select></label>
  <label>Estado<select name="estado"><option value="">Todos</option><?php foreach ($estados as $e): ?><option value="<?= (int)$e['id_estado'] ?>" <?= $fe === (int)$e['id_estado'] ? 'selected' : '' ?>><?= h($e['nombre']) ?></option><?php endforeach; ?></select></label>
  <button class="btn ghost" type="submit">Filtrar</button>
</form>
<table><thead><tr><th>Tipo</th><th>Mascota</th><th>Apto.</th><th>Origen</th><th>Fecha</th><th>Estado</th><?php if (is_admin()): ?><th>Gestionar</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($alertas as $a): ?>
  <tr><td><?= h($a['tipo']) ?></td><td><?= h($a['mascota']) ?></td><td><?= h($a['apartamento']) ?></td><td><?= h($a['origen']) ?></td><td><?= h(substr((string)$a['fecha_creacion'], 0, 10)) ?></td><td><?= pill($a['estado']) ?></td>
  <?php if (is_admin()): ?><td><form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="accion" value="seguimiento"><input type="hidden" name="id" value="<?= (int)$a['id_alerta'] ?>">
    <select name="id_estado" aria-label="Nuevo estado"><?php foreach ($estados as $e): ?><option value="<?= (int)$e['id_estado'] ?>" <?= (int)$e['id_estado'] === (int)$a['id_estado'] ? 'selected' : '' ?>><?= h($e['nombre']) ?></option><?php endforeach; ?></select>
    <input name="observacion" placeholder="Observación" aria-label="Observación"><button class="btn ghost" type="submit">Guardar</button></form></td><?php endif; ?></tr>
<?php endforeach; if (!$alertas): ?><tr><td colspan="7" class="muted">No hay alertas con estos filtros.</td></tr><?php endif; ?>
</tbody></table></section>
<?php foot();
