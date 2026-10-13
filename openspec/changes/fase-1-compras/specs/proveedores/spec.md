# Spec Delta

## Purpose

Mantiene el catálogo de proveedores a los que el comercio les compra mercadería, con su CUIT validado, datos de contacto y condiciones de pago, para usarlo en órdenes de compra y recepciones.

## ADDED Requirements

### Requirement: Catálogo de proveedores
El sistema SHALL mantener proveedores con razón social obligatoria, CUIT opcional, contacto, teléfono, email, condiciones de pago y estado activo. Los proveedores SHALL ser comunes a todas las sucursales y SHALL no borrarse: se desactivan. Un proveedor inactivo SHALL no ofrecerse para órdenes ni recepciones nuevas. Altas y cambios SHALL quedar auditados.

#### Scenario: Alta de un proveedor
- **WHEN** el Gerente crea "Calzados del Norte SA" con CUIT 30-71234567-1 y condiciones "30 días"
- **THEN** el proveedor queda activo, el CUIT se guarda como 30712345671 y el alta queda auditada

#### Scenario: Proveedor desactivado
- **WHEN** el Gerente desactiva un proveedor
- **THEN** deja de ofrecerse para nuevas órdenes y recepciones, y las anteriores lo siguen mostrando

### Requirement: Validación de CUIT
Cuando se informa, el CUIT SHALL tener 11 dígitos (con o sin guiones), un prefijo válido (20, 23, 24, 25, 26, 27, 30, 33 o 34) y un dígito verificador correcto según el algoritmo de módulo 11. El CUIT SHALL ser único entre proveedores.

#### Scenario: Dígito verificador incorrecto
- **WHEN** se intenta guardar un proveedor con CUIT 30-71234567-2
- **THEN** el sistema lo rechaza con el error "El CUIT no es válido."

#### Scenario: Formato incorrecto
- **WHEN** se intenta guardar un proveedor con CUIT "1234"
- **THEN** el sistema lo rechaza con un error de validación

#### Scenario: CUIT repetido
- **WHEN** se crea un proveedor con un CUIT que ya tiene otro proveedor, escrito con o sin guiones
- **THEN** el sistema lo rechaza con un error de validación

#### Scenario: Sin CUIT
- **WHEN** se crean dos proveedores sin CUIT
- **THEN** ambos se guardan

### Requirement: Permisos sobre proveedores
Ver el listado de proveedores SHALL requerir `compras.ver` y crearlos o modificarlos SHALL requerir `compras.gestionar`.

#### Scenario: Depósito no edita proveedores
- **WHEN** un usuario con rol Depósito intenta crear o editar un proveedor
- **THEN** el sistema responde 403

#### Scenario: Vendedor no ve proveedores
- **WHEN** un usuario con rol Vendedor abre el listado de proveedores
- **THEN** el sistema responde 403
