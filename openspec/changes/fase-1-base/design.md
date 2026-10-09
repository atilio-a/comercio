# Design

## Context

- Repositorio: esqueleto Laravel 12.69 / PHP 8.2.12, PHPUnit 11, Pint, Laravel Boost. Frontend con **Tailwind 4** (`@tailwindcss/vite`, `@import 'tailwindcss'` en `resources/css/app.css`) y Vite 6. Sin Alpine, sin autenticación, solo el modelo `User` y las migraciones de fábrica.
- Base `comercio` en MariaDB 10.4.32 (XAMPP), vacía y sin tabla de migraciones. PHP con `pdo_sqlite` y `mbstring`, sin `intl`.
- `phpunit.xml` no define base de pruebas: hoy los tests usarían `comercio` y `RefreshDatabase` la vaciaría.
- `config/app.php` tiene `timezone => 'UTC'` y `locale` desde `APP_LOCALE` (por defecto `en`).
- Requerimientos y motivación: ver proposal.md y `specs/`. Decisiones de base heredadas del doc 05: spatie/laravel-permission en modo teams con `team_id = sucursal_id`, `parametros (clave, valor)`, `auditorias (user_id, accion, modelo, modelo_id, antes, despues, ip, dispositivo)`.

## Goals / Non-Goals

**Goals:**
- Que los cambios siguientes de la Fase 1 solo tengan que: declarar sus permisos, leer parámetros, registrar auditoría y tomar la sucursal activa y el dispositivo, sin reinventar nada.
- Autorización siempre del lado del servidor; la interfaz solo oculta.
- Base de pruebas aislada de la de desarrollo.

**Non-Goals:**
- Editor de matriz rol-permiso en la interfaz (los permisos se definen en el seeder).
- ABM de sucursales en la interfaz (solo existe "Local principal"; se agrega cuando se habilite la segunda sucursal en la Fase 4).
- Verificación de email, registro público, eliminación de cuenta propia.
- Backups, HTTPS y configuración de correo de producción.

## Decisions

### D1. Breeze (Blade) adaptado a Tailwind 4
Se instala `laravel/breeze` como dependencia de desarrollo y se ejecuta `breeze:install blade` con PHPUnit. Breeze 2.x genera su stack Blade sobre Tailwind 3 (`tailwind.config.js`, `postcss.config.js`, `tailwindcss@^3`). Después de instalar:
- se restaura Tailwind 4: `@tailwindcss/vite` en `vite.config.js`, `app.css` con `@import 'tailwindcss'`, `@plugin '@tailwindcss/forms'` y los `@source` existentes; se eliminan `tailwind.config.js`, `postcss.config.js`, `autoprefixer` y `postcss`;
- se revisan las clases de las vistas generadas que cambiaron de nombre en v4 (por ejemplo `shadow-sm`→`shadow-xs`, `rounded`→`rounded-sm`, `outline-none`→`outline-hidden`, anillos de foco), verificando con `npm run build` y revisión visual.
- *Alternativa descartada:* aceptar Tailwind 3. Implica bajar de versión lo ya instalado, contra las instrucciones del proyecto.
- *Alternativa descartada:* Fortify o login propio. El usuario eligió Breeze; aporta controladores, tests y componentes Blade reutilizables.

Recortes sobre lo generado: se eliminan rutas, controladores, vistas y tests de **registro**, **verificación de email** y **eliminación de cuenta** (`ProfileController@destroy`). Los tests de Breeze que quedan se conservan y se adaptan a la sucursal activa y al español.

