-- Crear tabla lugares_entrega
CREATE TABLE IF NOT EXISTS `lugares_entrega` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar datos iniciales
INSERT INTO `lugares_entrega` (`nombre`) VALUES
('COBAN'),
('GUATEMALA');