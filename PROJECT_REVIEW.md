# Revisión del proyecto "Sistema de Conocimiento de Entrega de Insumos"

## Resumen ejecutivo
El proyecto implementa un sistema web en PHP para registrar y gestionar entregas de insumos, con módulos de autenticación por roles, reportes, generación de PDFs y exportaciones, orientado a la Dirección Departamental de Redes Integradas de Servicios de Salud de Alta Verapaz.【F:README.md†L1-L156】

## Puntos fuertes identificados
- El módulo de autenticación utiliza `password_hash`/`password_verify` y consultas preparadas para reducir riesgos de inyección SQL al validar credenciales y construir la sesión del usuario.【F:classes/Auth.php†L22-L66】
- Existe una utilidad para generar y extender tokens de sesión que evita el uso de sesiones PHP simples y permite caducidades controladas desde la base de datos.【F:classes/Auth.php†L123-L200】
- Se incluyen helpers reutilizables para sanitización básica, validaciones y numeración automática de conocimientos que centralizan reglas comunes del dominio.【F:includes/functions.php†L12-L157】

## Riesgos y problemas
1. **Credenciales sensibles en el repositorio.** El archivo de configuración expone host, usuario y contraseña reales de la base de datos y además mantiene la visualización de errores activa en producción, lo que podría revelar detalles críticos del entorno en caso de fallo.【F:config/database.php†L10-L75】
2. **Protección CSRF incompleta.** Aunque existe una función para generar/verificar tokens y el formulario de inicio de sesión incluye un campo oculto, el flujo `POST` no valida el token antes de procesar las credenciales, dejando la pantalla de login vulnerable a ataques de tipo CSRF.【F:includes/functions.php†L29-L40】【F:login.php†L22-L43】【F:login.php†L88-L139】
3. **Registro excesivo de información sensible.** La aplicación escribe en el log los intentos de acceso, los resultados completos del login (incluyendo estructuras con datos del usuario) y hasta el SQL utilizado para validar sesiones, lo que puede exponer información sensible si los logs son accesibles o se filtran.【F:login.php†L26-L37】【F:classes/Auth.php†L53-L175】
4. **Credenciales por defecto débiles documentadas públicamente.** El README publica usuario y contraseña iniciales (`admin`/`admin123`), lo que obliga a resetearlas inmediatamente y supone un vector de riesgo si se despliega sin endurecimiento adicional.【F:README.md†L83-L90】

## Recomendaciones prioritarias
1. Externalizar las credenciales a variables de entorno o archivos fuera del control de versiones y desactivar la muestra de errores en producción. Añadir ejemplos en `.env.example` y documentación sobre cómo configurarlos.【F:config/database.php†L10-L75】
2. Integrar la verificación de tokens CSRF en todos los formularios sensibles, empezando por `login.php`, retornando error si el token no coincide o falta, y regenerándolo tras un inicio de sesión exitoso.【F:includes/functions.php†L29-L40】【F:login.php†L22-L139】
3. Reducir los logs a mensajes genéricos en producción y evitar imprimir consultas o estructuras completas de usuario. Utilizar niveles/handlers configurables para depuración local y activar mascarado de datos sensibles.【F:login.php†L26-L37】【F:classes/Auth.php†L53-L175】
4. Reforzar el proceso de provisión inicial: exigir cambio de contraseña al primer acceso, documentar políticas de complejidad y retirar credenciales predeterminadas del README público o moverlas a documentación privada.【F:README.md†L83-L90】

## Próximos pasos sugeridos
- Auditar el resto de endpoints (`nuevo_conocimiento.php`, `reportes.php`, etc.) para confirmar que todas las operaciones de escritura usan consultas preparadas y que validan autorización por rol.
- Añadir pruebas automatizadas mínimas (por ejemplo, pruebas de integración para el flujo de login y generación de números de conocimiento) que faciliten detectar regresiones en futuras modificaciones.
- Implementar pipeline de despliegue que gestione variables sensibles mediante un gestor de secretos y ejecute verificaciones estáticas (linters/analizadores de seguridad) antes de publicar.
