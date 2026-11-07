# S.I.G.S.M. - AJDG-Hospital

Sistema de Gestión de Servicios Médicos: módulo **Documentación** (documentos y encuestas anónimas) y módulo **Ambulancias** (traslados).

## Iniciar con Docker (servidor CentOS)
```
git clone URL-DEL-REPO proyecto-hospital
cd proyecto-hospital
docker compose up -d --build
```
Abrir `http://IP-DEL-SERVIDOR:8000`. Para borrar la base y crearla de nuevo: `docker compose down -v` y volver a levantar.

## Requisitos (instalación sin Docker)
- XAMPP (Apache + MySQL/MariaDB) con PHP 7.4 o superior. Extensiones `pdo_mysql`, `fileinfo` y `mbstring` activas (vienen activadas en XAMPP).

## Instalación en otra máquina
1. Copiar toda la carpeta del proyecto dentro de `htdocs` (por ejemplo `C:\xampp\htdocs\proyecto-hospital`).
2. Iniciar **Apache** y **MySQL** desde el panel de XAMPP.
3. Abrir `http://localhost/phpmyadmin` con el usuario `root`, ir a **Importar** y elegir `sql/basedatos.sql`. El script crea la base `basedatos`, las tablas, las vistas, los usuarios de base de datos y los datos de prueba.
4. Abrir `http://localhost/proyecto-hospital/login.html`.

Si el servidor usa otro usuario o clave de MySQL, se cambian solo en `php/config.php`.

## Credenciales de prueba
| Rol | Cédula (usuario) | Contraseña |
|---|---|---|
| Administrador | 11111111 | Admin2026 |
| Enfermero | 22222222 | Prueba2026 |
| Chofer | 33333333 | Prueba2026 |
| Funcionario | 44444444 | Prueba2026 |

Usuarios de base de datos (los crea el script): `sigsm_admin` (todos los privilegios sobre `basedatos`) y `sigsm_app` (el que usa PHP: lectura en todo y escritura solo en las tablas que la aplicación modifica; sin CREATE, ALTER ni DROP).

## Permisos por rol
| Función | admin | enfermero | chofer | funcionario |
|---|---|---|---|---|
| Ver documentos | sí | sí | sí | sí |
| Subir / editar documentos | sí | sí | no | sí |
| Eliminar documentos | sí | no | no | no |
| Ver historial de traslados | todos | todos | solo los suyos | todos |
| Alta / edición de traslados | sí | sí | no | no |
| Cambiar estado de un traslado | sí | sí | solo los suyos | no |
| Eliminar traslados | sí | no | no | no |
| Resultados de encuestas | sí | no | no | no |
| Gestión de usuarios y roles | sí | no | no | no |

La encuesta de satisfacción es pública y anónima (no requiere sesión ni guarda datos personales). Quien se registra desde "Crear usuario" queda como `funcionario`; el administrador cambia el rol desde Usuarios.

## Arquitectura en tres capas
- **Presentación:** páginas `*.html`, `css/` y `js/` (validación en el navegador con `js/validar.js`).
- **Lógica:** `php/api/` (controladores: reciben la petición, comprueban sesión y rol, devuelven JSON) y `php/clase/` (clases con las reglas de negocio y la validación del servidor).
- **Datos:** `php/clase/conexion.php` (PDO con consultas preparadas) y la base MySQL (`sql/basedatos.sql`, con vistas).

## Seguridad implementada
Consultas preparadas en todo acceso a datos, validación en navegador y servidor, control de acceso por rol en cada endpoint, sesión con cookie HttpOnly + SameSite y regeneración de id al iniciar sesión, archivos subidos con nombre aleatorio y verificación de tipo real, carpeta `php/uploads` bloqueada para acceso directo y descarga solo a usuarios logueados.
