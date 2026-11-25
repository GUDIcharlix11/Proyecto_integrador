<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/validaciones.php';

const COLUMNAS_RESULTADO = 'folio_inscripcion, matricula, practica_id, asistio, calificacion, adeudo_material, monto_adeudo';

function resultados_todos() {
    $filas = consultar('SELECT ' . COLUMNAS_RESULTADO . ' FROM resultados ORDER BY registrado, folio_inscripcion');
    foreach ($filas as $i => $f) {
        $filas[$i]['calificacion'] = (float)$f['calificacion'];
        $filas[$i]['monto_adeudo'] = (float)$f['monto_adeudo'];
    }
    return $filas;
}

function resultado_obtener($folio) {
    $filas = consultar('SELECT ' . COLUMNAS_RESULTADO . ' FROM resultados WHERE folio_inscripcion = ?', 's', [(string)$folio]);
    if (!$filas) {
        throw new InvalidArgumentException("No existe el resultado $folio.");
    }
    $filas[0]['calificacion'] = (float)$filas[0]['calificacion'];
    $filas[0]['monto_adeudo'] = (float)$filas[0]['monto_adeudo'];
    return $filas[0];
}

function normalizar_resultado($r) {
    if (!is_array($r)) {
        return $r;
    }
    foreach (['asistio', 'adeudo_material'] as $campo) {
        if (isset($r[$campo]) && is_string($r[$campo])) {
            $r[$campo] = strtoupper(trim($r[$campo]));
        }
    }
    return $r;
}

function resultado_guardar($r) {
    $r = normalizar_resultado($r);
    $errores = validar_resultado($r);
    if ($errores) {
        throw new InvalidArgumentException(implode(' ', $errores));
    }
    $folio = trim($r['folio_inscripcion']);
    $valores = [trim($r['matricula']), trim($r['practica_id']), $r['asistio'],
                (float)$r['calificacion'], $r['adeudo_material'], (float)$r['monto_adeudo']];
    $existe = consultar('SELECT 1 FROM resultados WHERE folio_inscripcion = ?', 's', [$folio]);
    if ($existe) {
        consultar('UPDATE resultados SET matricula = ?, practica_id = ?, asistio = ?, calificacion = ?, ' .
                  'adeudo_material = ?, monto_adeudo = ? WHERE folio_inscripcion = ?',
                  'sssdsds', array_merge($valores, [$folio]));
        return 'actualizado';
    }
    consultar('INSERT INTO resultados (' . COLUMNAS_RESULTADO . ') VALUES (?, ?, ?, ?, ?, ?, ?)',
              'ssssdsd', array_merge([$folio], $valores));
    return 'guardado';
}

function resultado_eliminar($folio) {
    if (!consultar('SELECT 1 FROM resultados WHERE folio_inscripcion = ?', 's', [(string)$folio])) {
        throw new InvalidArgumentException("No existe el resultado $folio.");
    }
    consultar('DELETE FROM resultados WHERE folio_inscripcion = ?', 's', [(string)$folio]);
}
