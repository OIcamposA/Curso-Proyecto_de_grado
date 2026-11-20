<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_login();
[$w, $p] = alcance();
$k = one("SELECT COUNT(*) AS mascotas, COUNT(DISTINCT m.id_tutor) AS tutores, COALESCE(ROUND(100 * AVG(m.vacunas_al_dia)), 0) AS vacunacion
          FROM mascota m WHERE m.estado = 'Activa' AND $w", $p);
$alertas = one("SELECT COUNT(*) AS n FROM alerta a JOIN mascota m ON m.id_mascota = a.id_mascota JOIN estado_alerta es ON es.id_estado = a.id_estado
                WHERE es.nombre <> 'Resuelta' AND $w", $p)['n'];
$especies = rows("SELECT e.nombre, COUNT(*) AS n FROM mascota m JOIN especie e ON e.id_especie = m.id_especie
                  WHERE m.estado = 'Activa' AND $w GROUP BY e.nombre ORDER BY n DESC", $p);
$recientes = rows("SELECT t.nombre AS tipo, es.nombre AS estado, m.nombre AS mascota, un.apartamento
    FROM alerta a JOIN mascota m ON m.id_mascota = a.id_mascota JOIN tipo_alerta t ON t.id_tipo = a.id_tipo
    JOIN estado_alerta es ON es.id_estado = a.id_estado JOIN usuario u ON u.id_usuario = m.id_tutor
    JOIN unidad_residencial un ON un.id_unidad = u.id_unidad WHERE $w ORDER BY a.fecha_creacion DESC LIMIT 5", $p);
$total = max(1, array_sum(array_column($especies, 'n')));
head('Panel de control', 'dashboard.php');
?>
<h1>Panel de control</h1>
<section class="banner"><?= logo(190, 'mark') ?><div class="hello"><?= marca(64) ?><div><h2>Bienvenido a <?= h(cfg('nombre_conjunto')) ?></h2><p>Registre, actualice y dé seguimiento a las mascotas del conjunto.</p></div></div></section>
<section class="stats">
<?php foreach ([['Mascotas registradas', $k['mascotas']], ['Tutores', $k['tutores']], ['Alertas activas', $alertas], ['Vacunación al día', $k['vacunacion'] . ' %']] as [$et, $val]): ?>
  <div class="card"><small><?= h($et) ?></small><strong><?= h($val) ?></strong></div>
<?php endforeach; ?>
</section>
<section class="two">
  <div class="card"><h3>Alertas recientes</h3>
    <table><thead><tr><th>Tipo</th><th>Mascota</th><th>Apto.</th><th>Estado</th></tr></thead><tbody>
    <?php foreach ($recientes as $a): ?>
      <tr><td><?= h($a['tipo']) ?></td><td><?= h($a['mascota']) ?></td><td><?= h($a['apartamento']) ?></td><td><?= pill($a['estado']) ?></td></tr>
    <?php endforeach; if (!$recientes): ?><tr><td colspan="4" class="muted">Aún no hay alertas. Se generan al registrar mascotas sin vacunas al día.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card"><h3>Mascotas por especie</h3>
    <?php foreach ($especies as $e): $pc = (int)round(100 * $e['n'] / $total); ?>
      <div class="bar"><span><?= h($e['nombre']) ?></span><span><?= $pc ?> %</span></div><div class="track"><div style="width:<?= $pc ?>%"></div></div>
    <?php endforeach; if (!$especies): ?><p class="muted">Registre la primera mascota para ver la distribución.</p><?php endif; ?>
  </div>
</section>
<?php foot();
