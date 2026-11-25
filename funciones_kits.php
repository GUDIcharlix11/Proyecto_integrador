<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/validaciones.php';

function cargar_kits() {
    $nombres = [];
    $porPadre = [];
    foreach (consultar('SELECT id, nombre, padre_id FROM kits ORDER BY id') as $f) {
        $id = (int)$f['id'];
        $nombres[$id] = $f['nombre'];
        $padre = $f['padre_id'] === null ? 0 : (int)$f['padre_id'];
        $porPadre[$padre][] = $id;
    }
    $materiales = [];
    foreach (consultar('SELECT id, kit_id, clave, nombre, cantidad, costo FROM materiales ORDER BY id') as $m) {
        $materiales[(int)$m['kit_id']][] = ['id' => (int)$m['id'], 'clave' => $m['clave'], 'nombre' => $m['nombre'],
                                            'cantidad' => (int)$m['cantidad'], 'costo' => (float)$m['costo']];
    }
    $raices = [];
    $idsRaiz = isset($porPadre[0]) ? $porPadre[0] : [];
    foreach ($idsRaiz as $id) {
        $raices[] = armar_kit($id, $nombres, $porPadre, $materiales);
    }
    return $raices;
}

function armar_kit($id, $nombres, $porPadre, $materiales) {
    $subkits = [];
    $hijos = isset($porPadre[$id]) ? $porPadre[$id] : [];
    foreach ($hijos as $hijo) {
        $subkits[] = armar_kit($hijo, $nombres, $porPadre, $materiales);
    }
    return ['id' => $id, 'nombre' => $nombres[$id],
            'materiales' => isset($materiales[$id]) ? $materiales[$id] : [], 'subkits' => $subkits];
}

function contar_piezas($kit) {
    $propias = 0;
    foreach ($kit['materiales'] as $m) {
        $propias += $m['cantidad'];
    }

    if (count($kit['subkits']) === 0) {
        return $propias;
    }

    $total = $propias;
    foreach ($kit['subkits'] as $sub) {
        $total += contar_piezas($sub);
    }
    return $total;
}

function sumar_pieza(&$total, $clave, $nombre, $cantidad, $costo) {
    if (!isset($total[$clave])) {
        $total[$clave] = ['nombre' => $nombre, 'cantidad' => 0, 'costo' => $costo];
    }
    $total[$clave]['cantidad'] += $cantidad;
}

function piezas_por_clave($kit) {
    $total = [];
    foreach ($kit['materiales'] as $m) {
        sumar_pieza($total, $m['clave'], $m['nombre'], $m['cantidad'], $m['costo']);
    }
    foreach ($kit['subkits'] as $sub) {
        foreach (piezas_por_clave($sub) as $clave => $d) {
            sumar_pieza($total, $clave, $d['nombre'], $d['cantidad'], $d['costo']);
        }
    }
    return $total;
}

function buscar_kit($kits, $id) {
    foreach ($kits as $k) {
        if ($k['id'] === $id) {
            return $k;
        }
        $dentro = buscar_kit($k['subkits'], $id);
        if ($dentro !== null) {
            return $dentro;
        }
    }
    return null;
}

function kits_planos($kits, $nivel = 0) {
    $lista = [];
    foreach ($kits as $k) {
        $lista[$k['id']] = str_repeat('— ', $nivel) . $k['nombre'];
        foreach (kits_planos($k['subkits'], $nivel + 1) as $id => $nombre) {
            $lista[$id] = $nombre;
        }
    }
    return $lista;
}

function revisar_devolucion($kit, $devuelto) {
    $faltantes = [];
    foreach (piezas_por_clave($kit) as $clave => $d) {
        $recibidas = isset($devuelto[$clave]) ? (int)$devuelto[$clave] : 0;
        if ($recibidas < $d['cantidad']) {
            $faltan = $d['cantidad'] - $recibidas;
            $faltantes[] = ['clave' => (string)$clave, 'nombre' => $d['nombre'], 'esperadas' => $d['cantidad'],
                            'devueltas' => $recibidas, 'faltan' => $faltan,
                            'costo_faltante' => round($faltan * $d['costo'], 2)];
        }
    }
    return $faltantes;
}

function kit_crear($nombre, $padre_id) {
    $nombre = trim((string)$nombre);
    if ($nombre === '') {
        throw new InvalidArgumentException('El nombre del kit no puede ir vacío.');
    }
    if (largo($nombre) > 100) {
        throw new InvalidArgumentException('El nombre del kit es muy largo (máximo 100 caracteres).');
    }
    $padre = null;
    $txt = trim((string)$padre_id);
    if ($txt !== '') {
        if (!preg_match('/^\d+$/', $txt) || !consultar('SELECT 1 FROM kits WHERE id = ?', 'i', [(int)$txt])) {
            throw new InvalidArgumentException('El kit donde quieres meterlo no existe.');
        }
        $padre = (int)$txt;
    }
    consultar('INSERT INTO kits (nombre, padre_id) VALUES (?, ?)', 'si', [$nombre, $padre]);
}

function material_agregar($kit_id, $clave, $nombre, $cantidad, $costo) {
    $kit = trim((string)$kit_id);
    if (!preg_match('/^\d+$/', $kit) || !consultar('SELECT 1 FROM kits WHERE id = ?', 'i', [(int)$kit])) {
        throw new InvalidArgumentException('Elige el kit al que pertenece el material.');
    }
    $clave = trim((string)$clave);
    $nombre = trim((string)$nombre);
    $cantidad = trim((string)$cantidad);
    $errores = [];
    if ($clave === '' || largo($clave) > 20) {
        $errores[] = 'La clave es obligatoria (máximo 20 caracteres).';
    }
    if ($nombre === '' || largo($nombre) > 100) {
        $errores[] = 'El nombre del material es obligatorio (máximo 100 caracteres).';
    }
    if (!preg_match('/^\d{1,6}$/', $cantidad) || (int)$cantidad < 1) {
        $errores[] = 'La cantidad debe ser un entero mayor a 0.';
    }
    if (!es_numero($costo) || (float)$costo < 0 || (float)$costo > 1000000) {
        $errores[] = 'El costo debe ser un número entre 0 y 1,000,000.';
    }
    if (!$errores && consultar('SELECT 1 FROM materiales WHERE kit_id = ? AND clave = ?', 'is', [(int)$kit, $clave])) {
        $errores[] = "Ese kit ya tiene un material con la clave $clave.";
    }
    if ($errores) {
        throw new InvalidArgumentException(implode(' ', $errores));
    }
    consultar('INSERT INTO materiales (kit_id, clave, nombre, cantidad, costo) VALUES (?, ?, ?, ?, ?)', 'issid',
              [(int)$kit, $clave, $nombre, (int)$cantidad, (float)$costo]);
}

function kit_eliminar($id) {
    $id = trim((string)$id);
    if (!preg_match('/^\d+$/', $id) || !consultar('SELECT 1 FROM kits WHERE id = ?', 'i', [(int)$id])) {
        throw new InvalidArgumentException('Ese kit no existe.');
    }
    consultar('DELETE FROM kits WHERE id = ?', 'i', [(int)$id]);
}

function material_eliminar($id) {
    $id = trim((string)$id);
    if (!preg_match('/^\d+$/', $id) || !consultar('SELECT 1 FROM materiales WHERE id = ?', 'i', [(int)$id])) {
        throw new InvalidArgumentException('Ese material no existe.');
    }
    consultar('DELETE FROM materiales WHERE id = ?', 'i', [(int)$id]);
}
