# Spec Delta

## Purpose

Prepara el sistema para operar con varias sucursales aunque hoy exista una sola: define las sucursales, a qué sucursales accede cada usuario y en qué sucursal trabaja durante su sesión.

## ADDED Requirements

### Requirement: Sucursal inicial
El sistema SHALL crear en la instalación una única sucursal activa llamada "Local principal".

#### Scenario: Instalación nueva
- **WHEN** se instala el sistema y se cargan los datos iniciales
- **THEN** existe una sola sucursal, "Local principal", activa

### Requirement: Usuarios asignados a sucursales
Cada usuario SHALL estar asignado a una o más sucursales activas. Un usuario SHALL operar solo en las sucursales a las que está asignado.

#### Scenario: Usuario sin sucursal
- **WHEN** el Administrador intenta guardar un usuario sin ninguna sucursal asignada
- **THEN** el sistema rechaza el guardado indicando que debe asignar al menos una sucursal

#### Scenario: Acceso a una sucursal no asignada
- **WHEN** un usuario intenta seleccionar como activa una sucursal a la que no está asignado
- **THEN** el sistema rechaza la operación con un error de autorización

### Requirement: Sucursal activa en la sesión
Al ingresar, el sistema SHALL fijar una sucursal activa en la sesión. Si el usuario tiene una sola sucursal asignada, SHALL usarla automáticamente. Los permisos del usuario SHALL evaluarse según su rol en la sucursal activa.

#### Scenario: Usuario con una sucursal
- **WHEN** un usuario asignado solo a "Local principal" ingresa
- **THEN** la sucursal activa de su sesión es "Local principal" sin que deba elegirla

#### Scenario: Usuario con varias sucursales
- **WHEN** el parámetro multi-sucursal está habilitado y un usuario asignado a dos sucursales ingresa
- **THEN** el sistema le pide elegir la sucursal activa antes de continuar

### Requirement: Selector de sucursal oculto con una sola sucursal
La interfaz SHALL ocultar el selector de sucursal y toda columna o filtro de sucursal mientras el parámetro multi-sucursal esté deshabilitado.

#### Scenario: Operación con una sola sucursal
- **WHEN** el parámetro multi-sucursal está deshabilitado
- **THEN** ninguna pantalla muestra el selector de sucursal

#### Scenario: Cambio de sucursal activa
- **WHEN** el parámetro multi-sucursal está habilitado y el usuario elige otra de sus sucursales en el selector
- **THEN** la sucursal activa de la sesión cambia y la navegación refleja sus permisos en esa sucursal

### Requirement: Usuario sin sucursales activas
El sistema SHALL impedir operar a un usuario cuyas sucursales asignadas estén todas inactivas.

#### Scenario: Única sucursal desactivada
- **WHEN** un usuario cuya única sucursal asignada está inactiva ingresa
- **THEN** el sistema cierra la sesión indicando que no tiene sucursales habilitadas
