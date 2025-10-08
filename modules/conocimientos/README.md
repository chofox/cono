# Módulo de Conocimientos

Este módulo agrupa todo el flujo de registro, consulta y reportes de los conocimientos de entrega.

## Estructura

```
modules/conocimientos/
├── index.php              # Listado principal con filtros, paginación y modal de detalle
├── ajax/
│   └── detalle.php        # Respuesta HTML para el modal de detalle
└── pages/
    ├── crear.php          # Formulario de registro y emisión de PDF
    ├── editar.php         # Edición de conocimientos en borrador
    ├── ver.php            # Vista detallada con acciones rápidas
    ├── reportes.php       # Tableros y exportaciones consolidadas
    ├── exportar_excel.php # Exportación CSV/Excel con filtros avanzados
    └── generar_pdf.php    # Generación de comprobante PDF con TCPDF
```

Todas las páginas usan `APP_URL` para construir rutas absolutas y cargan la navegación lateral/encabezado compartidos desde `includes/`.
