# Guía de módulos

Los módulos funcionales del sistema se agrupan en subdirectorios dentro de `modules/` para mantener separados los activos, servicios y vistas de cada flujo. Cada módulo debe seguir la estructura base:

```
modules/
  └── nombre_modulo/
      ├── index.php            # Entrada o panel principal del módulo
      ├── pages/               # Vistas secundarias (formularios, reportes, etc.)
      └── services/            # Clases de dominio, repositorios y helpers específicos
```

## Convenciones
- Todas las páginas de un módulo cargan dependencias comunes con `dirname(__DIR__, 3)` para reutilizar la configuración global.
- Los enlaces hacia otras vistas del mismo módulo deben construirse con `APP_URL` para evitar problemas al anidar directorios.
- Los servicios internos (repositorios, manejadores) viven en `services/` y pueden reutilizar utilidades compartidas (`includes/functions.php`).
- Mantén los estilos compartidos en `assets/css/style.css`; si un módulo requiere estilos propios, agrégales un prefijo único o crea un archivo dedicado dentro de `assets/`.

## Próximos módulos
Crea un subdirectorio por cada nuevo flujo (por ejemplo `modules/insumos` o `modules/soporte`) repitiendo la estructura previa. Esto facilita escalar el sistema sin mezclar responsabilidades ni rutas raíz.
