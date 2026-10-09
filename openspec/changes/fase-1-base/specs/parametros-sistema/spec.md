# Spec Delta

## Purpose

Permite adaptar el comportamiento del sistema al comercio sin tocar código: un conjunto de parámetros con valores iniciales seguros, editables solo por quien tiene permiso y con cada cambio auditado.

## ADDED Requirements

### Requirement: Parámetros iniciales
El sistema SHALL crear en la instalación los parámetros "Multi-sucursal" (deshabilitado), "Permitir venta con stock negativo" (deshabilitado), "Facturación electrónica" (deshabilitado) y "Usar talles en productos" (habilitado).

#### Scenario: Instalación nueva
- **WHEN** se instala el sistema y se cargan los datos iniciales
- **THEN** existen los cuatro parámetros con esos valores

#### Scenario: Recarga de datos iniciales
- **WHEN** se vuelven a cargar los datos iniciales en una instalación donde el Administrador ya cambió un parámetro
- **THEN** el valor elegido por el Administrador se conserva

### Requirement: Edición de parámetros
Un usuario con permiso `parametros.editar` SHALL poder ver y modificar los parámetros desde una pantalla de parametrización. Cada parámetro SHALL mostrar un nombre y una ayuda en español que describan su efecto.

#### Scenario: Habilitar stock negativo
- **WHEN** el Administrador habilita "Permitir venta con stock negativo" y guarda
- **THEN** el parámetro queda habilitado y el nuevo valor rige en la siguiente solicitud

#### Scenario: Usuario sin permiso
- **WHEN** un usuario sin permiso `parametros.editar` envía una modificación de parámetros
- **THEN** el sistema la rechaza con un error de autorización y ningún valor cambia

### Requirement: Valores válidos
El sistema SHALL aceptar para cada parámetro solo valores de su tipo y SHALL rechazar claves de parámetros inexistentes.

#### Scenario: Valor inválido
- **WHEN** se envía un valor que no es habilitado ni deshabilitado para un parámetro de tipo sí/no
- **THEN** el sistema rechaza el cambio con un error de validación

#### Scenario: Parámetro inexistente
- **WHEN** se envía una clave que no corresponde a ningún parámetro
- **THEN** el sistema rechaza el cambio con un error de validación y no crea un parámetro nuevo

### Requirement: Facturación electrónica no habilitable todavía
El sistema SHALL impedir habilitar "Facturación electrónica" mientras no exista una implementación de emisión fiscal, mostrando el parámetro como no disponible.

#### Scenario: Intento de habilitar facturación
- **WHEN** el Administrador intenta habilitar "Facturación electrónica"
- **THEN** el sistema rechaza el cambio indicando que la facturación electrónica todavía no está disponible

### Requirement: Cambios de parámetros auditados
Todo cambio de valor de un parámetro SHALL registrarse en la auditoría con usuario, parámetro, valor anterior y valor nuevo. Guardar sin cambios SHALL no generar registros.

#### Scenario: Cambio auditado
- **WHEN** el Administrador cambia "Multi-sucursal" de deshabilitado a habilitado
- **THEN** la auditoría registra el usuario, el parámetro, "deshabilitado" como valor anterior y "habilitado" como nuevo

#### Scenario: Guardado sin cambios
- **WHEN** el Administrador guarda la pantalla de parámetros sin modificar ningún valor
- **THEN** no se agrega ningún registro de auditoría
