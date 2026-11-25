<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/Mexico_City');

define('DB_HOST', 'localhost');
define('DB_USUARIO', 'root');
define('DB_PASSWORD', '');
define('DB_NOMBRE', 'proyecto_d_equipo4');

function sql_tablas() {
    $fin = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return [
        'CREATE TABLE IF NOT EXISTS kits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            padre_id INT NULL,
            CONSTRAINT fk_kit_padre FOREIGN KEY (padre_id) REFERENCES kits(id) ON DELETE CASCADE
        ' . $fin,

        'CREATE TABLE IF NOT EXISTS materiales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kit_id INT NOT NULL,
            clave VARCHAR(20) NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            cantidad INT NOT NULL,
            costo DECIMAL(10,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_material_kit FOREIGN KEY (kit_id) REFERENCES kits(id) ON DELETE CASCADE
        ' . $fin,

        'CREATE TABLE IF NOT EXISTS accidentes (
            folio VARCHAR(20) PRIMARY KEY,
            fecha DATE NOT NULL,
            hora CHAR(5) NOT NULL,
            matricula VARCHAR(50) NOT NULL,
            laboratorio VARCHAR(100) NOT NULL,
            descripcion TEXT NOT NULL,
            gravedad TINYINT NOT NULL,
            CHECK (gravedad BETWEEN 1 AND 5)
        ' . $fin,

        'CREATE TABLE IF NOT EXISTS resultados (
            folio_inscripcion VARCHAR(30) PRIMARY KEY,
            matricula VARCHAR(50) NOT NULL,
            practica_id VARCHAR(50) NOT NULL,
            asistio CHAR(1) NOT NULL,
            calificacion DECIMAL(4,2) NOT NULL,
            adeudo_material CHAR(1) NOT NULL,
            monto_adeudo DECIMAL(10,2) NOT NULL DEFAULT 0,
            registrado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CHECK (asistio IN (\'S\', \'N\')),
            CHECK (adeudo_material IN (\'S\', \'N\')),
            CHECK (calificacion BETWEEN 0 AND 10)
        ' . $fin,
    ];
}

function db() {
    static $con = null;
    if ($con !== null) {
        return $con;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $nuevo = new mysqli(DB_HOST, DB_USUARIO, DB_PASSWORD);
    $nuevo->set_charset('utf8mb4');
    $nuevo->query('CREATE DATABASE IF NOT EXISTS `' . DB_NOMBRE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $nuevo->select_db(DB_NOMBRE);
    foreach (sql_tablas() as $sql) {
        $nuevo->query($sql);
    }
    $con = $nuevo;
    return $con;
}

function consultar($sql, $tipos = '', $params = []) {
    $stmt = db()->prepare($sql);
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $filas = [];
    if ($stmt->field_count > 0) {
        $res = $stmt->get_result();
        while ($f = $res->fetch_assoc()) {
            $filas[] = $f;
        }
    }
    $stmt->close();
    return $filas;
}
