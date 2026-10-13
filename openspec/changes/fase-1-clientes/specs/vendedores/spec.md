# Spec Delta

## Purpose

Identifica qué usuarios venden en el comercio, para asignar cada venta a un vendedor y reportar las ventas por vendedor (VND-01).

## ADDED Requirements

### Requirement: Marca de vendedor sobre el usuario
Quien gestiona usuarios SHALL poder marcar o desmarcar a un usuario como vendedor al crearlo o editarlo. Cada usuario SHALL tener a lo sumo un registro de vendedor; desmarcarlo SHALL desactivar el registro sin borrarlo. El cambio SHALL quedar en la auditoría del usuario.

#### Scenario: Alta de un usuario vendedor
- **WHEN** el Administrador crea un usuario marcando "Es vendedor"
- **THEN** el usuario queda registrado como vendedor activo y la auditoría del alta lo indica

#### Scenario: Usuario deja de ser vendedor
- **WHEN** el Administrador edita a un vendedor y desmarca "Es vendedor"
- **THEN** el registro de vendedor queda inactivo y la auditoría muestra el cambio

#### Scenario: Listado de usuarios
- **WHEN** un usuario con permiso abre el listado de usuarios
- **THEN** los vendedores activos se distinguen con la etiqueta "Vendedor"

### Requirement: Vendedores disponibles en la sucursal
El sistema SHALL ofrecer la lista de vendedores disponibles en una sucursal (por defecto la activa): vendedores activos cuyo usuario está activo y asignado a esa sucursal, ordenados por nombre.

#### Scenario: Vendedores de la sucursal activa
- **WHEN** hay un vendedor activo asignado a la sucursal activa, otro asignado a otra sucursal, uno inactivo y uno cuyo usuario está desactivado
- **THEN** la lista de la sucursal activa contiene solo el primero
