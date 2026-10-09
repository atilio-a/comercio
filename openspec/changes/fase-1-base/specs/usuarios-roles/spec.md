# Spec Delta

## Purpose

Define quién puede hacer qué en cada sucursal: el alta y mantenimiento de usuarios, los roles base del comercio y los permisos por módulo y acción, asignados por sucursal.

## ADDED Requirements

### Requirement: Roles base
El sistema SHALL proveer los roles Administrador, Gerente, Vendedor, Cajero y Depósito. Los roles y sus permisos SHALL cargarse con los datos iniciales y no SHALL editarse desde la interfaz en esta fase.

#### Scenario: Roles disponibles
- **WHEN** el Administrador asigna un rol a un usuario
- **THEN** puede elegir entre Administrador, Gerente, Vendedor, Cajero y Depósito

### Requirement: Permisos por módulo y acción
El sistema SHALL controlar cada pantalla y operación con permisos nombrados como `modulo.accion` (por ejemplo `usuarios.gestionar`, `parametros.editar`, `auditoria.ver`). Una operación sin el permiso correspondiente SHALL rechazarse en el servidor, aunque la interfaz oculte la opción.

#### Scenario: Operación sin permiso
- **WHEN** un usuario con rol Vendedor envía una solicitud para crear un usuario
- **THEN** el sistema responde con un error de autorización
- **AND** no se crea ningún usuario

#### Scenario: Navegación según permisos
- **WHEN** un usuario con rol Cajero abre el menú
- **THEN** no ve las opciones de usuarios, parámetros ni auditoría

### Requirement: Administrador con acceso total
El rol Administrador SHALL tener todos los permisos en la sucursal donde lo tiene asignado, incluidos los que agreguen los módulos futuros.

#### Scenario: Permiso de un módulo nuevo
- **WHEN** se agrega un permiso nuevo para un módulo futuro
- **THEN** un Administrador lo tiene sin que haya que asignárselo

### Requirement: Rol por sucursal
El sistema SHALL permitir asignar a un usuario un rol distinto en cada sucursal a la que está asignado. Sus permisos SHALL ser los del rol en la sucursal activa.

#### Scenario: Roles distintos por sucursal
- **WHEN** un usuario es Gerente en la sucursal A y Vendedor en la sucursal B
- **AND** su sucursal activa es B
- **THEN** solo tiene los permisos de Vendedor

### Requirement: ABM de usuarios
Un usuario con permiso `usuarios.gestionar` SHALL poder listar, crear y editar usuarios con nombre, email único, contraseña inicial, estado activo, sucursales asignadas y rol en cada sucursal. Los usuarios SHALL no borrarse: se desactivan.

#### Scenario: Alta de usuario
- **WHEN** el Administrador crea un usuario con nombre, email, contraseña, la sucursal "Local principal" y el rol Cajero
- **THEN** el usuario queda activo y puede ingresar con rol Cajero en "Local principal"

#### Scenario: Email duplicado
- **WHEN** el Administrador intenta crear un usuario con un email ya registrado
- **THEN** el sistema rechaza el alta indicando que el email ya está en uso

#### Scenario: Desactivación de usuario
- **WHEN** el Administrador desactiva un usuario
- **THEN** el usuario sigue existiendo con sus datos e historial, pero no puede ingresar

### Requirement: Siempre queda un Administrador activo
El sistema SHALL impedir desactivar, quitar el rol Administrador o quitar sucursales a un usuario si eso deja al sistema sin ningún Administrador activo. Un usuario SHALL no poder desactivarse a sí mismo.

#### Scenario: Último Administrador
- **WHEN** hay un solo Administrador activo y se intenta desactivarlo o cambiarle el rol
- **THEN** el sistema rechaza la operación indicando que debe quedar al menos un Administrador activo

#### Scenario: Autodesactivación
- **WHEN** un Administrador intenta desactivar su propio usuario
- **THEN** el sistema rechaza la operación

### Requirement: Creación del primer Administrador
El sistema SHALL ofrecer un comando de consola que cree un usuario Administrador asignado a "Local principal", pidiendo nombre, email y contraseña de forma interactiva, sin guardar credenciales en el código ni en los datos iniciales.

#### Scenario: Instalación nueva
- **WHEN** se ejecuta el comando en una instalación sin usuarios y se ingresan nombre, email y contraseña válidos
- **THEN** se crea un usuario activo con rol Administrador en "Local principal"

### Requirement: Cambios de usuarios auditados
Toda alta, edición, activación, desactivación y cambio de roles o sucursales de un usuario SHALL registrarse en la auditoría con los valores anteriores y nuevos, sin incluir contraseñas ni sus hashes.

#### Scenario: Cambio de rol auditado
- **WHEN** el Administrador cambia el rol de un usuario de Vendedor a Cajero en "Local principal"
- **THEN** la auditoría registra quién lo cambió, el usuario afectado, el rol anterior y el nuevo
