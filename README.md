# Sistema de Conocimiento de Entrega de Insumos

Sistema web para la gestión y registro de entregas de insumos en la Dirección Departamental de Redes Integradas de Servicios de Salud de Alta Verapaz.

## 🚀 Características Principales

- **Autenticación segura** con roles diferenciados (Administrador, Técnico, RRHH)
- **Numeración automática** de conocimientos con formato Año-Número
- **Gestión de usuarios** y catálogo de insumos
- **Registro de entregas** con selección dinámica de insumos
- **Generación de PDFs** con formato oficial
- **Sistema de reportes** con filtros avanzados y exportación a Excel
- **Dashboard responsivo** con estadísticas en tiempo real

## 📋 Requisitos del Sistema

- **PHP 7.4+** con extensiones PDO, MySQL, mbstring
- **MySQL 5.7+** o **MariaDB 10.2+**
- **Servidor web** (Apache/Nginx)
- **TCPDF Library** (incluida en el proyecto)

## 🛠️ Instalación

### 1. Configuración de la Base de Datos

```sql
-- Crear la base de datos
CREATE DATABASE conocimientos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importar el esquema
mysql -u usuario -p conocimientos_db < database.sql
```

### 2. Configuración del Sistema

Editar el archivo `config/database.php` con los datos de tu servidor:

```php
// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'conocimientos_db');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

### 3. Configuración del Servidor Web

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Seguridad
<Files "config/*">
    Deny from all
</Files>
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ /config/ {
    deny all;
}
```

### 4. Permisos de Archivos

```bash
# Permisos para directorios de carga (si los hay)
chmod 755 uploads/
chmod 644 uploads/*

# Permisos para archivos de configuración
chmod 600 config/database.php
```

## 👤 Usuario por Defecto

**Usuario:** admin  
**Contraseña:** admin123  
**Rol:** Administrador

> ⚠️ **Importante:** Cambiar la contraseña del administrador después de la primera instalación.

## 📁 Estructura del Proyecto

```
cono/
├── config/
│   └── database.php          # Configuración de base de datos
├── classes/
│   └── Auth.php              # Clase de autenticación
├── includes/
│   └── functions.php         # Funciones utilitarias
├── ajax/
│   └── detalle_conocimiento.php  # Endpoint para detalles
├── tcpdf/                    # Librería TCPDF
├── database.sql              # Esquema de base de datos
├── login.php                 # Página de login
├── logout.php                # Cerrar sesión
├── dashboard.php             # Panel principal
├── nuevo_conocimiento.php    # Registro de entregas
├── usuarios.php              # Gestión de usuarios
├── insumos.php              # Gestión de insumos
├── reportes.php             # Sistema de reportes
├── generar_pdf.php          # Generación de PDFs
├── exportar_excel.php       # Exportación a Excel
└── README.md                # Este archivo
```

## 🔐 Roles y Permisos

### Administrador
- Gestión completa de usuarios
- Gestión de catálogo de insumos
- Acceso a todos los reportes
- Configuración del sistema

### Técnico
- Registro de nuevas entregas
- Visualización de sus propias entregas
- Generación de PDFs de sus conocimientos

### RRHH
- Consulta de conocimientos de su distrito
- Generación de reportes por distrito
- Validación de información

## 📊 Funcionalidades Principales

### 1. Registro de Conocimientos
- Selección de entregante y receptor
- Fecha y lugar de entrega
- Selección múltiple de insumos con cantidades
- Observaciones adicionales
- Numeración automática anual

### 2. Generación de PDFs
- Formato oficial institucional
- Información completa de entregante y receptor
- Detalle de insumos con cantidades
- Espacios para firmas

### 3. Sistema de Reportes
- Filtros por año, mes, distrito, usuarios, insumos
- Búsqueda por texto libre
- Paginación de resultados
- Exportación a Excel/CSV
- Estadísticas resumidas

### 4. Dashboard
- Estadísticas en tiempo real
- Gráficos de entregas mensuales
- Accesos rápidos por rol
- Actividad reciente

## 🔧 Configuración Avanzada

### Personalización de PDFs
Editar `generar_pdf.php` para modificar:
- Encabezados institucionales
- Formato de tablas
- Información adicional

### Configuración de Email (Opcional)
Para notificaciones automáticas, configurar en `config/database.php`:
```php
define('SMTP_HOST', 'tu_servidor_smtp');
define('SMTP_USER', 'tu_email');
define('SMTP_PASS', 'tu_contraseña');
```

### Backup Automático
Script recomendado para backup diario:
```bash
#!/bin/bash
mysqldump -u usuario -p conocimientos_db > backup_$(date +%Y%m%d).sql
```

## 🐛 Solución de Problemas

### Error de Conexión a Base de Datos
1. Verificar credenciales en `config/database.php`
2. Confirmar que el servidor MySQL esté ejecutándose
3. Verificar permisos del usuario de base de datos

### Error al Generar PDFs
1. Verificar que la carpeta `tcpdf/` tenga permisos de lectura
2. Confirmar que PHP tenga suficiente memoria (`memory_limit = 256M`)

### Problemas de Sesión
1. Verificar configuración de sesiones en PHP
2. Confirmar permisos de escritura en directorio de sesiones

## 📈 Mantenimiento

### Limpieza de Sesiones
El sistema incluye limpieza automática de sesiones expiradas. Para limpieza manual:
```sql
CALL LimpiarSesionesExpiradas();
```

### Optimización de Base de Datos
```sql
OPTIMIZE TABLE conocimientos, usuarios, insumos, detalle_conocimientos;
```

### Logs del Sistema
Los logs se almacenan en la tabla `actividades_usuario` para auditoría.

## 🔒 Seguridad

- Contraseñas encriptadas con `password_hash()`
- Protección CSRF en formularios
- Validación y sanitización de entradas
- Sesiones seguras con regeneración de ID
- Control de acceso basado en roles

## 📞 Soporte

Para soporte técnico o reportar problemas:
- Revisar logs en la tabla `actividades_usuario`
- Verificar configuración en `config/database.php`
- Consultar documentación de TCPDF para problemas de PDF

## 📄 Licencia

Sistema desarrollado para uso interno de la Dirección Departamental de Redes Integradas de Servicios de Salud de Alta Verapaz.

---

**Versión:** 1.0  
**Fecha:** Enero 2025  
**Desarrollado para:** Dirección Departamental de Redes Integradas de Servicios de Salud de Alta Verapaz