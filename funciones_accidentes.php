<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/validaciones.php';

const CAMPOS_EDITABLES = ['hora', 'matricula', 'laboratorio', 'descripcion', 'gravedad'];
const COLUMNAS_ACCIDENTE = 'folio, fecha, hora, matricula, laboratorio, descripcion, gravedad';

function mayus($t) {
    return function_exists('mb_strtoupper') ? mb_strtoupper($t, 'UTF-8') : strtoupper($t);
}

function accidentes_todos() {
    $filas = consultar('SELECT ' . COLUMNAS_ACCIDENTE . ' FROM accidentes ORDER BY folio');
    foreach ($filas as $i => $f) {
        $filas[$i]['gravedad'] = (int)$f['gravedad'];
    }
    return $filas;
}

function accidente_obtener($folio) {
    $filas = consultar('SELECT ' . COLUMNAS_ACCIDENTE . ' FROM accidentes WHERE folio = ?', 's', [(string)$folio]);
    if (!$filas) {
        throw new InvalidArgumentException("No existe el accidente $folio.");
    }
    $filas[0]['gravedad'] = (int)$filas[0]['gravedad'];
    return $filas[0];
}

function siguiente_folio($fecha) {
    $prefijo = 'ACC-' . str_replace('-', '', $fecha) . '-';
    $usados = [];
    $locales = consultar('SELECT folio FROM accidentes WHERE folio LIKE ?', 's', [$prefijo . '%']);
    foreach ($locales as $f) {
        $usados[$f['folio']] = true;
    }
    $max = 0;
    foreach (array_keys($usados) as $f) {
        $max = max($max, (int)substr((string)$f, -3));
    }
    $n = $max + 1;
    if ($n > 999) {
        throw new InvalidArgumentException("Ya se usaron los 999 folios del $fecha.");
    }
    return $prefijo . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

function accidente_reportar($fecha, $hora, $matricula, $laboratorio, $descripcion, $gravedad) {
    $fecha = is_string($fecha) ? trim($fecha) : $fecha;
    $e = error_fecha($fecha);
    if ($e !== null) {
        throw new InvalidArgumentException($e);
    }
    $r = [
        'folio'       => siguiente_folio($fecha),
        'fecha'       => $fecha,
        'hora'        => is_string($hora) ? trim($hora) : $hora,
        'matricula'   => trim((string)$matricula),
        'laboratorio' => mayus(trim((string)$laboratorio)),
        'descripcion' => trim((string)$descripcion),
        'gravedad'    => is_string($gravedad) ? trim($gravedad) : $gravedad,
    ];
    $errores = validar_accidente($r);
    if ($errores) {
        throw new InvalidArgumentException(implode(' ', $errores));
    }
    $r['gravedad'] = (int)$r['gravedad'];
    consultar('INSERT INTO accidentes (' . COLUMNAS_ACCIDENTE . ') VALUES (?, ?, ?, ?, ?, ?, ?)', 'ssssssi',
              [$r['folio'], $r['fecha'], $r['hora'], $r['matricula'], $r['laboratorio'], $r['descripcion'], $r['gravedad']]);
    return $r;
}

function accidente_modificar($folio, $cambios) {
    $invalidos = array_diff(array_keys($cambios), CAMPOS_EDITABLES);
    if ($invalidos) {
        throw new InvalidArgumentException('No se puede cambiar: ' . implode(', ', $invalidos) . '.');
    }
    $r = accidente_obtener($folio);
    foreach ($cambios as $campo => $valor) {
        if ($valor !== null) {
            $r[$campo] = is_string($valor) ? trim($valor) : $valor;
        }
    }
    $r['laboratorio'] = mayus(trim((string)$r['laboratorio']));
    $errores = validar_accidente($r);
    if ($errores) {
        throw new InvalidArgumentException(implode(' ', $errores));
    }
    $r['gravedad'] = (int)$r['gravedad'];
    consultar('UPDATE accidentes SET hora = ?, matricula = ?, laboratorio = ?, descripcion = ?, gravedad = ? WHERE folio = ?',
              'ssssis', [$r['hora'], $r['matricula'], $r['laboratorio'], $r['descripcion'], $r['gravedad'], $r['folio']]);
    return $r;
}

function accidente_eliminar($folio) {
    accidente_obtener($folio);
    consultar('DELETE FROM accidentes WHERE folio = ?', 's', [(string)$folio]);
}
