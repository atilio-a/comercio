# Proposal

## Why

La documentación de `documentacion/` define el sistema completo en cuatro fases. Todos los módulos de la Fase 1 (catálogo, stock, compras, ventas, caja, reportes) dependen de una base común: saber quién opera, en qué sucursal, con qué permisos, bajo qué parámetros y dejando rastro auditable. Hoy el repositorio es un esqueleto Laravel 12 sin autenticación, sin tablas propias y con la base `comercio` vacía. Este cambio construye esa base para que los siguientes cambios de la Fase 1 se apoyen en ella.

## What Changes

- **Autenticación** con Laravel Breeze (stack Blade): login, logout, recuperación de contraseña y edición del propio perfil, en español. Sin registro público: los usuarios los da de alta el Administrador. Un usuario inactivo no puede ingresar.
- **Sucursales** (decisión de diseño 1 del doc 01): tabla `sucursales` con la fila inicial "Local principal", usuarios asignables a una o varias sucursales, sucursal activa en la sesión y selector de sucursal oculto mientras exista una sola.
- **Usuarios, roles y permisos** (USR-01, USR-02): roles base Administrador, Gerente, Vendedor, Cajero y Depósito; permisos por módulo y acción; rol asignado por sucursal con spatie/laravel-permission en modo teams (team = sucursal). ABM de usuarios para el Administrador.
- **Parámetros del sistema** (RNF-06): tabla `parametros` con multi-sucursal, stock negativo, facturación electrónica y uso de talles (este último definido en el cambio `talles-configurables`), pantalla de edición para el Administrador.
- **Auditoría** (USR-03, base): tabla `auditorias` y registro de usuario, acción, entidad, valores antes/después, IP y dispositivo. En esta fase se auditan cambios de usuarios, roles y parámetros; los módulos siguientes (anulaciones, ajustes, descuentos, quitas) usan el mismo mecanismo.
- **Identificación de dispositivo** (RNF-09): identificador persistente por navegador/equipo, disponible para auditoría y para las operaciones de dinero de los módulos siguientes.
- **Interfaz base mobile-first** en español: layout, navegación por permisos y tablero inicial vacío, reutilizando los componentes Blade de Breeze sobre Tailwind 4 ya instalado.
- **Configuración regional:** idioma español y zona horaria de Argentina.
- **Nuevas dependencias:** `laravel/breeze` (desarrollo) y `spatie/laravel-permission`. `alpinejs` y `@tailwindcss/forms` en el frontend (los trae Breeze).

## Capabilities

### New Capabilities
- `autenticacion`: ingreso, egreso, recuperación de contraseña, perfil propio y bloqueo de usuarios inactivos.
- `sucursales`: sucursales, asignación de usuarios y sucursal activa en la sesión.
- `usuarios-roles`: ABM de usuarios, roles base, permisos por módulo y acción, y asignación de rol por sucursal.
- `parametros-sistema`: parámetros configurables sin tocar código y su edición controlada.
- `auditoria`: registro y consulta de acciones sensibles, con usuario, IP y dispositivo.

### Modified Capabilities
<!-- No hay specs existentes en openspec/specs/. -->

## Impact

- **Código nuevo:** migraciones (`sucursales`, `sucursal_user`, `parametros`, `auditorias`, tablas de spatie, `users.activo`), modelos, Form Requests, policies, middleware de sucursal activa y de dispositivo, controladores y vistas Blade de usuarios, parámetros y auditoría, seeders de sucursal, roles, permisos y parámetros, comando Artisan para crear el primer Administrador.
- **Archivos existentes que cambian:** `composer.json`/`composer.lock`, `package.json`, `vite.config.js`, `resources/css/app.css`, `routes/web.php`, `bootstrap/app.php`, `app/Models/User.php`, `config/app.php` (zona horaria), `.env.example` (idioma), `phpunit.xml` (base de pruebas separada).
- **Base de datos:** primeras migraciones sobre la base `comercio`; los tests usan una base aparte (`comercio_testing`) para no borrar datos de desarrollo.
- **Fuera de alcance:** catálogo, stock, compras, ventas, caja, clientes y reportes (cambios siguientes de la Fase 1); backups automáticos y HTTPS de producción (infraestructura); edición de la matriz rol-permiso desde la interfaz; transferencias entre sucursales.
- **Relación con `talles-configurables`:** este cambio crea el parámetro "Usar talles en productos" en la lista de parámetros; el comportamiento del catálogo con o sin talles sigue definido en ese cambio.
