INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(4, 'Entregante', 'Usuario encargado de entregar insumos', 1, NOW()),
(5, 'Receptor', 'Usuario encargado de recibir insumos', 1, NOW());

-- Asignar el rol de Entregante al usuario con id 2 (Rodolfo Garcia)
UPDATE `usuarios` SET `rol_id` = 4 WHERE `id` = 2;

-- Insertar un nuevo usuario con rol de Receptor (ejemplo)
INSERT INTO `usuarios` (`nombre_completo`, `usuario`, `password_hash`, `puesto`, `distrito_id`, `rol_id`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
('Maria Lopez', 'marial', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2...', 'Recepcionista', 1, 5, 1, NOW(), NOW());