### D2. spatie/laravel-permission en modo teams
`spatie/laravel-permission` ^6 con `teams => true` **antes** de publicar y correr su migración. Se usa la columna por defecto `team_id`, con valor `sucursales.id`.
- Roles y permisos se crean **globales** (`team_id = null`); la asignación `model_has_roles` lleva la sucursal. Así un mismo rol se asigna en cualquier sucursal (USR-02).
- Middleware `EstablecerSucursalActiva` llama a `setPermissionsTeamId()` con la sucursal de la sesión; se registra con prioridad antes de `SubstituteBindings`, como pide spatie para teams. Al cambiar de sucursal se descargan las relaciones `roles`/`permissions` cacheadas del usuario.
- `Gate::before` otorga todo a quien tenga el rol Administrador en la sucursal activa (cubre permisos futuros).
- Permisos de esta fase: `usuarios.ver`, `usuarios.gestionar`, `parametros.editar`, `auditoria.ver`. Gerente recibe `usuarios.ver` y `auditoria.ver`; Vendedor, Cajero y Depósito, ninguno todavía (los suman los módulos siguientes). Seeder idempotente con `firstOrCreate`/`syncPermissions` y `permission:cache-reset`.

### D3. Sucursales y sucursal activa
- Tablas `sucursales (id, nombre, domicilio null, activa, timestamps)` y `sucursal_user (user_id, sucursal_id, timestamps)` con clave única compuesta.
- La sesión guarda `sucursal_activa_id`. El middleware (en el grupo `auth`) en cada solicitud: verifica que el usuario siga activo (si no, logout); verifica que la sucursal de sesión siga asignada y activa; si no hay sucursal fijada, toma la única asignada o, con varias y multi-sucursal habilitado, redirige al selector; con multi-sucursal deshabilitado toma la de menor id.
- La sucursal activa se expone con un helper/servicio (`SucursalActiva`) que usarán los módulos siguientes para completar `sucursal_id`.
- *Alternativa descartada:* sucursal en la URL (`/s/{sucursal}/...`). Agrega ruido a todas las rutas mientras hay una sola sucursal.

### D4. Parámetros: enum de definición + tabla clave/valor + caché
- Tabla `parametros (id, clave único, valor text, timestamps)` según doc 05.
- Un enum `App\Enums\Parametro` (claves `multi_sucursal`, `stock_negativo`, `facturacion_electronica`, `usar_talles`) define tipo, valor por defecto, etiqueta y ayuda en español. La tabla solo guarda valores; la metadata vive en código versionado.
- Servicio `Parametros` con `obtener(Parametro)`/`guardar(...)`, caché (`Cache::rememberForever`) invalidada al guardar. Seeder con `firstOrCreate` para no pisar valores elegidos.
- Form Request de parámetros: `boolean` por clave conocida; `facturacion_electronica` con regla que prohíbe `true` mientras no exista emisor fiscal.
- *Alternativa descartada:* columnas `tipo`/`etiqueta` en la tabla. Duplica metadata que el código necesita igual para validar.

### D5. Auditoría explícita con un servicio
- Tabla `auditorias (id, user_id null, sucursal_id null, accion, modelo, modelo_id null, antes json null, despues json null, ip, dispositivo, user_agent, created_at)` con índices `(sucursal_id, created_at)`, `user_id`, `(modelo, modelo_id)`. Se agregan `sucursal_id` y `user_agent` a lo previsto en doc 05 para filtrar por sucursal (spec) y describir el navegador.
- Servicio `RegistradorAuditoria::registrar(accion, modelo, antes, despues)` que completa usuario, sucursal activa, IP y dispositivo, y filtra claves sensibles (`password`, `remember_token`, tokens). Se llama dentro de la misma `DB::transaction` que la operación.
- El modelo `Auditoria` lanza excepción en `updating` y `deleting`; no hay rutas de edición ni borrado.
- *Alternativa descartada:* observers de Eloquent o un paquete de auditoría. No capturan bien los cambios de pivote (roles y sucursales) ni la semántica de negocio ("desactivó usuario"), y agregan dependencia.

### D6. Identificador de dispositivo por cookie
Middleware `IdentificarDispositivo` (grupo `web`): si no existe la cookie `dispositivo_id`, genera un UUID y la emite por 5 años, `HttpOnly`, `SameSite=Lax` y cifrada (Laravel cifra cookies por defecto). El valor queda disponible para la auditoría y para las operaciones de dinero de módulos futuros.
- *Alternativa descartada:* huella digital del navegador. Poco confiable y con implicancias de privacidad.

