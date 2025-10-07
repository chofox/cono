# Detalles del Código del Sistema

## Arquitectura general
- Aplicación PHP multipágina organizada por módulos que arrancan sesión, cargan la configuración y reutilizan helpers comunes antes de renderizar HTML con Bootstrap/Font Awesome. Ejemplos representativos incluyen el dashboard y el listado de conocimientos, que invocan `config/database.php`, `classes/Auth.php` e `includes/functions.php` para preparar datos antes de imprimir la vista.【F:dashboard.php†L7-L105】【F:modules/conocimientos/index.php†L7-L150】
- El front-end se apoya en CDN externos para Bootstrap 5, Font Awesome y Chart.js además de una hoja de estilos propia (`assets/css/style.css`) que define la identidad visual del sistema.【F:dashboard.php†L117-L170】【F:assets/css/style.css†L3-L139】

## Configuración base
- `config/database.php` encapsula la conexión PDO, establece el juego de caracteres, fuerza zona horaria UTC-6 para MySQL y activa el modo de excepciones. El archivo también centraliza constantes de aplicación (nombre, versión, URL), sesiones, rutas de archivos y parámetros de seguridad como el algoritmo de hash y la longitud de tokens CSRF.【F:config/database.php†L1-L74】
- Composer solo declara la dependencia de `tecnickcom/tcpdf`, librería usada para los reportes en PDF.【F:composer.json†L1-L14】

## Utilidades compartidas
- `includes/functions.php` agrupa sanitización de entradas, validación de correo/fecha/teléfono, helpers de sesión y roles, generador de tokens CSRF, formato de fecha, numeración de conocimientos, logging de actividad y utilitarios de archivos. Varias funciones encapsulan accesos recurrentes a la base (años disponibles, catálogos de usuarios/insumos) para mantener el código de las páginas más legible.【F:includes/functions.php†L1-L378】

## Autenticación y gestión de sesiones
- La clase `Auth` maneja el ciclo completo de autenticación: verifica credenciales, persiste tokens en `sesiones_usuario`, renueva expiraciones, registra auditoría y expone helpers para recuperar al usuario actual o exigir roles específicos antes de servir contenido.【F:classes/Auth.php†L10-L415】
- Las tablas `sesiones_usuario` y `log_actividades` almacenan los tokens activos y el historial de acciones respectivamente, facilitando la expiración automática y la trazabilidad del uso.【F:database.sql†L205-L258】【F:database.sql†L312-L333】
- La pantalla de inicio de sesión valida CSRF, delega la verificación en `Auth` y redirige a `dashboard.php` cuando la sesión queda establecida.【F:login.php†L1-L195】

## Módulos funcionales clave
- **Dashboard**: muestra métricas agregadas (total, año y mes en curso, usuarios/insumos activos) y recientes conocimientos, filtrando por rol cuando no es administrador.【F:dashboard.php†L25-L101】
- **Gestión de conocimientos**: `modules/conocimientos/index.php` aplica filtros por texto, año, mes y estado, respeta las restricciones de rol y pagina los resultados con metadatos de entregante/receptor.【F:modules/conocimientos/index.php†L25-L150】
- **Registro de entregas**: `modules/conocimientos/pages/crear.php` arma los catálogos necesarios, valida insumos y datos obligatorios, inserta cabecera/detalle dentro de una transacción y abre automáticamente el PDF generado por el trigger de numeración al concluir.【F:modules/conocimientos/pages/crear.php†L7-L200】
- **Detalle y reportes**: `modules/conocimientos/pages/generar_pdf.php` produce el comprobante oficial con TCPDF validando permisos y adjuntando firmas opcionales; `modules/conocimientos/pages/exportar_excel.php` arma un CSV filtrable por múltiples criterios y restringe el acceso a roles administrativos o de RRHH.【F:modules/conocimientos/pages/generar_pdf.php†L1-L172】【F:modules/conocimientos/pages/exportar_excel.php†L1-L160】
- **Administración de catálogos**: `usuarios.php` exige rol de administrador para crear o actualizar personal, incluyendo carga de firmas y sincronización con tablas de distritos, puestos y roles.【F:usuarios.php†L1-L160】
- **Mantenimiento de equipos**: `modules/mantenimiento/index.php` ofrece filtros y accesos rápidos a recepción, diagnóstico, ejecución, entrega y consulta pública, mientras que las páginas anidadas (`modules/mantenimiento/pages/diagnostico.php`, `modules/mantenimiento/pages/ejecucion.php`, `modules/mantenimiento/pages/entrega.php`, `modules/mantenimiento/pages/seguimiento.php`, `modules/mantenimiento/pages/estado.php`) encapsulan cada fase con controles de rol y bitácoras.【F:modules/mantenimiento/index.php†L1-L210】【F:modules/mantenimiento/pages/diagnostico.php†L1-L200】【F:modules/mantenimiento/pages/ejecucion.php†L1-L200】【F:modules/mantenimiento/pages/entrega.php†L1-L210】【F:modules/mantenimiento/pages/seguimiento.php†L1-L200】【F:modules/mantenimiento/pages/estado.php†L1-L200】

