# Spec Delta

## Purpose

Deja un rastro inmutable de las acciones sensibles (quién, qué, cuándo, desde dónde y qué cambió) y permite consultarlo, como base para auditar anulaciones, ajustes, descuentos y quitas en los módulos siguientes.

## ADDED Requirements

### Requirement: Contenido del registro de auditoría
Cada registro de auditoría SHALL guardar usuario, sucursal activa, acción, entidad afectada y su identificador, valores anteriores y nuevos de los campos cambiados, IP, dispositivo y momento de registro.

#### Scenario: Registro completo
- **WHEN** un Administrador cambia un parámetro desde su celular
- **THEN** el registro de auditoría incluye su usuario, la sucursal activa, la acción, el parámetro, los valores anterior y nuevo, la IP y el identificador de dispositivo

### Requirement: Datos sensibles excluidos
Los registros de auditoría SHALL no contener contraseñas, hashes de contraseña ni tokens de sesión o de recuperación.

#### Scenario: Cambio de contraseña de un usuario
- **WHEN** el Administrador cambia la contraseña de un usuario
- **THEN** la auditoría registra que la contraseña cambió, sin incluir su valor ni su hash

### Requirement: Registros inmutables
Los registros de auditoría SHALL no poder editarse ni borrarse desde el sistema.

#### Scenario: Intento de modificación
- **WHEN** cualquier usuario, incluso un Administrador, busca editar o borrar un registro de auditoría
- **THEN** el sistema no ofrece esa operación y rechaza cualquier solicitud para hacerlo

### Requirement: Auditoría en la misma transacción
El registro de auditoría de una operación SHALL guardarse en la misma transacción que la operación auditada: si una falla, ninguna de las dos queda registrada.

#### Scenario: Falla al guardar la operación
- **WHEN** una operación auditada falla al guardarse
- **THEN** no queda registro de auditoría de esa operación

### Requirement: Consulta de auditoría
Un usuario con permiso `auditoria.ver` SHALL poder consultar los registros de auditoría de su sucursal activa, ordenados del más reciente al más antiguo, paginados y filtrables por período, usuario y acción.

#### Scenario: Filtro por usuario y período
- **WHEN** un Gerente filtra la auditoría por un usuario y el mes actual
- **THEN** ve solo los registros de ese usuario en ese período, del más reciente al más antiguo

#### Scenario: Usuario sin permiso
- **WHEN** un usuario con rol Vendedor intenta abrir la auditoría
- **THEN** el sistema responde con un error de autorización

### Requirement: Identificación de dispositivo
El sistema SHALL asignar a cada navegador un identificador de dispositivo persistente la primera vez que accede y SHALL reutilizarlo en las solicitudes siguientes, para registrarlo en auditoría y en operaciones de dinero.

#### Scenario: Primer acceso desde un navegador
- **WHEN** un navegador sin identificador accede al sistema
- **THEN** el sistema le asigna un identificador de dispositivo nuevo

#### Scenario: Accesos siguientes
- **WHEN** el mismo navegador vuelve a acceder días después
- **THEN** el sistema usa el mismo identificador de dispositivo
