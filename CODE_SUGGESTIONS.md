# Sugerencias de Mejora de Código

## Configuración y despliegue
- **Externalizar credenciales sensibles (`config/database.php`)**: Mover `host`, `db_name`, `username` y `password` a variables de entorno o un archivo `.env` no versionado y leerlos dinámicamente para evitar exponer secretos en el repositorio y facilitar configuraciones por entorno. Además, desactivar `display_errors` y `display_startup_errors` en producción para no filtrar detalles internos en caso de error. 【F:config/database.php†L11-L57】
- **Unificar parámetros de conexión**: Actualmente se declaran constantes `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` que no se utilizan por la clase `Database`. Considere reutilizar la lógica centralizada (`getConnection`) para evitar divergencias entre configuraciones CLI y web, y documentar claramente qué consumidor usa cada constante. 【F:config/database.php†L11-L57】

## Seguridad en formularios y autenticación
- **Validar el token CSRF en `login.php`**: Aunque el formulario genera un `csrf_token`, en la rama `POST` no se llama a `verify_csrf_token`. Agregar la validación y retornar un mensaje genérico si falla ayuda a prevenir ataques de falsificación de solicitudes. 【F:login.php†L22-L106】
- **Normalizar entrada antes de consultas**: Aprovechar `sanitize_input` o un filtro dedicado para `username` y `password` (p. ej., `trim`) antes de procesarlos. Esto evita espacios en blanco accidentales y mantiene coherencia con otras rutas que usan sanitización centralizada. 【F:login.php†L22-L88】【F:includes/functions.php†L11-L96】
- **Reemplazar logs verbosos en producción (`classes/Auth.php` y `login.php`)**: Muchos `error_log` imprimen tokens de sesión, consultas e identificadores. Sustituirlos por logs estructurados y anónimos (o condicionados a un modo debug) reduce el riesgo de exponer información sensible. 【F:classes/Auth.php†L1-L120】【F:login.php†L22-L63】

## Gestión de sesiones y auditoría
- **Aislar la lógica de expiración**: Extraer `cleanExpiredSessions()` y `extendSession()` (definidas en `Auth`) a un servicio que pueda reutilizarse para tareas programadas (cron) y pruebas unitarias. Además, documentar la zona horaria usada (`gmdate` vs `America/Guatemala`) para evitar desajustes entre la BD y PHP. 【F:classes/Auth.php†L1-L160】
- **Parametrizar almacenamiento de auditoría**: `log_user_activity` se apoya en `$_SERVER['REMOTE_ADDR']` y `$_SERVER['HTTP_USER_AGENT']`, que pueden no existir en entornos CLI/API. Usar operadores nulos (`$_SERVER['REMOTE_ADDR'] ?? ''`) previene avisos y mejora la resiliencia en ejecuciones de mantenimiento. 【F:includes/functions.php†L63-L110】

## Utilidades generales
- **Revisar `sanitize_input`**: Actualmente aplica `stripslashes` y `htmlspecialchars`, lo que puede alterar datos legítimos (por ejemplo, contenidos HTML permitidos). Considere usar `filter_var` específicos según contexto (email, texto plano) y delegar el escape a la capa de presentación (`escape_html`) para evitar doble escapado. 【F:includes/functions.php†L11-L36】
- **Agregar pruebas automatizadas básicas**: Incluir pruebas (p. ej., con PHPUnit) para funciones críticas como generación de números de conocimiento o validación de fechas ayudaría a detectar regresiones tempranas y sirve como documentación ejecutable. 【F:includes/functions.php†L40-L118】

Estas acciones ofrecen una ruta incremental para fortalecer la seguridad, mantenibilidad y confiabilidad del sistema sin cambios disruptivos inmediatos.
