<?php
const CAMPOS_ACCIDENTE = ['folio', 'fecha', 'hora', 'matricula', 'laboratorio', 'descripcion', 'gravedad'];
const CAMPOS_RESULTADO = ['folio_inscripcion', 'matricula', 'practica_id', 'asistio',
                          'calificacion', 'adeudo_material', 'monto_adeudo'];

function texto($v) {
    return is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
}

function largo($t) {
    return function_exists('mb_strlen') ? mb_strlen($t, 'UTF-8') : strlen($t);
}

function es_numero($x) {
    if (is_int($x) || is_float($x)) {
        return true;
    }
    return is_string($x) && is_numeric(trim($x));
}

function es_lista($a) {
    return is_array($a) && ($a === [] || array_keys($a) === range(0, count($a) - 1));
}

function fecha_real($txt, $formato) {
    $d = DateTime::createFromFormat($formato, $txt);
    return $d !== false && $d->format($formato) === $txt;
}

function error_fecha($fecha) {
    if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !fecha_real($fecha, 'Y-m-d')) {
        return "Fecha inválida '" . texto($fecha) . "': usa AAAA-MM-DD (por ejemplo 2026-11-23).";
    }
    return null;
}

function error_hora($hora) {
    if (!is_string($hora) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
        return "Hora inválida '" . texto($hora) . "': usa HH:MM en 24 horas (por ejemplo 09:35).";
    }
    return null;
}

function error_folio($folio) {
    $ok = is_string($folio)
        && preg_match('/^ACC-(\d{8})-(\d{3})$/', $folio, $m)
        && fecha_real($m[1], 'Ymd')
        && $m[2] !== '000';
    if (!$ok) {
        return "Folio inválido '" . texto($folio) . "': debe ser ACC-AAAAMMDD-### (ej. ACC-20261123-001) (RN-D08).";
    }
    return null;
}

function error_gravedad($g) {
    $ok = (is_int($g) && $g >= 1 && $g <= 5)
        || (is_string($g) && preg_match('/^[1-5]$/', trim($g)) === 1);
    if (!$ok) {
        return "Gravedad inválida '" . texto($g) . "': debe ser un entero de 1 a 5 (RN-D07).";
    }
    return null;
}

function sin_nulos($errores) {
    return array_values(array_filter($errores, function ($e) { return $e !== null; }));
}

function validar_accidente($reg) {
    if (!is_array($reg)) {
        return ['El accidente debe ser un objeto con sus campos.'];
    }
    $faltan = [];
    foreach (CAMPOS_ACCIDENTE as $c) {
        if (!array_key_exists($c, $reg)) {
            $faltan[] = $c;
        }
    }
    if ($faltan) {
        return ['Faltan campos del accidente: ' . implode(', ', $faltan) . '.'];
    }
    $errores = [error_folio($reg['folio']), error_fecha($reg['fecha']),
                error_hora($reg['hora']), error_gravedad($reg['gravedad'])];
    $limites = ['matricula' => 50, 'laboratorio' => 100, 'descripcion' => 60000];
    foreach ($limites as $campo => $max) {
        if (!is_string($reg[$campo]) || trim($reg[$campo]) === '') {
            $errores[] = "El campo '$campo' no puede ir vacío.";
        } elseif (largo($reg[$campo]) > $max) {
            $errores[] = "El campo '$campo' es muy largo (máximo $max caracteres).";
        }
    }
    return sin_nulos($errores);
}

function validar_resultado($reg) {
    if (!is_array($reg)) {
        return ['El resultado debe ser un objeto con sus campos.'];
    }
    $faltan = [];
    foreach (CAMPOS_RESULTADO as $c) {
        if (!array_key_exists($c, $reg)) {
            $faltan[] = $c;
        }
    }
    if ($faltan) {
        return ['Faltan campos del resultado: ' . implode(', ', $faltan) . '.'];
    }
    $errores = [];
    $limites = ['folio_inscripcion' => 30, 'matricula' => 50, 'practica_id' => 50];
    foreach ($limites as $campo => $max) {
        if (!is_string($reg[$campo]) || trim($reg[$campo]) === '') {
            $errores[] = "El campo '$campo' no puede ir vacío.";
        } elseif (largo($reg[$campo]) > $max) {
            $errores[] = "El campo '$campo' es muy largo (máximo $max caracteres).";
        }
    }
    foreach (['asistio', 'adeudo_material'] as $campo) {
        if (!in_array($reg[$campo], ['S', 'N'], true)) {
            $errores[] = "'$campo' debe ser \"S\" o \"N\".";
        }
    }
    $calif = es_numero($reg['calificacion']) ? (float)$reg['calificacion'] : null;
    if ($calif === null || $calif < 0 || $calif > 10) {
        $errores[] = 'La calificación debe ser un número de 0 a 10.';
    } elseif ($reg['asistio'] === 'N' && $calif != 0) {
        $errores[] = 'Si el alumno no asistió, la calificación es 0 (RN-D03).';
    }
    $monto = es_numero($reg['monto_adeudo']) ? (float)$reg['monto_adeudo'] : null;
    if ($monto === null || $monto < 0 || $monto > 99999999) {
        $errores[] = 'monto_adeudo debe ser un número entre 0 y 99,999,999.';
    } elseif ($reg['adeudo_material'] === 'N' && $monto != 0) {
        $errores[] = 'Sin adeudo ("N") el monto_adeudo debe ser 0 (RN-D06).';
    }
    return $errores;
}
