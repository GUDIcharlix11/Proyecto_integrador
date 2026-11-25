<?php
require_once __DIR__ . '/conexion.php';

session_start();
$VIEJO = isset($_SESSION['viejo']) ? $_SESSION['viejo'] : [];
$AVISO = isset($_SESSION['aviso']) ? $_SESSION['aviso'] : null;
unset($_SESSION['viejo'], $_SESSION['aviso']);

try {
    db();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sin conexión</title>'
       . '<link rel="stylesheet" href="estilos.css"></head><body><main><h1>No pude conectar con MySQL</h1>'
       . '<p class="aviso error">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
       . '<p>Revisa en el panel de XAMPP que <strong>Apache</strong> y <strong>MySQL</strong> estén en verde (Start) '
       . 'y que los datos de <code>conexion.php</code> sean los tuyos.</p></main></body></html>';
    exit;
}

function esc($t) {
    return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
}

function post($campo, $defecto = '') {
    return isset($_POST[$campo]) ? $_POST[$campo] : $defecto;
}

function viejo($campo, $defecto = '') {
    global $VIEJO;
    return isset($VIEJO[$campo]) ? $VIEJO[$campo] : $defecto;
}

function v($campo, $defecto = '') {
    return esc(viejo($campo, $defecto));
}

function sel($valor, $actual) {
    return (string)$valor === (string)$actual ? ' selected' : '';
}

function num($n) {
    return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
}

function avisar($tipo, $texto) {
    $_SESSION['aviso'] = [$tipo, $texto];
}

function guardar_viejo($datos) {
    $_SESSION['viejo'] = $datos;
}

function redirigir($destino) {
    header('Location: ' . $destino);
    exit;
}

function encabezado($titulo, $activa) {
    global $AVISO;
    $iconos = ['ok' => '✔', 'alerta' => '⚠', 'error' => '✗'];
    $secciones = [
        'kits'       => ['kits.php', 'Kits'],
        'accidentes' => ['accidentes.php', 'Accidentes'],
        'resultados' => ['resultados.php', 'Resultados de práctica'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc($titulo) ?> · Equipo 4</title>
  <link rel="stylesheet" href="estilos.css">
</head>
<body>
  <header class="barra">
    <div>
      <h1>Laboratorios y talleres · Equipo 4</h1>
      <p class="sub">Kits, accidentes y resultados de práctica</p>
    </div>
    <p class="estado">Base de datos: <?= esc(DB_NOMBRE) ?> · conectada</p>
  </header>

  <nav class="tabs" aria-label="Secciones">
<?php foreach ($secciones as $clave => $s): ?>
    <a class="tab<?= $clave === $activa ? ' activa' : '' ?>" href="<?= $s[0] ?>"><?= esc($s[1]) ?></a>
<?php endforeach; ?>
  </nav>

<?php if ($AVISO): ?>
  <p class="aviso <?= esc($AVISO[0]) ?>" role="status"><?= esc(isset($iconos[$AVISO[0]]) ? $iconos[$AVISO[0]] : '') ?> <?= esc($AVISO[1]) ?></p>
<?php endif; ?>

  <main>
<?php
}

function pie() {
    ?>
  </main>
</body>
</html>
<?php
}