## Esquema de datos
- El script `database.sql` define tablas para categorías de insumos, conocimientos, detalle, distritos, receptores, roles, sesiones, usuarios y logs. Destacan el trigger `generar_numero_conocimiento` que asegura numeración correlativa Año-Número y los índices para acelerar consultas por fecha, estado y participantes.【F:database.sql†L24-L200】【F:database.sql†L334-L390】
- El bloque final del esquema incorpora `equipos`, `mantenimientos`, `diagnosticos`, `mantenimiento_repuestos`, `seguimientos`, `entregas` y `mantenimiento_estados_historial`, garantizando llaves foráneas hacia usuarios y trazabilidad completa del proceso de mantenimiento.【F:database.sql†L400-L489】

## Estilos y experiencia de usuario
- La hoja `assets/css/style.css` consolida la paleta, sombras y componentes (tarjetas, formularios, sidebar, login) reforzando la identidad institucional sobre Bootstrap.【F:assets/css/style.css†L1-L139】

## Flujo típico
1. El usuario accede a `login.php`, se autentica y genera una sesión persistida en `sesiones_usuario` vía `Auth::login`.【F:login.php†L13-L195】【F:classes/Auth.php†L22-L220】
2. Tras entrar al dashboard consulta métricas y accede a la creación o listado según su rol; los filtros se ejecutan con sentencias preparadas para evitar inyección.【F:dashboard.php†L25-L101】【F:modules/conocimientos/index.php†L35-L141】
3. Al registrar un nuevo conocimiento se valida entrada, se guarda cabecera/detalle y se entrega numeración automática respaldada por el trigger y el PDF emitido con TCPDF.【F:modules/conocimientos/pages/crear.php†L80-L200】【F:database.sql†L90-L123】【F:modules/conocimientos/pages/generar_pdf.php†L29-L172】
4. Los administradores pueden exportar reportes a CSV o mantener usuarios, con logs y permisos controlados centralmente por la clase `Auth`.【F:modules/conocimientos/pages/exportar_excel.php†L24-L160】【F:usuarios.php†L18-L160】【F:classes/Auth.php†L311-L415】
5. El módulo de mantenimiento cubre recepción (`modules/mantenimiento/pages/recepcion.php`), asignación y diagnóstico, ejecución, entrega y seguimiento; cada etapa persiste cambios mediante `modules/mantenimiento/services/MantenimientoRepository.php` y expone una consulta pública (`modules/mantenimiento/pages/estado.php`) para transparencia.【F:modules/mantenimiento/pages/recepcion.php†L1-L220】【F:modules/mantenimiento/services/MantenimientoRepository.php†L1-L360】【F:modules/mantenimiento/pages/estado.php†L1-L200】
