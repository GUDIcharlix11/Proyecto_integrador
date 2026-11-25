<?php
require_once __DIR__ . '/plantilla.php';
require_once __DIR__ . '/funciones_resultados.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = post('accion');
    $destino = 'resultados.php';
    try {
        if ($accion === 'guardar') {
            $asistio = strtoupper(trim((string)post('asistio', 'S')));
            $adeudo = strtoupper(trim((string)post('adeudo_material', 'N')));
            $registro = [
                'folio_inscripcion' => trim((string)post('folio_inscripcion')),
                'matricula'         => trim((string)post('matricula')),
                'practica_id'       => trim((string)post('practica_id')),
                'asistio'           => $asistio,
                'calificacion'      => $asistio === 'N' ? 0 : trim((string)post('calificacion')),
                'adeudo_material'   => $adeudo,
                'monto_adeudo'      => $adeudo === 'S' ? trim((string)post('monto_adeudo', '0')) : 0,
            ];
            $estado = resultado_guardar($registro);
            avisar('ok', 'Resultado ' . $registro['folio_inscripcion'] . ' ' . $estado . '.');

        } elseif ($accion === 'lista') {
            $texto = trim((string)post('json_lista'));
            if ($texto === '') {
                throw new InvalidArgumentException('Pega primero la lista de resultados en formato JSON.');
            }
            $lista = json_decode($texto, true);
            if ($lista === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Ese JSON no es válido: ' . json_last_error_msg());
            }
            if (is_array($lista) && isset($lista['datos'])) {
                $lista = $lista['datos'];
            }
            if (!es_lista($lista)) {
                throw new InvalidArgumentException('Debe ser una lista de resultados o {"datos": [...]}.');
            }
            $guardados = 0;
            $mensajes = [];
            foreach ($lista as $i => $r) {
                try {
                    resultado_guardar($r);
                    $guardados++;
                } catch (InvalidArgumentException $e) {
                    $mensajes[] = 'Resultado #' . ($i + 1) . ': ' . $e->getMessage();
                }
            }
            avisar(count($mensajes) ? 'error' : 'ok',
                   "Resultados: $guardados guardados, " . count($mensajes) . ' rechazados.' .
                   ($mensajes ? ' ' . implode(' | ', $mensajes) : ''));

        } elseif ($accion === 'eliminar') {
            resultado_eliminar(post('folio_inscripcion'));
            avisar('ok', 'Resultado ' . post('folio_inscripcion') . ' eliminado.');
        }
    } catch (InvalidArgumentException $e) {
        avisar('error', $e->getMessage());
        guardar_viejo($_POST);
    } catch (mysqli_sql_exception $e) {
        avisar('error', 'Error de base de datos: ' . $e->getMessage());
    }
    redirigir($destino);
}

$editando = null;
if (isset($_GET['corregir'])) {
    try {
        $editando = resultado_obtener((string)$_GET['corregir']);
    } catch (InvalidArgumentException $e) {
        $AVISO = ['error', $e->getMessage()];
    }
}
$d = ['folio_inscripcion' => '', 'matricula' => '', 'practica_id' => '', 'asistio' => 'S',
      'calificacion' => '', 'adeudo_material' => 'N', 'monto_adeudo' => '0'];
if ($editando) {
    $d = array_merge($d, $editando);
    $d['calificacion'] = num($d['calificacion']);
    $d['monto_adeudo'] = num($d['monto_adeudo']);
}
if ($VIEJO) {
    $d = array_merge($d, array_intersect_key($VIEJO, $d));
}
$modoEditar = $editando !== null;
$lista = resultados_todos();

