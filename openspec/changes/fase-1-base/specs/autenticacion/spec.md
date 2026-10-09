# Spec Delta

## Purpose

Controla el acceso al sistema: solo usuarios dados de alta por el Administrador y activos pueden ingresar, con recuperación segura de contraseña y edición de su propio perfil.

## ADDED Requirements

### Requirement: Ingreso con email y contraseña
El sistema SHALL permitir ingresar con email y contraseña a usuarios activos. Las contraseñas SHALL guardarse con hash y nunca en texto plano. Toda pantalla del sistema, salvo las de ingreso y recuperación de contraseña, SHALL requerir sesión iniciada.

#### Scenario: Ingreso correcto
- **WHEN** un usuario activo ingresa su email y contraseña correctos
- **THEN** el sistema inicia la sesión y lo lleva al tablero de inicio

#### Scenario: Credenciales incorrectas
- **WHEN** alguien ingresa un email o una contraseña incorrectos
- **THEN** el sistema rechaza el ingreso con un mensaje en español que no revela cuál de los dos datos es incorrecto

#### Scenario: Acceso sin sesión
- **WHEN** un visitante sin sesión abre una pantalla interna del sistema
- **THEN** el sistema lo redirige a la pantalla de ingreso

### Requirement: Limitación de intentos de ingreso
El sistema SHALL bloquear temporalmente los intentos de ingreso para un mismo email e IP después de 5 intentos fallidos consecutivos.

#### Scenario: Demasiados intentos
- **WHEN** se registran 5 intentos fallidos para el mismo email desde la misma IP
- **THEN** el siguiente intento se rechaza indicando cuántos segundos faltan para reintentar

### Requirement: Usuarios inactivos no ingresan
El sistema SHALL rechazar el ingreso de un usuario marcado como inactivo, aunque sus credenciales sean correctas, y SHALL cerrar la sesión de un usuario que fue desactivado en su siguiente solicitud.

#### Scenario: Ingreso de usuario inactivo
- **WHEN** un usuario inactivo ingresa credenciales correctas
- **THEN** el sistema rechaza el ingreso indicando que el usuario está deshabilitado

#### Scenario: Desactivación con sesión abierta
- **WHEN** el Administrador desactiva a un usuario que tiene una sesión abierta
- **AND** ese usuario realiza una nueva solicitud
- **THEN** el sistema cierra su sesión y lo lleva a la pantalla de ingreso

### Requirement: Sin registro público
El sistema SHALL no ofrecer registro público de usuarios. Las altas SHALL hacerse solo desde la gestión de usuarios o con el comando de creación del primer Administrador.

#### Scenario: Ruta de registro
- **WHEN** un visitante intenta abrir la ruta de registro
- **THEN** el sistema responde que la página no existe

### Requirement: Recuperación de contraseña
El sistema SHALL permitir a un usuario activo solicitar un enlace de recuperación por email y definir una contraseña nueva con ese enlace, que SHALL vencer a los 60 minutos.

#### Scenario: Solicitud de recuperación
- **WHEN** un usuario solicita recuperar la contraseña con su email
- **THEN** el sistema envía un enlace de recuperación a ese email
- **AND** muestra el mismo mensaje de confirmación exista o no el email

#### Scenario: Enlace vencido
- **WHEN** un usuario usa un enlace de recuperación de más de 60 minutos
- **THEN** el sistema rechaza el cambio de contraseña

### Requirement: Perfil propio
El sistema SHALL permitir a cada usuario cambiar su nombre y su contraseña. El cambio de contraseña SHALL pedir la contraseña actual. Un usuario SHALL no poder cambiar sus propios roles, sucursales ni estado activo.

#### Scenario: Cambio de contraseña
- **WHEN** un usuario ingresa su contraseña actual correcta y una nueva confirmada
- **THEN** el sistema actualiza su contraseña

#### Scenario: Contraseña actual incorrecta
- **WHEN** un usuario intenta cambiar la contraseña con una contraseña actual incorrecta
- **THEN** el sistema rechaza el cambio

### Requirement: Cierre de sesión
El sistema SHALL permitir cerrar la sesión e invalidarla en el servidor.

#### Scenario: Cerrar sesión
- **WHEN** un usuario cierra la sesión
- **THEN** el sistema invalida la sesión y lo lleva a la pantalla de ingreso
