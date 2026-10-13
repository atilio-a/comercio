# Spec Delta

## Purpose

Mantiene la ficha de los clientes del comercio, común a todas las sucursales, para identificarlos en la venta, guardar su contacto y talle habitual, y consultar su historial y su cuenta corriente.

## ADDED Requirements

### Requirement: Ficha de cliente
El sistema SHALL mantener clientes con nombre obligatorio, documento, teléfono, email, talle habitual, condición frente al IVA (consumidor final, responsable inscripto, monotributo o exento; por defecto consumidor final), observaciones y estado activo. Los clientes SHALL ser comunes a todas las sucursales, SHALL no borrarse (se desactivan) y sus altas y cambios SHALL quedar auditados.

#### Scenario: Alta de un cliente
- **WHEN** un Vendedor crea a "María López" con DNI 30.123.456 y teléfono "388 4123456"
- **THEN** el cliente queda activo, consumidor final, con documento 30123456 y el alta queda auditada

#### Scenario: Cliente desactivado
- **WHEN** un Gerente desactiva un cliente
- **THEN** el cliente sigue existiendo con su historial y se lista como inactivo

### Requirement: Datos mínimos de identificación
Todo cliente SHALL tener, además del nombre, un teléfono o un documento. Un cliente responsable inscripto, monotributista o exento SHALL identificarse con CUIT.

#### Scenario: Sin teléfono ni documento
- **WHEN** se intenta crear un cliente solo con el nombre
- **THEN** el sistema lo rechaza pidiendo teléfono o documento

#### Scenario: Responsable inscripto con DNI
- **WHEN** se intenta guardar un cliente responsable inscripto con un DNI
- **THEN** el sistema lo rechaza indicando que corresponde CUIT

### Requirement: Validación del documento
Cuando se informa, el documento SHALL ser un DNI de 7 u 8 dígitos o un CUIT de 11 dígitos con prefijo admitido y dígito verificador correcto, escrito con o sin puntos, guiones o espacios. Se SHALL guardar solo con dígitos y SHALL ser único entre clientes.

#### Scenario: Documento inválido
- **WHEN** se intenta guardar un cliente con documento "123456", "20-12345678-0" con dígito verificador incorrecto o "abc"
- **THEN** el sistema lo rechaza con el error "El documento debe ser un DNI de 7 u 8 dígitos o un CUIT válido."

#### Scenario: Documento repetido
- **WHEN** se crea un cliente con un documento que ya tiene otro cliente, escrito con o sin puntos o guiones
- **THEN** el sistema lo rechaza con el error "Ya hay un cliente con ese documento."

#### Scenario: Clientes sin documento
- **WHEN** se crean dos clientes sin documento, cada uno con su teléfono
- **THEN** ambos se guardan

### Requirement: Búsqueda de clientes
El listado de clientes SHALL permitir buscar por parte del nombre, del documento (con o sin separadores) o del teléfono, y SHALL mostrar primero los activos ordenados por nombre.

#### Scenario: Búsqueda por documento con puntos
- **WHEN** un usuario busca "30.123.456"
- **THEN** el listado muestra al cliente con documento 30123456

### Requirement: Búsqueda y alta rápida para la venta
El sistema SHALL ofrecer una búsqueda en JSON de clientes activos (hasta 20 resultados, por nombre, documento o teléfono) y un alta rápida en JSON que solo exige nombre y teléfono o documento, con las mismas validaciones y auditoría que el alta completa.

#### Scenario: Búsqueda JSON
- **WHEN** un Cajero consulta la búsqueda JSON con "lóp"
- **THEN** recibe los clientes activos cuyo nombre contiene "lóp", con id, nombre, documento formateado y teléfono, y no recibe los inactivos

#### Scenario: Alta rápida
- **WHEN** un Vendedor envía en JSON el nombre "Juan Pérez" y el teléfono "388 5001122"
- **THEN** el sistema crea el cliente como consumidor final y responde 201 con sus datos

#### Scenario: Alta rápida inválida
- **WHEN** se envía un alta rápida sin teléfono ni documento
- **THEN** el sistema responde 422 con el error de validación y no crea el cliente

### Requirement: Talle habitual del cliente según el parámetro
La ficha y el formulario del cliente SHALL mostrar y permitir editar el talle habitual solo con el parámetro "Usar talles en productos" habilitado. Con el parámetro deshabilitado el talle SHALL ocultarse en pantallas y búsquedas, y guardar el cliente SHALL conservar el valor existente.

#### Scenario: Ficha de cliente con talles deshabilitados
- **WHEN** el parámetro está deshabilitado y un usuario abre la ficha o edita un cliente con talle habitual "38"
- **THEN** no ve el talle habitual
- **AND** al guardar el cliente, el talle habitual sigue siendo "38"

#### Scenario: Ficha de cliente con talles habilitados
- **WHEN** el parámetro está habilitado y un usuario edita el talle habitual de un cliente a "39"
- **THEN** el cliente queda con talle habitual "39" y la ficha lo muestra

### Requirement: Historial en la ficha del cliente
La ficha del cliente SHALL mostrar sus datos, el estado de su cuenta corriente para quien tenga permiso para verla, y un espacio para el historial de ventas que SHALL indicar que las ventas todavía no están disponibles.

#### Scenario: Ficha sin ventas habilitadas
- **WHEN** un usuario abre la ficha de un cliente
- **THEN** ve los datos del cliente y el aviso de que el historial de ventas estará disponible con el módulo de ventas

### Requirement: Permisos sobre clientes
Ver clientes (listado, ficha y búsqueda JSON) SHALL requerir `clientes.ver` y crearlos o modificarlos (incluida el alta rápida) SHALL requerir `clientes.gestionar`. Vendedor, Cajero y Gerente SHALL tener ambos permisos; Depósito ninguno.

#### Scenario: Depósito no ve clientes
- **WHEN** un usuario con rol Depósito abre el listado, la ficha o la búsqueda JSON de clientes
- **THEN** el sistema responde 403

#### Scenario: Cajero gestiona clientes
- **WHEN** un usuario con rol Cajero crea o edita un cliente
- **THEN** el cambio se guarda

### Requirement: Entrada de menú Clientes
El grupo "Ventas y caja" del menú principal SHALL incluir la entrada "Clientes", visible solo para quien tiene `clientes.ver`.

#### Scenario: Vendedor ve Clientes
- **WHEN** un Vendedor abre cualquier página
- **THEN** el grupo "Ventas y caja" incluye la entrada "Clientes"

#### Scenario: Depósito no ve Clientes
- **WHEN** un usuario con rol Depósito abre cualquier página
- **THEN** no ve la entrada "Clientes" ni el grupo "Ventas y caja"
