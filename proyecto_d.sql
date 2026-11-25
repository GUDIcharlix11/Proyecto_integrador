SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS proyecto_d_equipo4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proyecto_d_equipo4;

CREATE TABLE IF NOT EXISTS kits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  padre_id INT NULL,
  CONSTRAINT fk_kit_padre FOREIGN KEY (padre_id) REFERENCES kits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS materiales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kit_id INT NOT NULL,
  clave VARCHAR(20) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  cantidad INT NOT NULL,
  costo DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_material_kit FOREIGN KEY (kit_id) REFERENCES kits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accidentes (
  folio VARCHAR(20) PRIMARY KEY,
  fecha DATE NOT NULL,
  hora CHAR(5) NOT NULL,
  matricula VARCHAR(50) NOT NULL,
  laboratorio VARCHAR(100) NOT NULL,
  descripcion TEXT NOT NULL,
  gravedad TINYINT NOT NULL,
  CHECK (gravedad BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resultados (
  folio_inscripcion VARCHAR(30) PRIMARY KEY,
  matricula VARCHAR(50) NOT NULL,
  practica_id VARCHAR(50) NOT NULL,
  asistio CHAR(1) NOT NULL,
  calificacion DECIMAL(4,2) NOT NULL,
  adeudo_material CHAR(1) NOT NULL,
  monto_adeudo DECIMAL(10,2) NOT NULL DEFAULT 0,
  registrado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (asistio IN ('S','N')),
  CHECK (adeudo_material IN ('S','N')),
  CHECK (calificacion BETWEEN 0 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO kits (id, nombre, padre_id) VALUES
  (1, 'Kit de Química General', NULL),
  (2, 'Kit de Seguridad', 1),
  (3, 'Botiquín básico', 2);

INSERT IGNORE INTO materiales (id, kit_id, clave, nombre, cantidad, costo) VALUES
  (1, 1, 'QUI-001', 'Matraz Erlenmeyer 250 ml', 4, 85.00),
  (2, 1, 'QUI-002', 'Probeta 100 ml', 2, 60.00),
  (3, 2, 'SEG-001', 'Goggles de seguridad', 4, 45.00),
  (4, 2, 'SEG-002', 'Guantes de nitrilo (par)', 4, 20.00),
  (5, 3, 'BOT-001', 'Gasas estériles', 10, 3.50),
  (6, 3, 'BOT-002', 'Venda elástica', 2, 18.00);
