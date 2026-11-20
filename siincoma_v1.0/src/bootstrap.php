<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('PUBLIC_DIR', ROOT . '/public');
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    http_response_code(500);
    $acceso = $e instanceof PDOException && str_contains($e->getMessage(), 'Access denied');
    echo '<p style="font-family:sans-serif;padding:24px">' . ($acceso
        ? 'MySQL rechazó el usuario o la contraseña. Revise el archivo src/config.php.'
        : 'No se pudo completar la solicitud. Revise el registro de errores del servidor.') . '</p>';
});

// ---------- base de datos ----------
function config(): array { static $c = null; return $c ??= require __DIR__ . '/config.php'; }
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config();
    return $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { return q($sql, $p)->fetch() ?: null; }
function cfg(string $k, string $d = ''): string {
    static $c = null;
    $c ??= array_column(rows('SELECT clave, valor FROM configuracion'), 'valor', 'clave');
    return $c[$k] ?? $d;
}
function audit(string $entidad, ?int $id, string $accion, ?string $detalle = null): void {
    q('INSERT INTO auditoria (id_usuario, entidad, id_entidad, accion, detalle) VALUES (?,?,?,?,?)', [user()['id'] ?? null, $entidad, $id, $accion, $detalle]);
}

// ---------- utilidades y sesión ----------
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Solicitud no válida. Recargue la página e intente de nuevo.'); }
}
function flash(?string $msg = null, string $tipo = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $tipo]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function user(): array { return $_SESSION['user'] ?? []; }
function is_admin(): bool { return (user()['rol'] ?? '') === 'Administrador'; }
function go(string $page): never { header('Location: ' . $page); exit; }
function require_login(bool $soloAdmin = false): void {
    if (empty($_SESSION['user'])) go('login.php');
    if ($soloAdmin && !is_admin()) { http_response_code(403); exit('Acceso restringido a la administración.'); }
}
/** Condición SQL que limita a los residentes a sus propias mascotas (alias m). */
function alcance(): array { return is_admin() ? ['1=1', []] : ['m.id_tutor = ?', [(int)user()['id']]]; }

// ---------- imágenes (mascotas y logo) ----------
/** Valida y guarda una imagen. Devuelve [ruta relativa|null, error|null]. */
function guardar_imagen(array $f, string $carpeta, int $maxBytes): array {
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return [null, 'La imagen supera el límite del servidor. Use una de menor tamaño.'];
    if ($err !== UPLOAD_ERR_OK) return [null, 'No se pudo cargar la imagen (código ' . $err . ').'];
    if ($f['size'] > $maxBytes) return [null, 'La imagen no debe superar ' . round($maxBytes / 1048576) . ' MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($f['tmp_name']) === false) return [null, 'Formato no permitido. Use una imagen JPG, PNG o WEBP.'];
    $dir = UPLOAD_DIR . '/' . $carpeta;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return [null, 'No se pudo crear la carpeta de imágenes.'];
    $nombre = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nombre)) return [null, 'No se pudo guardar la imagen. Verifique los permisos de la carpeta uploads.'];
    return ['uploads/' . $carpeta . '/' . $nombre, null];
}
function borrar_imagen(?string $ruta): void {
    if ($ruta && preg_match('#^uploads/(mascotas|logo)/[a-f0-9]{24}\.(jpg|png|webp)$#', $ruta)) @unlink(PUBLIC_DIR . '/' . $ruta);
}

// ---------- vistas ----------
function logo(int $w = 36, string $clase = ''): string {
    $h = (int)round($w * 140 / 120);
    return '<svg class="' . $clase . '" width="' . $w . '" height="' . $h . '" viewBox="0 0 120 140" role="img" aria-label="Logo SIINCOMA">'
        . '<path d="M60 6 L108 24 V66 C108 98 88 120 60 134 C32 120 12 98 12 66 V24 Z" fill="#fff"/>'
        . '<g fill="#0B1F4B"><path d="M60 98 C44 88 40 78 48 72 C53 68 58 70 60 75 C62 70 67 68 72 72 C80 78 76 88 60 98 Z"/>'
        . '<ellipse cx="38" cy="60" rx="6" ry="8.5" transform="rotate(-22 38 60)"/><ellipse cx="51" cy="46" rx="6" ry="9" transform="rotate(-8 51 46)"/>'
        . '<ellipse cx="69" cy="46" rx="6" ry="9" transform="rotate(8 69 46)"/><ellipse cx="82" cy="60" rx="6" ry="8.5" transform="rotate(22 82 60)"/></g></svg>';
}
/** Logo del conjunto si fue cargado; si no, el escudo de SIINCOMA. */
function marca(int $w): string {
    $l = cfg('logo');
    return $l ? '<span class="logo-box" style="width:' . $w . 'px;height:' . $w . 'px"><img src="' . h($l) . '" alt="Logo de ' . h(cfg('nombre_conjunto')) . '"></span>' : logo($w);
}
function pill(string $estado): string {
    $c = ['Activa' => 'a', 'En revisión' => 'r', 'Pendiente' => 'p', 'Resuelta' => 'z'][$estado] ?? 'p';
    return '<span class="pill ' . $c . '">' . h($estado) . '</span>';
}
function head(string $titulo, string $activa): void {
    $menu = ['dashboard.php' => ['Inicio', false], 'usuarios.php' => ['Registro de usuario', true],
             'mascotas.php' => ['Registro de mascotas', false], 'alertas.php' => ['Gestión de alertas', false],
             'reportes.php' => ['Reportes', true], 'configuracion.php' => ['Configuración', true]];
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . h($titulo) . ' · ' . h(cfg('nombre_conjunto')) . '</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="app">'
       . '<nav class="side" aria-label="Módulos"><div class="brand">' . marca(42) . '<span><b>' . h(cfg('nombre_conjunto')) . '</b><small>SIINCOMA</small></span></div>';
    foreach ($menu as $archivo => [$nombre, $soloAdmin]) {
        if ($soloAdmin && !is_admin()) continue;
        echo '<a href="' . $archivo . '" class="nv' . ($archivo === $activa ? ' on' : '') . '">' . h($nombre) . '</a>';
    }
    $u = user();
    echo '<div class="who"><b>' . h($u['nombre'] ?? '') . '</b><span>Apto. ' . h($u['apto'] ?? '') . ' · ' . h($u['rol'] ?? '') . '</span>'
       . '<a href="logout.php">Cerrar sesión</a></div></nav><main class="main">';
    if ($f = flash()) echo '<p class="flash ' . h($f[1]) . '" role="status">' . h($f[0]) . '</p>';
}
function foot(): void { echo '</main></div><script src="assets/app.js"></script></body></html>'; }
