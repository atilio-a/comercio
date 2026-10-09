# Tasks

> Requiere aprobación previa del usuario para: crear la base `comercio_testing`, instalar dependencias (1.2, 2.1, 3.1) y correr migraciones sobre `comercio` (3.4). No usar `migrate:fresh` ni `db:wipe`.

## 1. Entorno de pruebas y configuración regional

- [x] 1.1 Crear la base `comercio_testing` (utf8mb4) en MariaDB y fijar en `phpunit.xml` `DB_CONNECTION=mariadb` y `DB_DATABASE=comercio_testing`; verificar con `php artisan test --compact` que los tests de ejemplo pasan sin tocar `comercio`
- [ ] 1.2 Fijar `timezone => 'America/Argentina/Jujuy'` en `config/app.php` y `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`, `APP_FAKER_LOCALE=es_AR`, `MAIL_MAILER=log` en `.env.example`; verificar con `php artisan config:show app.timezone`

## 2. Autenticación con Breeze

- [x] 2.1 Instalar `laravel/breeze` como dependencia de desarrollo y ejecutar `php artisan breeze:install blade --phpunit --no-interaction`; verificar que existen las rutas `login`, `logout`, `password.request` con `php artisan route:list --name=password`
- [x] 2.2 Restaurar Tailwind 4 según D1 (`vite.config.js` con `@tailwindcss/vite`, `app.css` con `@import 'tailwindcss'` y `@plugin '@tailwindcss/forms'`, borrar `tailwind.config.js` y `postcss.config.js`, quitar `tailwindcss@3`, `postcss`, `autoprefixer`) y corregir clases renombradas en las vistas generadas; verificar con `npm run build` sin errores y revisión visual del login en ancho de celular
- [x] 2.3 Eliminar registro público, verificación de email y eliminación de cuenta (rutas, controladores, vistas y tests asociados); verificar con un test que `GET /register` responde 404 y con `php artisan route:list` sin rutas `register` ni `verification`
- [x] 2.4 Publicar y traducir `lang/es/{auth,passwords,pagination,validation}.php` y `lang/es.json` con los textos de Breeze; verificar con un test que el error de credenciales inválidas se muestra en español
- [x] 2.5 Ajustar los tests de Breeze que quedan (login, logout, recuperación, perfil, cambio de contraseña) y agregar test de throttling tras 5 intentos; verificar con `php artisan test --compact tests/Feature/Auth tests/Feature/ProfileTest.php`

## 3. Sucursales, roles y permisos

- [x] 3.1 Instalar `spatie/laravel-permission` ^6, publicar config y migración, fijar `teams => true` antes de migrar; verificar en la migración publicada que `model_has_roles` incluye `team_id`
- [x] 3.2 Crear migraciones de `sucursales`, `sucursal_user` (única por usuario y sucursal) y `users.activo` (boolean, default true, índice) con modelos `Sucursal`, relaciones en `User` (`sucursales()`, trait `HasRoles`), factories y casts en `casts()`; verificar con test de relaciones
- [x] 3.3 Crear `SucursalSeeder` ("Local principal") y `RolesYPermisosSeeder` idempotentes (roles globales, permisos `usuarios.ver`, `usuarios.gestionar`, `parametros.editar`, `auditoria.ver`, asignación por rol según D2) y registrarlos en `DatabaseSeeder`; verificar corriendo el seeder dos veces en test sin duplicados
- [x] 3.4 Ejecutar `php artisan migrate` y `php artisan db:seed` sobre `comercio`; verificar con `php artisan migrate:status` y la herramienta `database-schema` de Boost
- [x] 3.5 Implementar servicio `SucursalActiva` y middleware `EstablecerSucursalActiva` (usuario activo, sucursal asignada y activa, `setPermissionsTeamId`, prioridad antes de `SubstituteBindings`) y `Gate::before` para Administrador; verificar con tests: usuario inactivo se desloguea, sucursal única se fija sola, sucursal no asignada da 403, roles distintos por sucursal, Administrador con permiso inexistente en el seeder
- [x] 3.6 Implementar selector de sucursal (vista y ruta) visible solo con multi-sucursal habilitado; verificar con tests de visibilidad según parámetro y de cambio de sucursal activa

