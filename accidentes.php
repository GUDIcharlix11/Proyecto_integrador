<?php
require_once __DIR__ . '/plantilla.php';
require_once __DIR__ . '/funciones_accidentes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = post('accion');
    $destino = 'accidentes.php';
    try {
        if ($accion === 'reportar') {
            $r = accidente_reportar(post('fecha'), post('hora'), post('matricula'), post('laboratorio'),
                                    post('descripcion'), post('gravedad'));
            avisar('ok', 'Accidente guardado con folio ' . $r['folio'] . '.');

        } elseif ($accion === 'corregir') {
            $folio = (string)post('folio');
            $cambios = [];
            foreach (CAMPOS_EDITABLES as $campo) {
                if (isset($_POST[$campo])) {
                    $cambios[$campo] = $_POST[$campo];
                }
            }
            accidente_modificar($folio, $cambios);
            avisar('ok', "Accidente $folio actualizado.");

        } elseif ($accion === 'eliminar') {
            accidente_eliminar(post('folio'));
            avisar('ok', 'Accidente ' . post('folio') . ' eliminado.');
        }
    } catch (InvalidArgumentException $e) {
        avisar('error', $e->getMessage());
        guardar_viejo($_POST);
        if ($accion === 'corregir') {
            $destino .= '?corregir=' . urlencode((string)post('folio')) . '#formulario';
        }
    } catch (mysqli_sql_exception $e) {
        avisar('error', 'Error de base de datos: ' . $e->getMessage());
    }
    redirigir($destino);
}

$editando = null;
if (isset($_GET['corregir'])) {
    try {
        $editando = accidente_obtener((string)$_GET['corregir']);
    } catch (InvalidArgumentException $e) {
        $AVISO = ['error', $e->getMessage()];
    }
}
$d = ['folio' => '', 'fecha' => date('Y-m-d'), 'hora' => date('H:i'), 'matricula' => '',
      'laboratorio' => '', 'gravedad' => '', 'descripcion' => ''];
if ($editando) {
    $d = array_merge($d, $editando);
}
if ($VIEJO) {
    $d = array_merge($d, array_intersect_key($VIEJO, $d));
}
$modoEditar = $d['folio'] !== '';
$lista = accidentes_todos();

encabezado('Accidentes', 'accidentes');
?>
    <section class="panel">
      <h2 id="formulario"><?= $modoEditar ? 'Corregir el accidente ' . esc($d['folio']) : 'Reportar un accidente' ?></h2>
      <form method="post" action="accidentes.php" autocomplete="off">
        <input type="hidden" name="accion" value="<?= $modoEditar ? 'corregir' : 'reportar' ?>">
<?php if ($modoEditar): ?>
        <input type="hidden" name="folio" value="<?= esc($d['folio']) ?>">
<?php endif; ?>
        <div class="rejilla">
          <label>Fecha (AAAA-MM-DD) <input name="fecha" placeholder="2026-11-23" value="<?= esc($d['fecha']) ?>"<?= $modoEditar ? ' readonly' : '' ?>></label>
          <label>Hora (HH:MM) <input name="hora" placeholder="09:35" value="<?= esc($d['hora']) ?>"></label>
          <label>Matrícula del alumno <input name="matricula" maxlength="50" value="<?= esc($d['matricula']) ?>"></label>
          <label>Laboratorio <input name="laboratorio" placeholder="LAB-QUIM" maxlength="100" value="<?= esc($d['laboratorio']) ?>"></label>
          <label>Gravedad (1 a 5) <input name="gravedad" inputmode="numeric" value="<?= esc($d['gravedad']) ?>"></label>
        </div>
        <label>Descripción <textarea name="descripcion" rows="2"><?= esc($d['descripcion']) ?></textarea></label>
        <p class="ayuda"><?= $modoEditar ? 'El folio y la fecha no se pueden cambiar.' : 'El folio ACC-AAAAMMDD-### se genera solo.' ?></p>
        <button type="submit" class="primario"><?= $modoEditar ? 'Guardar cambios' : 'Reportar accidente' ?></button>
<?php if ($modoEditar): ?>
        <a class="boton" href="accidentes.php">Cancelar edición</a>
<?php endif; ?>
      </form>

      <h2>Accidentes registrados</h2>
      <div class="tabla-scroll">
        <table>
          <thead><tr><th>Folio</th><th>Fecha y hora</th><th>Matrícula</th><th>Lab.</th><th>Gravedad</th><th>Descripción</th><th></th></tr></thead>
          <tbody>
<?php if (!$lista): ?>
            <tr><td colspan="7" class="vacio">No hay accidentes registrados.</td></tr>
<?php endif; ?>
<?php foreach ($lista as $a): ?>
            <tr>
              <td><code><?= esc($a['folio']) ?></code></td>
              <td><?= esc($a['fecha']) ?> <?= esc($a['hora']) ?></td>
              <td><?= esc($a['matricula']) ?></td>
              <td><?= esc($a['laboratorio']) ?></td>
              <td><span class="g g<?= (int)$a['gravedad'] ?>"><?= (int)$a['gravedad'] ?></span></td>
              <td><?= esc($a['descripcion']) ?></td>
              <td>
                <a class="boton" href="accidentes.php?corregir=<?= urlencode($a['folio']) ?>#formulario">Corregir</a>
                <form method="post" action="accidentes.php" class="en-linea" onsubmit="return confirm('¿Seguro que quieres borrar este accidente?')">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="folio" value="<?= esc($a['folio']) ?>">
                  <button type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
<?php pie(); ?>
