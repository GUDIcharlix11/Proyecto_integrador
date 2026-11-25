<?php
require_once __DIR__ . '/plantilla.php';
require_once __DIR__ . '/funciones_kits.php';

$revision = null;
$devueltas = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = post('accion');
    try {
        if ($accion === 'kit_nuevo') {
            kit_crear(post('kit_nombre'), post('kit_padre'));
            avisar('ok', 'Kit «' . trim((string)post('kit_nombre')) . '» guardado.');

        } elseif ($accion === 'material_nuevo') {
            material_agregar(post('mat_kit'), post('mat_clave'), post('mat_nombre'), post('mat_cantidad'), post('mat_costo'));
            avisar('ok', 'Material ' . trim((string)post('mat_clave')) . ' guardado.');

        } elseif ($accion === 'kit_borrar') {
            kit_eliminar(post('id'));
            avisar('ok', 'Kit eliminado (con sus subkits y materiales).');

        } elseif ($accion === 'material_borrar') {
            material_eliminar(post('id'));
            avisar('ok', 'Material eliminado.');

        } elseif ($accion === 'revisar') {
            $kitRev = buscar_kit(cargar_kits(), (int)post('kit_id', 0));
            if ($kitRev === null) {
                throw new InvalidArgumentException('Elige un kit de la lista.');
            }
            $claves = is_array(post('clave')) ? array_values(post('clave')) : [];
            $devueltas = is_array(post('devuelto')) ? array_values(post('devuelto')) : [];
            $devuelto = [];
            foreach ($claves as $i => $clave) {
                $n = isset($devueltas[$i]) ? trim((string)$devueltas[$i]) : '0';
                if ($n === '') {
                    $n = '0';
                }
                if (!preg_match('/^\d+$/', $n)) {
                    throw new InvalidArgumentException("Las piezas devueltas de $clave deben ser un entero ≥ 0.");
                }
                $devuelto[(string)$clave] = (int)$n;
            }
            $faltantes = revisar_devolucion($kitRev, $devuelto);
            $adeudo = 0;
            foreach ($faltantes as $f) {
                $adeudo += $f['costo_faltante'];
            }
            $revision = ['kit' => $kitRev, 'faltantes' => $faltantes, 'adeudo' => round($adeudo, 2)];
        }
    } catch (InvalidArgumentException $e) {
        avisar('error', $e->getMessage());
        guardar_viejo($_POST);
        redirigir('kits.php' . ($accion === 'revisar' ? '?revisar=' . (int)post('kit_id', 0) . '#revision' : ''));
    } catch (mysqli_sql_exception $e) {
        avisar('error', 'Error de base de datos: ' . $e->getMessage());
        redirigir('kits.php');
    }
    if ($accion !== 'revisar') {
        redirigir('kits.php');
    }
}

$kits = cargar_kits();
$planos = kits_planos($kits);
$idElegido = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int)post('kit_id', 0) : (int)(isset($_GET['revisar']) ? $_GET['revisar'] : 0);
$kitElegido = $idElegido ? buscar_kit($kits, $idElegido) : null;

function arbol_html($kit) {
    echo '<details open><summary>' . esc($kit['nombre']) . '<span class="total">' . contar_piezas($kit) . ' piezas en total</span></summary>';
    if ($kit['materiales']) {
        echo '<ul>';
        foreach ($kit['materiales'] as $m) {
            echo '<li><code>' . esc($m['clave']) . '</code> ' . esc($m['nombre'])
               . ' <span class="cant">×' . $m['cantidad'] . ' · $' . number_format($m['costo'], 2) . ' c/u</span>'
               . '<form method="post" action="kits.php" class="en-linea" onsubmit="return confirm(\'¿Quitar este material?\')">'
               . '<input type="hidden" name="accion" value="material_borrar"><input type="hidden" name="id" value="' . $m['id'] . '">'
               . '<button class="chico">Quitar</button></form></li>';
        }
        echo '</ul>';
    }
    foreach ($kit['subkits'] as $sub) {
        arbol_html($sub);
    }
    echo '<form method="post" action="kits.php" class="en-linea" onsubmit="return confirm(\'Se borrarán también sus subkits y materiales. ¿Seguro?\')">'
       . '<input type="hidden" name="accion" value="kit_borrar"><input type="hidden" name="id" value="' . $kit['id'] . '">'
       . '<button class="chico">Eliminar este kit</button></form>';
    echo '</details>';
}

encabezado('Kits', 'kits');
?>
    <section class="panel">
      <h2>Kits y sus piezas</h2>
      <p class="ayuda">Un kit puede traer otros kits adentro. El total de piezas lo cuenta una función recursiva.</p>
<?php if (!$kits): ?>
      <p class="ayuda">Todavía no hay kits. Registra el primero en el formulario de abajo.</p>