## 4. Parámetros del sistema

- [x] 4.1 Crear migración y modelo `parametros (clave único, valor)`, enum `App\Enums\Parametro` (tipo, default, etiqueta, ayuda) y servicio `Parametros` con caché; verificar con tests unitarios de lectura, default y invalidación de caché
- [x] 4.2 Crear `ParametroSeeder` idempotente con los cuatro parámetros y sus valores iniciales; verificar con test que conserva un valor ya modificado al re-ejecutarse
- [x] 4.3 Crear pantalla de parametrización (controlador, `UpdateParametrosRequest`, vista Blade accesible con etiquetas y ayudas) protegida por `parametros.editar`; verificar con tests: Administrador guarda, Vendedor recibe 403, clave inexistente y valor inválido fallan validación, facturación electrónica no se puede habilitar

## 5. Auditoría y dispositivo

- [x] 5.1 Crear middleware `IdentificarDispositivo` (cookie `dispositivo_id` UUID, 5 años, HttpOnly, SameSite=Lax) registrado en el grupo `web`; verificar con tests de primer acceso y reutilización de la cookie
- [x] 5.2 Crear migración y modelo `Auditoria` inmutable (excepción en `updating`/`deleting`) y servicio `RegistradorAuditoria` que completa usuario, sucursal, IP, dispositivo y user agent y filtra claves sensibles; verificar con tests de contenido, exclusión de `password` y excepción al editar o borrar
- [x] 5.3 Auditar cambios de parámetros dentro de la transacción de guardado (sin registros si no hay cambios); verificar con tests de cambio auditado, guardado sin cambios y rollback ante falla
- [x] 5.4 Crear pantalla de consulta de auditoría (paginada, del más reciente al más antiguo, filtros por período, usuario y acción, limitada a la sucursal activa) protegida por `auditoria.ver`; verificar con tests de filtros, aislamiento por sucursal y 403 para Vendedor

## 6. Gestión de usuarios

- [x] 6.1 Crear `UserController` (index, create, store, edit, update), `StoreUserRequest`/`UpdateUserRequest`, `UserPolicy` y vistas con rol por sucursal; verificar con tests de alta, email duplicado, usuario sin sucursal y 403 para usuarios sin `usuarios.gestionar`
- [x] 6.2 Implementar reglas "siempre queda un Administrador activo" y "no autodesactivarse"; verificar con tests de último Administrador (desactivar, cambiar rol, quitar sucursal) y autodesactivación
- [x] 6.3 Auditar alta, edición, activación, desactivación y cambios de roles/sucursales en la misma transacción, sin contraseñas; verificar con tests que revisan `antes`/`despues` de un cambio de rol y la ausencia de `password`
- [x] 6.4 Crear comando `comercio:crear-administrador` con Laravel Prompts; verificar con test de consola que crea un Administrador activo en "Local principal" y rechaza email duplicado

## 7. Interfaz base

- [x] 7.1 Adaptar layout y navegación de Breeze en español, mobile-first, con opciones según permisos (`@can`) y tablero inicial vacío; verificar con tests de que Cajero no ve Usuarios, Parámetros ni Auditoría y Administrador sí, y revisión con teclado del foco visible en el menú

## 8. Verificación integral

- [x] 8.1 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff final
- [ ] 8.2 Recorrido manual: crear Administrador con el comando, ingresar, crear un Cajero, cambiar un parámetro, ver la auditoría, ingresar como Cajero y comprobar el menú; verificar que todo funciona en el navegador del celular o en ancho móvil

## Workflow follow-up

- Archivar este cambio cuando se apruebe la revisión, para que las cinco capacidades queden en `openspec/specs/`.
- Proponer los siguientes cambios de la Fase 1 (catálogo con `talles-configurables`, stock, compras, ventas, caja, clientes y reportes) apoyados en esta base.