encabezado('Resultados de práctica', 'resultados');
?>
    <section class="panel">
      <h2 id="formulario"><?= $modoEditar ? 'Corregir el resultado ' . esc($d['folio_inscripcion']) : 'Capturar un resultado' ?></h2>
      <form method="post" action="resultados.php" autocomplete="off">
        <input type="hidden" name="accion" value="guardar">
        <div class="rejilla">
          <label>folio_inscripcion <input name="folio_inscripcion" placeholder="INS-0001" maxlength="30" value="<?= esc($d['folio_inscripcion']) ?>"<?= $modoEditar ? ' readonly' : '' ?>></label>
          <label>Matrícula <input name="matricula" maxlength="50" value="<?= esc($d['matricula']) ?>"></label>
          <label>practica_id <input name="practica_id" placeholder="PR-QUIM-01" maxlength="50" value="<?= esc($d['practica_id']) ?>"></label>
          <label>¿Asistió?
            <select name="asistio">
              <option<?= sel('S', $d['asistio']) ?>>S</option>
              <option<?= sel('N', $d['asistio']) ?>>N</option>
            </select>
          </label>
          <label>Calificación (0 a 10) <input name="calificacion" inputmode="decimal" value="<?= esc($d['calificacion']) ?>"></label>
          <label>¿Adeudo de material?
            <select name="adeudo_material">
              <option<?= sel('N', $d['adeudo_material']) ?>>N</option>
              <option<?= sel('S', $d['adeudo_material']) ?>>S</option>
            </select>
          </label>
          <label>Monto del adeudo <input name="monto_adeudo" inputmode="decimal" value="<?= esc($d['monto_adeudo']) ?>"></label>
        </div>
        <p class="ayuda">Si ya existe un resultado con el mismo folio_inscripcion, se actualiza. Si no asistió, la calificación queda en 0; sin adeudo, el monto queda en 0.</p>
        <button type="submit" class="primario">Guardar resultado</button>
<?php if ($modoEditar): ?>
        <a class="boton" href="resultados.php">Cancelar edición</a>
<?php endif; ?>
      </form>

      <h2>Resultados guardados</h2>
      <div class="tabla-scroll">
        <table>
          <thead><tr><th>Folio</th><th>Matrícula</th><th>Práctica</th><th>Asistió</th><th>Calif.</th><th>Adeudo</th><th>Monto</th><th></th></tr></thead>
          <tbody>
<?php if (!$lista): ?>
            <tr><td colspan="8" class="vacio">No hay resultados guardados.</td></tr>
<?php endif; ?>
<?php foreach ($lista as $r): ?>
            <tr>
              <td><code><?= esc($r['folio_inscripcion']) ?></code></td>
              <td><?= esc($r['matricula']) ?></td>
              <td><?= esc($r['practica_id']) ?></td>
              <td><?= esc($r['asistio']) ?></td>
              <td><?= esc(num($r['calificacion'])) ?></td>
              <td><?= esc($r['adeudo_material']) ?></td>
              <td>$<?= number_format($r['monto_adeudo'], 2) ?></td>
              <td>
                <a class="boton" href="resultados.php?corregir=<?= urlencode($r['folio_inscripcion']) ?>#formulario">Corregir</a>
                <form method="post" action="resultados.php" class="en-linea" onsubmit="return confirm('¿Seguro que quieres borrar este resultado?')">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="folio_inscripcion" value="<?= esc($r['folio_inscripcion']) ?>">
                  <button type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2>Cargar varios resultados (JSON)</h2>
      <p class="ayuda">Pega una lista o <code>{"datos": [...]}</code> con los campos de arriba.</p>
      <form method="post" action="resultados.php">
        <input type="hidden" name="accion" value="lista">
        <textarea id="json-res" name="json_lista" rows="8" spellcheck="false"
          placeholder='{"datos": [{"folio_inscripcion": "INS-0001", "matricula": "2526160001", "practica_id": "PR-QUIM-01", "asistio": "S", "calificacion": 9.5, "adeudo_material": "N", "monto_adeudo": 0}]}'><?= v('json_lista') ?></textarea>
        <button type="submit" class="primario">Guardar lista de resultados</button>
      </form>
    </section>
<?php pie(); ?>