<?php endif; ?>
<?php foreach ($kits as $k) { arbol_html($k); } ?>

      <h2>Registrar un kit</h2>
      <form method="post" action="kits.php" autocomplete="off">
        <input type="hidden" name="accion" value="kit_nuevo">
        <div class="rejilla">
          <label>Nombre del kit <input name="kit_nombre" maxlength="100" required value="<?= v('kit_nombre') ?>"></label>
          <label>¿Dentro de qué kit va?
            <select name="kit_padre">
              <option value="">Ninguno (kit principal)</option>
<?php foreach ($planos as $id => $nombre): ?>
              <option value="<?= $id ?>"<?= sel($id, viejo('kit_padre')) ?>><?= esc($nombre) ?></option>
<?php endforeach; ?>
            </select>
          </label>
        </div>
        <button type="submit" class="primario">Guardar kit</button>
      </form>

      <h2>Agregar material a un kit</h2>
<?php if (!$planos): ?>
      <p class="ayuda">Primero registra un kit.</p>
<?php else: ?>
      <form method="post" action="kits.php" autocomplete="off">
        <input type="hidden" name="accion" value="material_nuevo">
        <div class="rejilla">
          <label>Kit
            <select name="mat_kit">
              <option value="">Elige un kit</option>
<?php foreach ($planos as $id => $nombre): ?>
              <option value="<?= $id ?>"<?= sel($id, viejo('mat_kit')) ?>><?= esc($nombre) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label>Clave <input name="mat_clave" maxlength="20" value="<?= v('mat_clave') ?>"></label>
          <label>Material <input name="mat_nombre" maxlength="100" value="<?= v('mat_nombre') ?>"></label>
          <label>Cantidad <input name="mat_cantidad" inputmode="numeric" value="<?= v('mat_cantidad', '1') ?>"></label>
          <label>Costo por pieza ($) <input name="mat_costo" inputmode="decimal" value="<?= v('mat_costo', '0') ?>"></label>
        </div>
        <button type="submit" class="primario">Guardar material</button>
      </form>
<?php endif; ?>

      <h2 id="revision">Revisar que un kit se devuelva completo</h2>
<?php if (!$planos): ?>
      <p class="ayuda">Primero registra un kit con su material.</p>
<?php else: ?>
      <form method="get" action="kits.php#revision">
        <label>Kit
          <select name="revisar" onchange="this.form.submit()">
            <option value="">Elige un kit</option>
<?php foreach ($planos as $id => $nombre): ?>
            <option value="<?= $id ?>"<?= sel($id, $idElegido) ?>><?= esc($nombre) ?></option>
<?php endforeach; ?>
          </select>
        </label>
        <button type="submit">Ver piezas</button>
      </form>
<?php endif; ?>

<?php if ($kitElegido): ?>
<?php $piezas = piezas_por_clave($kitElegido); ?>
<?php if (!$piezas): ?>
      <p class="ayuda">Este kit todavía no tiene material.</p>
<?php else: ?>
      <form method="post" action="kits.php#revision">
        <input type="hidden" name="accion" value="revisar">
        <input type="hidden" name="kit_id" value="<?= $kitElegido['id'] ?>">
        <div class="tabla-scroll">
          <table>
            <thead><tr><th>Clave</th><th>Material</th><th>Esperadas</th><th>Devueltas</th></tr></thead>
            <tbody>
<?php $i = 0; foreach ($piezas as $clave => $p): ?>
              <tr>
                <td><code><?= esc($clave) ?></code><input type="hidden" name="clave[]" value="<?= esc($clave) ?>"></td>
                <td><?= esc($p['nombre']) ?></td>
                <td><?= $p['cantidad'] ?></td>
                <td><input type="number" name="devuelto[]" min="0" step="1" value="<?= esc(isset($devueltas[$i]) ? $devueltas[$i] : $p['cantidad']) ?>"></td>
              </tr>
<?php $i++; endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="submit" class="primario">Revisar devolución</button>
      </form>
<?php endif; ?>
<?php endif; ?>

<?php if ($revision): ?>
      <div id="res-revision">
        <p>Kit «<?= esc($revision['kit']['nombre']) ?>»: debe devolverse con <strong><?= contar_piezas($revision['kit']) ?></strong> piezas.</p>
<?php if (!$revision['faltantes']): ?>
        <p class="ok-texto">✔ Kit completo.</p>
<?php else: ?>
        <p>✗ Kit INCOMPLETO. Faltan:</p>
        <ul>
<?php foreach ($revision['faltantes'] as $f): ?>
          <li><code><?= esc($f['clave']) ?></code> <?= esc($f['nombre']) ?>: faltan <?= $f['faltan'] ?> (costo $<?= number_format($f['costo_faltante'], 2) ?>)</li>
<?php endforeach; ?>
        </ul>
        <p>Adeudo sugerido (RN-D06): <strong>$<?= number_format($revision['adeudo'], 2) ?></strong></p>
<?php endif; ?>
      </div>
<?php endif; ?>
    </section>
<?php pie(); ?>
