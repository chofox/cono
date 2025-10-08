-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 10.123.0.165:3306
-- Tiempo de generación: 26-09-2025 a las 15:52:41
-- Versión del servidor: 8.4.5
-- Versión de PHP: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `chofoxr_informatica`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`chofoxr_informatica`@`%` PROCEDURE `LimpiarSesionesExpiradas` ()   BEGIN
    DELETE FROM sesiones_usuario
    WHERE fecha_expiracion < NOW() OR activa = 0;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_insumos`
--

CREATE TABLE `categorias_insumos` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `categorias_insumos`
--

INSERT INTO `categorias_insumos` (`id`, `nombre`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(1, 'Hardware', 'Componentes físicos de computadora', 1, '2025-09-24 22:02:52'),
(2, 'Periféricos', 'Dispositivos externos de entrada y salida', 1, '2025-09-24 22:02:52'),
(3, 'Accesorios', 'Cables, adaptadores y otros accesorios', 1, '2025-09-24 22:02:52'),
(4, 'Consumibles', 'Materiales de consumo', 1, '2025-09-24 22:02:52'),
(5, 'Software', 'Licencias y programas informáticos', 1, '2025-09-24 22:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_sistema`
--

CREATE TABLE `configuracion_sistema` (
  `id` int NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `valor` text COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `configuracion_sistema`
--

INSERT INTO `configuracion_sistema` (`id`, `clave`, `valor`, `descripcion`, `fecha_actualizacion`) VALUES
(1, 'nombre_institucion', 'Dirección Departamental de Redes Integradas de Servicios de Salud de Alta Verapaz', 'Nombre completo de la institución', '2025-09-24 22:02:52'),
(2, 'unidad_responsable', 'Unidad de Informática', 'Unidad responsable del sistema', '2025-09-24 22:02:52'),
(3, 'año_actual', '2025', 'Año actual para numeración de conocimientos', '2025-09-24 22:02:52'),
(4, 'ultimo_numero_conocimiento', '0', 'Último número de conocimiento generado', '2025-09-24 22:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conocimientos`
--

CREATE TABLE `conocimientos` (
  `id` int NOT NULL,
  `numero_conocimiento` varchar(20) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `año` int NOT NULL,
  `numero_secuencial` int NOT NULL,
  `fecha_entrega` date NOT NULL,
  `lugar_entrega` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `entregante_id` int NOT NULL,
  `receptor_id` int NOT NULL,
  `observaciones_generales` text COLLATE utf8mb4_spanish2_ci,
  `estado` enum('borrador','finalizado','anulado') COLLATE utf8mb4_spanish2_ci DEFAULT 'borrador',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creado_por` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Disparadores `conocimientos`
--
DELIMITER $$
CREATE TRIGGER `generar_numero_conocimiento` BEFORE INSERT ON `conocimientos` FOR EACH ROW BEGIN
    DECLARE ultimo_numero INT DEFAULT 0;
    DECLARE anio_actual INT;
    SET anio_actual = YEAR(NEW.fecha_entrega);
    SELECT COALESCE(MAX(numero_secuencial),0) INTO ultimo_numero
    FROM conocimientos WHERE año = anio_actual;
    SET ultimo_numero = ultimo_numero + 1;
    SET NEW.año = anio_actual;
    SET NEW.numero_secuencial = ultimo_numero;
    SET NEW.numero_conocimiento = CONCAT(ultimo_numero,'-',anio_actual);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_conocimientos`
--

CREATE TABLE `detalle_conocimientos` (
  `id` int NOT NULL,
  `conocimiento_id` int NOT NULL,
  `insumo_id` int NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `distritos`
--

CREATE TABLE `distritos` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `distritos`
--

INSERT INTO `distritos` (`id`, `nombre`, `activo`, `fecha_creacion`) VALUES
(1, 'Chamelco', 1, '2025-09-24 22:02:52'),
(2, 'Cobán', 1, '2025-09-24 22:02:52'),
(3, 'San Pedro Carchá', 1, '2025-09-24 22:02:52'),
(4, 'Lanquín', 1, '2025-09-24 22:02:52'),
(5, 'Cahabón', 1, '2025-09-24 22:02:52'),
(6, 'Chisec', 1, '2025-09-24 22:02:52'),
(7, 'Raxruhá', 1, '2025-09-24 22:02:52'),
(8, 'Fray Bartolomé de las Casas', 1, '2025-09-24 22:02:52'),
(9, 'San Juan Chamelco', 1, '2025-09-24 22:02:52'),
(10, 'Tamahú', 1, '2025-09-24 22:02:52'),
(11, 'Tucurú', 1, '2025-09-24 22:02:52'),
(12, 'Panzós', 1, '2025-09-24 22:02:52'),
(13, 'Senahú', 1, '2025-09-24 22:02:52'),
(14, 'San Cristóbal Verapaz', 1, '2025-09-24 22:02:52'),
(15, 'Santa Cruz Verapaz', 1, '2025-09-24 22:02:52'),
(16, 'Tactic', 1, '2025-09-24 22:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos`
--

CREATE TABLE `insumos` (
  `id` int NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `categoria_id` int DEFAULT NULL,
  `unidad_medida` varchar(20) COLLATE utf8mb4_spanish2_ci NOT NULL DEFAULT 'Unidad',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `insumos`
--

INSERT INTO `insumos` (`id`, `codigo`, `nombre`, `descripcion`, `categoria_id`, `unidad_medida`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'HD001', 'Disco Duro 1TB', 'Disco duro interno SATA 1TB', 1, 'Unidad', 1, '2025-09-24 22:02:52', '2025-09-24 22:02:52'),
(2, 'RAM001', 'Memoria RAM 8GB DDR4', 'Memoria RAM 8GB DDR4 2400MHz', 1, 'Unidad', 1, '2025-09-24 22:02:52', '2025-09-24 22:02:52'),
(3, 'KB001', 'Teclado USB', 'Teclado estándar USB', 2, 'Unidad', 1, '2025-09-24 22:02:52', '2025-09-24 22:02:52'),
(4, 'MS001', 'Mouse USB', 'Mouse óptico USB', 2, 'Unidad', 1, '2025-09-24 22:02:52', '2025-09-24 22:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_actividades`
--

CREATE TABLE `log_actividades` (
  `id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `accion` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `detalles` text COLLATE utf8mb4_spanish2_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_spanish2_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `log_actividades`
--

INSERT INTO `log_actividades` (`id`, `usuario_id`, `accion`, `detalles`, `ip_address`, `user_agent`, `fecha_creacion`) VALUES
(1, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:19:56'),
(2, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:20:15'),
(3, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:20:26'),
(4, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:20:46'),
(5, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:21:29'),
(6, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:22:56'),
(7, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:23:36'),
(8, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:23:54'),
(9, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:23:57'),
(10, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:27:19'),
(11, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:27:54'),
(12, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:28:59'),
(13, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:29:02'),
(14, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:29:04'),
(15, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:29:52'),
(16, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:29:55'),
(17, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:29:58'),
(18, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:30:01'),
(19, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:30:04'),
(20, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:35:32'),
(21, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:35:35'),
(22, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:36:45'),
(23, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:37:45'),
(24, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:38:34'),
(25, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:38:39'),
(26, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:43:01'),
(27, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:45:21'),
(28, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:47:24'),
(29, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:47:29'),
(30, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:47:50'),
(31, 2, 'logout', 'Cierre de sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:51:59'),
(32, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:52:04'),
(33, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:54:22'),
(34, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-24 22:54:37'),
(35, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 00:30:22'),
(36, 2, 'login_success', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 13:57:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `receptores`
--

CREATE TABLE `receptores` (
  `id` int NOT NULL,
  `nombre_completo` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `puesto` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `distrito_id` int NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `receptores`
--

INSERT INTO `receptores` (`id`, `nombre_completo`, `puesto`, `distrito_id`, `telefono`, `email`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Juan Pérez', 'Jefe de Almacén', 1, '5555-1111', 'juan.perez@example.com', 1, '2025-09-24 22:02:52', '2025-09-24 22:02:52'),
(2, 'María López', 'Encargada de Inventario', 2, '5555-2222', 'maria.lopez@example.com', 1, '2025-09-24 22:02:52', '2025-09-24 22:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(1, 'Administrador', 'Acceso completo al sistema', 1, '2025-09-24 22:02:52'),
(2, 'Técnico', 'Registro de entregas, generación de conocimientos', 1, '2025-09-24 22:02:52'),
(3, 'RRHH', 'Consulta de conocimientos y reportes', 1, '2025-09-24 22:02:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_usuario`
--

CREATE TABLE `sesiones_usuario` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `token_sesion` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `fecha_inicio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` timestamp NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_spanish2_ci,
  `activa` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `sesiones_usuario`
--

INSERT INTO `sesiones_usuario` (`id`, `usuario_id`, `token_sesion`, `fecha_inicio`, `fecha_expiracion`, `ip_address`, `user_agent`, `activa`) VALUES
(35, 2, '9698f8003881b100d87d6da3d27a1cf42a7bb419620a118e3beb8a61522dcdd9', '2025-09-25 19:57:37', '2025-09-25 23:27:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre_completo` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `usuario` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `puesto` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `distrito_id` int DEFAULT NULL,
  `rol_id` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_completo`, `usuario`, `password_hash`, `puesto`, `distrito_id`, `rol_id`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Administrador del Sistema', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador de Sistema', 1, 1, 1, '2025-09-24 22:02:52', '2025-09-24 22:02:52'),
(2, 'Rodolfo Garcia', 'chofox', '$2y$10$r2z/zRSNiKhGHAJiqz728uBUPr7bT/ZxE5MFelrC5ZPvRcKz8AYBy', 'Administrador de Sistema', 1, 1, 1, '2025-09-24 22:02:52', '2025-09-24 22:05:10');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias_insumos`
--
ALTER TABLE `categorias_insumos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `conocimientos`
--
ALTER TABLE `conocimientos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_conocimiento` (`numero_conocimiento`),
  ADD KEY `creado_por` (`creado_por`),
  ADD KEY `idx_numero_año` (`numero_secuencial`,`año`),
  ADD KEY `idx_fecha_entrega` (`fecha_entrega`),
  ADD KEY `idx_entregante` (`entregante_id`),
  ADD KEY `idx_receptor` (`receptor_id`),
  ADD KEY `idx_conocimientos_estado` (`estado`),
  ADD KEY `idx_conocimientos_fecha_creacion` (`fecha_creacion`);

--
-- Indices de la tabla `detalle_conocimientos`
--
ALTER TABLE `detalle_conocimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conocimiento_id` (`conocimiento_id`),
  ADD KEY `insumo_id` (`insumo_id`);

--
-- Indices de la tabla `distritos`
--
ALTER TABLE `distritos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `idx_insumos_activo` (`activo`);

--
-- Indices de la tabla `log_actividades`
--
ALTER TABLE `log_actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `receptores`
--
ALTER TABLE `receptores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `distrito_id` (`distrito_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `sesiones_usuario`
--
ALTER TABLE `sesiones_usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_sesion` (`token_sesion`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_sesiones_token` (`token_sesion`),
  ADD KEY `idx_sesiones_expiracion` (`fecha_expiracion`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `distrito_id` (`distrito_id`),
  ADD KEY `rol_id` (`rol_id`),
  ADD KEY `idx_usuarios_activo` (`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias_insumos`
--
ALTER TABLE `categorias_insumos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `conocimientos`
--
ALTER TABLE `conocimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_conocimientos`
--
ALTER TABLE `detalle_conocimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `distritos`
--
ALTER TABLE `distritos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `log_actividades`
--
ALTER TABLE `log_actividades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `receptores`
--
ALTER TABLE `receptores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `sesiones_usuario`
--
ALTER TABLE `sesiones_usuario`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `conocimientos`
--
ALTER TABLE `conocimientos`
  ADD CONSTRAINT `conocimientos_ibfk_1` FOREIGN KEY (`entregante_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `conocimientos_ibfk_2` FOREIGN KEY (`receptor_id`) REFERENCES `receptores` (`id`),
  ADD CONSTRAINT `conocimientos_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `detalle_conocimientos`
--
ALTER TABLE `detalle_conocimientos`
  ADD CONSTRAINT `detalle_conocimientos_ibfk_1` FOREIGN KEY (`conocimiento_id`) REFERENCES `conocimientos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_conocimientos_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`);

--
-- Filtros para la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD CONSTRAINT `insumos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_insumos` (`id`);

--
-- Filtros para la tabla `log_actividades`
--
ALTER TABLE `log_actividades`
  ADD CONSTRAINT `log_actividades_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `receptores`
--
ALTER TABLE `receptores`
  ADD CONSTRAINT `receptores_ibfk_1` FOREIGN KEY (`distrito_id`) REFERENCES `distritos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sesiones_usuario`
--
ALTER TABLE `sesiones_usuario`
  ADD CONSTRAINT `sesiones_usuario_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`distrito_id`) REFERENCES `distritos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
--
-- Tablas del módulo de mantenimiento de equipos
--

CREATE TABLE `equipos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `serie` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `ubicacion` varchar(150) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `usuario_referencia` varchar(150) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_equipos_codigo` (`codigo`),
  UNIQUE KEY `idx_equipos_serie` (`serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

CREATE TABLE `mantenimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `folio` varchar(30) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `public_token` varchar(40) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `equipo_id` int NOT NULL,
  `tipo_mantenimiento` enum('preventivo','correctivo') COLLATE utf8mb4_spanish2_ci NOT NULL,
  `estado` enum('en_recepcion','en_diagnostico','en_mantenimiento','listo_para_entrega','entregado','cerrado') COLLATE utf8mb4_spanish2_ci NOT NULL DEFAULT 'en_recepcion',
  `recepcionista_id` int NOT NULL,
  `tecnico_id` int DEFAULT NULL,
  `supervisor_id` int DEFAULT NULL,
  `fecha_recepcion` date NOT NULL,
  `observaciones_recepcion` text COLLATE utf8mb4_spanish2_ci,
  `usuario_entrega` varchar(150) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `duracion_horas` decimal(8,2) DEFAULT NULL,
  `observaciones_finales` text COLLATE utf8mb4_spanish2_ci,
  `costo_mano_obra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `costo_repuestos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `costo_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mantenimientos_folio` (`folio`),
  UNIQUE KEY `uniq_mantenimientos_token` (`public_token`),
  KEY `idx_mantenimientos_equipo` (`equipo_id`),
  KEY `idx_mantenimientos_tecnico` (`tecnico_id`),
  KEY `idx_mantenimientos_supervisor` (`supervisor_id`),
  CONSTRAINT `fk_mant_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `fk_mant_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_mant_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_mant_recepcionista` FOREIGN KEY (`recepcionista_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

CREATE TABLE `diagnosticos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mantenimiento_id` int NOT NULL,
  `tecnico_id` int NOT NULL,
  `descripcion_falla` text COLLATE utf8mb4_spanish2_ci NOT NULL,
  `causa` text COLLATE utf8mb4_spanish2_ci,
  `accion_recomendada` text COLLATE utf8mb4_spanish2_ci,
  `fecha_diagnostico` date NOT NULL,
  `aprobado_por` int DEFAULT NULL,
  `observaciones_supervisor` text COLLATE utf8mb4_spanish2_ci,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_diagnosticos_mantenimiento` (`mantenimiento_id`),
  CONSTRAINT `fk_diag_mantenimiento` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`),
  CONSTRAINT `fk_diag_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_diag_supervisor` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

CREATE TABLE `mantenimiento_repuestos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mantenimiento_id` int NOT NULL,
  `insumo_id` int NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  PRIMARY KEY (`id`),
  KEY `idx_repuestos_mantenimiento` (`mantenimiento_id`),
  KEY `idx_repuestos_insumo` (`insumo_id`),
  CONSTRAINT `fk_repuestos_mantenimiento` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`),
  CONSTRAINT `fk_repuestos_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

CREATE TABLE `mantenimiento_conocimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mantenimiento_id` int NOT NULL,
  `conocimiento_id` int NOT NULL,
  `creado_por` int NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mantenimiento_conocimiento` (`mantenimiento_id`),
  UNIQUE KEY `uniq_conocimiento_mantenimiento` (`conocimiento_id`),
  CONSTRAINT `fk_mantenimiento_conocimiento_mantenimiento` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mantenimiento_conocimiento_conocimiento` FOREIGN KEY (`conocimiento_id`) REFERENCES `conocimientos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mantenimiento_conocimiento_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

CREATE TABLE `seguimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mantenimiento_id` int NOT NULL,
  `supervisor_id` int NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci NOT NULL,
  `fecha_seguimiento` datetime NOT NULL,
  `estado` enum('en_mantenimiento','listo_para_entrega','entregado','cerrado') COLLATE utf8mb4_spanish2_ci NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seguimientos_mantenimiento` (`mantenimiento_id`),
  CONSTRAINT `fk_seguimientos_mantenimiento` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`),
  CONSTRAINT `fk_seguimientos_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

CREATE TABLE `entregas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mantenimiento_id` int NOT NULL,
  `fecha_entrega` datetime NOT NULL,
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `entregado_por` int NOT NULL,
  `recibido_por` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `qr_code_url` varchar(255) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entregas_mantenimiento` (`mantenimiento_id`),
  CONSTRAINT `fk_entregas_mantenimiento` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`),
  CONSTRAINT `fk_entregas_usuario` FOREIGN KEY (`entregado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

CREATE TABLE `mantenimiento_estados_historial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mantenimiento_id` int NOT NULL,
  `estado` enum('en_recepcion','en_diagnostico','en_mantenimiento','listo_para_entrega','entregado','cerrado') COLLATE utf8mb4_spanish2_ci NOT NULL,
  `comentario` text COLLATE utf8mb4_spanish2_ci,
  `usuario_id` int NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historial_mantenimiento` (`mantenimiento_id`),
  CONSTRAINT `fk_historial_mantenimiento` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`),
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;
