# Spec Delta

## Purpose

Define los puntos de venta de cada sucursal y la numeración correlativa de los comprobantes que emiten, para que cada comprobante tenga un número único por tipo y punto de venta, hoy en los internos y mañana en los fiscales.

## ADDED Requirements

### Requirement: Puntos de venta por sucursal
El sistema SHALL mantener puntos de venta con sucursal, número (entero de 1 a 99999, único en todo el sistema), descripción y estado activo. El número SHALL no modificarse después del alta y los puntos de venta SHALL no borrarse (se desactivan). Altas y cambios SHALL quedar auditados.

#### Scenario: Alta de un punto de venta
- **WHEN** el Administrador crea el punto de venta 2 "Mostrador planta alta" en la sucursal activa
- **THEN** el punto de venta queda activo en esa sucursal y el alta queda auditada

#### Scenario: Número repetido
- **WHEN** se intenta crear un punto de venta con un número que ya tiene otro punto de venta, de cualquier sucursal
- **THEN** el sistema lo rechaza con un error de validación en el número

### Requirement: Configuración reservada al Administrador
Solo un usuario con `puntos_venta.configurar` SHALL poder ver, crear o modificar puntos de venta. El permiso SHALL no asignarse a ningún rol de la matriz: lo tiene el Administrador. Los puntos de venta de otra sucursal SHALL responder como inexistentes.

#### Scenario: Gerente sin permiso
- **WHEN** un Gerente intenta abrir el listado de puntos de venta
- **THEN** el sistema responde 403

### Requirement: Punto de venta inicial
Los datos iniciales SHALL crear un punto de venta activo para cada sucursal activa que no tenga ninguno, con el menor número libre (el 1 en una instalación con una sola sucursal), sin duplicar al volver a ejecutarse.

#### Scenario: Instalación inicial
- **WHEN** se cargan los datos iniciales en una instalación con una sucursal
- **THEN** la sucursal tiene el punto de venta 1 activo, y volver a cargarlos no crea otro

### Requirement: Numeración correlativa por tipo y punto de venta
Cada comprobante SHALL recibir el número siguiente al último emitido para su tipo y punto de venta, empezando en 1. La combinación de tipo, punto de venta y número SHALL ser única, y dos emisiones simultáneas SHALL no obtener el mismo número. Una operación que falla SHALL no consumir número.

#### Scenario: Numeración independiente
- **WHEN** se emiten dos comprobantes de venta en el punto de venta 1, uno en el punto de venta 2 y una anulación en el punto de venta 1
- **THEN** reciben los números 1 y 2 en el punto 1, 1 en el punto 2 y 1 para la anulación del punto 1

#### Scenario: Operación fallida
- **WHEN** una venta falla por stock insuficiente después de haber calculado sus importes
- **THEN** la venta siguiente del mismo punto de venta recibe el número que habría recibido la fallida, sin hueco

#### Scenario: Número duplicado a nivel base
- **WHEN** se intenta grabar directamente un segundo comprobante con el mismo tipo, punto de venta y número
- **THEN** la base de datos lo rechaza