### D7. Usuarios
- Migración nueva agrega `users.activo` (boolean, default true, índice). Se conserva la columna `name` (convención de Laravel y Breeze) en lugar de `nombre` del doc 05; la interfaz la muestra como "Nombre".
- `UserController` (index, create, store, edit, update) con `StoreUserRequest`/`UpdateUserRequest` y `UserPolicy`. Rol por sucursal en el formulario: una fila por sucursal asignada con su rol. Las reglas "último Administrador activo" y "no autodesactivarse" viven en la validación del Form Request. Alta y edición en transacción con auditoría.
- Comando `comercio:crear-administrador` con Laravel Prompts (incluido en el framework), validando email único y contraseña con `Password::defaults()`.

### D8. Español y zona horaria
- `config/app.php`: `timezone => 'America/Argentina/Jujuy'`. `.env.example`: `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`, `APP_FAKER_LOCALE=es_AR`. El `.env` local lo ajusta el usuario.
- `php artisan lang:publish` y traducción a mano de `lang/es/{auth,passwords,pagination,validation}.php` más `lang/es.json` con los textos de Breeze. Sin paquete de traducciones.

### D9. Pruebas
- PHPUnit (framework actual). `phpunit.xml` fija `DB_CONNECTION=mariadb` y `DB_DATABASE=comercio_testing` para que `RefreshDatabase` nunca toque `comercio`. La base `comercio_testing` debe crearse una vez (requiere aprobación).
- *Alternativa descartada:* SQLite en memoria. Más rápida, pero difiere de MariaDB en JSON, claves foráneas y columnas generadas que usarán los módulos siguientes.
- Tests de feature por capacidad: autenticación (incluye inactivo y throttling), sucursal activa, autorización por rol y por sucursal, ABM de usuarios y reglas del último Administrador, parámetros (validación, facturación bloqueada, caché, auditoría) y auditoría (contenido, exclusión de sensibles, inmutabilidad, filtros, permiso), dispositivo.

## Risks / Trade-offs

- [Breeze sobrescribe `routes/web.php`, `vite.config.js`, `app.css` y `package.json`] → El repositorio es un esqueleto sin cambios propios en esos archivos; se revisa el diff y se restaura Tailwind 4 inmediatamente (D1).
- [Clases de Tailwind 3 que cambian en v4 dejan estilos rotos] → Revisión de las vistas de Breeze con la guía de migración de v4, `npm run build` y prueba visual en ancho de celular.
- [Olvidar `setPermissionsTeamId` en un contexto sin HTTP (comandos, jobs) da permisos vacíos] → El servicio de sucursal activa expone un método para fijarla explícitamente; se documenta en el código y se cubre con test del comando de administrador.
- [Caché de parámetros o permisos desactualizada] → Invalidación al guardar; `CACHE_STORE=array` en tests; `permission:cache-reset` en el seeder.
- [Correo de recuperación sin configurar en desarrollo] → `MAIL_MAILER=log` en `.env.example`; la configuración de producción queda pendiente.
- [Nuevas dependencias] → Ambas son oficiales o ampliamente mantenidas: `laravel/breeze` (solo desarrollo; el código generado queda en el repo) y `spatie/laravel-permission` (prevista en el doc 05). Se verifica compatibilidad con Laravel 12 al instalar.

## Migration Plan

1. Crear la base `comercio_testing` y ajustar `phpunit.xml`.
2. Instalar dependencias y Breeze, restaurar Tailwind 4, recortar registro/verificación/borrado de cuenta.
3. Configurar spatie en modo teams y correr `php artisan migrate` sobre `comercio` (vacía: no hay datos que migrar).
4. Correr seeders (sucursal, roles y permisos, parámetros) y `comercio:crear-administrador`.
Rollback: `php artisan migrate:rollback` por lotes (solo tablas nuevas) y revertir los commits. No usar `migrate:fresh` sin aprobación.

## Open Questions

- Configuración de correo (SMTP) para la recuperación de contraseña en producción.
- Textos finales de ayuda de cada parámetro (se pueden ajustar sin cambiar specs ni tareas).
