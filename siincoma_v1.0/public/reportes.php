<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require_login(true);

$tipo = $_GET['tipo'] ?? 'censo';
$fecha = fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET[$k] ?? '')) ? $_GET[$k] : '';
$desde = $fecha('desde'); $hasta = $fecha('hasta');
$rango = fn(string $col) => ($desde ? " AND $col >= ?" : '') . ($hasta ? " AND $col < DATE_ADD(?, INTERVAL 1 DAY)" : '');
$p = array_values(array_filter([$desde, $hasta]));
$base = "FROM mascota m JOIN especie e ON e.id_especie = m.id_especie JOIN usuario u ON u.id_usuario = m.id_tutor
         JOIN unidad_residencial un ON un.id_unidad = u.id_unidad WHERE m.estado = 'Activa'" . $rango('m.fecha_registro');
$consultas = [
    'censo' => ["SELECT un.torre_casa AS `Torre o casa`, e.nombre AS Especie, COUNT(*) AS Mascotas $base GROUP BY un.torre_casa, e.nombre ORDER BY 1, 2", 'Censo'],
    'sanitario' => ["SELECT m.nombre AS Mascota, e.nombre AS Especie, un.apartamento AS Apartamento, IF(m.vacunas_al_dia, 'Sí', 'No') AS `Vacunas al día`,
        IF(m.esterilizada, 'Sí', 'No') AS Esterilizada, IF(m.microchip, 'Sí', 'No') AS Microchip $base ORDER BY un.apartamento", 'Estado sanitario'],
    'alertas' => ["SELECT t.nombre AS Tipo, es.nombre AS Estado, COUNT(*) AS Cantidad FROM alerta a JOIN tipo_alerta t ON t.id_tipo = a.id_tipo
        JOIN estado_alerta es ON es.id_estado = a.id_estado WHERE 1 = 1" . $rango('a.fecha_creacion') . ' GROUP BY t.nombre, es.nombre ORDER BY 1, 2', 'Alertas'],
];
if (!isset($consultas[$tipo])) $tipo = 'censo';
$filas = rows($consultas[$tipo][0], $p);
$columnas = $filas ? array_keys($filas[0]) : [];
$csv = ($_GET['exportar'] ?? '') === 'csv';
q('INSERT INTO reporte (id_usuario, tipo, fecha_desde, fecha_hasta, formato) VALUES (?,?,?,?,?)',
  [(int)user()['id'], $consultas[$tipo][1], $desde ?: null, $hasta ?: null, $csv ? 'CSV' : 'Pantalla']);

if ($csv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $tipo . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea las tildes
    fputcsv($out, $columnas, ',', '"', '');
    foreach ($filas as $fila) fputcsv($out, array_values($fila), ',', '"', '');
    exit;
}
head('Reportes', 'reportes.php');
?>
<h1>Reportes</h1>
<section class="card">
<form method="get" class="filters">
  <label>Tipo de reporte<select name="tipo"><?php foreach (['censo' => 'Censo de mascotas', 'sanitario' => 'Estado sanitario', 'alertas' => 'Alertas'] as $k => $n): ?><option value="<?= $k ?>" <?= $k === $tipo ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></label>
  <label>Desde<input type="date" name="desde" value="<?= h($desde) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= h($hasta) ?>"></label>
  <button class="btn" type="submit">Generar</button>
  <button class="btn ghost" type="submit" name="exportar" value="csv">Exportar CSV</button>
</form>
<table><thead><tr><?php foreach ($columnas as $col): ?><th><?= h($col) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($filas as $fila): ?><tr><?php foreach ($fila as $v): ?><td><?= h($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$filas): ?><tr><td class="muted">No hay datos para este reporte y periodo.</td></tr><?php endif; ?>
</tbody></table>
</section>
<?php foot();
