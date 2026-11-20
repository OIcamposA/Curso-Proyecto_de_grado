<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_login(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $b = $_POST;
    if (($b['accion'] ?? '') === 'estado') {
        $id = (int)($b['id'] ?? 0);
        $estado = $b['estado'] ?? '';
        if (!in_array($estado, ['Activo', 'Inactivo'], true) || $id === (int)user()['id']) { flash('No es posible cambiar ese estado.', 'err'); go('usuarios.php'); }
        q('UPDATE usuario SET estado = ? WHERE id_usuario = ?', [$estado, $id]);
        if ($estado === 'Inactivo') { // alertas automáticas de mudanza (RF-21)
            q("INSERT INTO alerta (id_mascota, id_tipo, id_estado, origen, descripcion)
               SELECT m.id_mascota, (SELECT id_tipo FROM tipo_alerta WHERE nombre = 'Mudanza'), (SELECT id_estado FROM estado_alerta WHERE nombre = 'Activa'), 'Automática', 'Salida del residente'
               FROM mascota m JOIN usuario u ON u.id_usuario = m.id_tutor
               WHERE m.id_tutor = ? AND m.estado = 'Activa' AND u.tipo_residente = 'Arrendatario'", [$id]);
        }
        audit('usuario', $id, 'UPDATE', 'Estado: ' . $estado);
        flash('Estado del usuario actualizado.'); go('usuarios.php');
    }
    $req = ['nombres', 'apellidos', 'documento', 'correo', 'tipo_residente', 'torre_casa', 'apartamento', 'password'];
    $falta = array_filter($req, fn($k) => trim((string)($b[$k] ?? '')) === '');
    $error = null;
    if ($falta) $error = 'Faltan campos obligatorios: ' . implode(', ', $falta) . '.';
    elseif (empty($b['autoriza_datos'])) $error = 'Debe autorizarse el tratamiento de datos personales (Ley 1581 de 2012).';
    elseif (!filter_var($b['correo'], FILTER_VALIDATE_EMAIL)) $error = 'El correo no es válido.';
    elseif (!in_array($b['tipo_residente'], ['Propietario', 'Arrendatario'], true)) $error = 'Tipo de residente no válido.';
    elseif (strlen((string)$b['password']) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
    elseif (one('SELECT 1 FROM usuario WHERE documento = ? OR correo = ?', [trim($b['documento']), trim($b['correo'])])) $error = 'El documento o el correo ya están registrados.';
    if ($error) { flash($error, 'err'); go('usuarios.php'); }

    q('INSERT INTO unidad_residencial (torre_casa, apartamento) VALUES (?, ?) ON DUPLICATE KEY UPDATE id_unidad = LAST_INSERT_ID(id_unidad)', [trim($b['torre_casa']), trim($b['apartamento'])]);
    $unidad = (int)db()->lastInsertId();
    $rol = ($b['rol'] ?? '') === 'Administrador' ? 'Administrador' : 'Residente';
    q("INSERT INTO usuario (id_rol, id_unidad, nombres, apellidos, documento, correo, telefono, tipo_residente, contrasena_hash, estado, autoriza_datos, fecha_autorizacion)
       VALUES ((SELECT id_rol FROM rol WHERE nombre = ?), ?, ?, ?, ?, ?, ?, ?, ?, 'Activo', 1, NOW())",
      [$rol, $unidad, trim($b['nombres']), trim($b['apellidos']), trim($b['documento']), trim($b['correo']), trim((string)($b['telefono'] ?? '')) ?: null, $b['tipo_residente'], password_hash((string)$b['password'], PASSWORD_DEFAULT)]);
    audit('usuario', (int)db()->lastInsertId(), 'INSERT', 'Apto ' . trim($b['apartamento']));
    flash('Usuario registrado. Ya puede ingresar con su número de apartamento.'); go('usuarios.php');
}
$lista = rows('SELECT u.id_usuario, u.nombres, u.apellidos, u.tipo_residente, u.estado, r.nombre AS rol, un.apartamento
               FROM usuario u JOIN rol r ON r.id_rol = u.id_rol JOIN unidad_residencial un ON un.id_unidad = u.id_unidad ORDER BY u.id_usuario DESC');
head('Registro de usuario', 'usuarios.php');
?>
<h1>Registro de usuario</h1>
<section class="card"><h3>Datos del residente</h3>
<form method="post" class="grid3">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <label>Nombres<input name="nombres" required></label>
  <label>Apellidos<input name="apellidos" required></label>
  <label>Documento de identidad<input name="documento" required></label>
  <label>Correo electrónico<input type="email" name="correo" required></label>
  <label>Teléfono<input name="telefono"></label>
  <label>Tipo de residente<select name="tipo_residente"><option>Arrendatario</option><option>Propietario</option></select></label>
  <label>Torre o casa<input name="torre_casa" required placeholder="Torre 1"></label>
  <label>Apartamento (será su usuario)<input name="apartamento" required placeholder="502" maxlength="10"></label>
  <label>Rol<select name="rol"><option>Residente</option><option>Administrador</option></select></label>
  <label>Contraseña inicial (mín. 8 caracteres)<input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
  <label class="chk span2"><input type="checkbox" name="autoriza_datos" value="1" required> Autorizo el tratamiento de mis datos personales conforme a la Ley 1581 de 2012.</label>
  <div class="actions span3"><button class="btn" type="submit">Guardar usuario</button></div>
</form></section>
<section class="card"><h3>Usuarios registrados</h3>
<table><thead><tr><th>Apto.</th><th>Nombre</th><th>Tipo</th><th>Rol</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $u): $nuevo = $u['estado'] === 'Activo' ? 'Inactivo' : 'Activo'; ?>
  <tr><td><?= h($u['apartamento']) ?></td><td><?= h($u['nombres'] . ' ' . $u['apellidos']) ?></td><td><?= h($u['tipo_residente']) ?></td><td><?= h($u['rol']) ?></td><td><?= h($u['estado']) ?></td>
  <td><?php if ((int)$u['id_usuario'] !== (int)user()['id']): ?><form method="post" class="inline" <?= $nuevo === 'Inactivo' ? 'data-confirm="¿Dar de baja a este usuario? Se crearán alertas de mudanza para sus mascotas."' : '' ?>>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>"><input type="hidden" name="estado" value="<?= h($nuevo) ?>">
    <button class="btn ghost" type="submit"><?= $nuevo === 'Inactivo' ? 'Dar de baja' : 'Reactivar' ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="muted">Dar de baja a un arrendatario genera alertas automáticas de mudanza para sus mascotas.</p></section>
<?php foot();
