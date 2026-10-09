# Spec Delta

## Purpose

Permite que el sistema sirva tanto a comercios de calzado como a comercios que no manejan talles, habilitando o deshabilitando el uso del talle en las variantes de producto mediante un parámetro de sistema.

## ADDED Requirements

### Requirement: Parámetro "Usar talles en productos"
El sistema SHALL ofrecer un parámetro de sistema "Usar talles en productos" con dos valores: habilitado o deshabilitado. Su valor inicial SHALL ser habilitado.

#### Scenario: Valor inicial en una instalación nueva
- **WHEN** se instala el sistema y nadie modificó el parámetro
- **THEN** el parámetro "Usar talles en productos" figura como habilitado

#### Scenario: Consulta del parámetro
- **WHEN** un usuario con permiso de configuración abre la pantalla de parametrización
- **THEN** ve el parámetro "Usar talles en productos" con su valor actual

### Requirement: Solo el Administrador modifica el parámetro de talles
El sistema SHALL permitir modificar el parámetro "Usar talles en productos" únicamente a usuarios con permiso de configuración (rol Administrador), y SHALL registrar cada cambio en la auditoría con usuario, valor anterior y valor nuevo.

#### Scenario: Administrador deshabilita los talles
- **WHEN** un Administrador cambia el parámetro de habilitado a deshabilitado y guarda
- **THEN** el parámetro queda deshabilitado
- **AND** se registra en auditoría el usuario, el valor anterior "habilitado" y el valor nuevo "deshabilitado"

#### Scenario: Usuario sin permiso intenta modificarlo
- **WHEN** un usuario con rol Vendedor, Cajero, Depósito o Gerente intenta modificar el parámetro
- **THEN** el sistema rechaza la operación con un error de autorización
- **AND** el valor del parámetro no cambia

### Requirement: Variantes con talle cuando el parámetro está habilitado
Con el parámetro habilitado, el sistema SHALL exigir un talle al crear una variante y SHALL identificar cada variante por producto, color y talle, sin permitir dos variantes con la misma combinación.

#### Scenario: Alta de variante sin talle con talles habilitados
- **WHEN** el parámetro está habilitado y se intenta crear una variante sin talle
- **THEN** el sistema rechaza el alta indicando que el talle es obligatorio

#### Scenario: Variante duplicada por producto, color y talle
- **WHEN** el parámetro está habilitado y ya existe la variante "Bota Ana / negro / 37"
- **AND** se intenta crear otra variante "Bota Ana / negro / 37"
- **THEN** el sistema rechaza el alta por variante duplicada

### Requirement: Variantes sin talle cuando el parámetro está deshabilitado
Con el parámetro deshabilitado, el sistema SHALL crear variantes sin talle, SHALL no solicitar ni aceptar un talle en el alta, y SHALL identificar cada variante nueva por producto y color, sin permitir dos variantes sin talle con el mismo producto y color.

#### Scenario: Alta de variante sin talle con talles deshabilitados
- **WHEN** el parámetro está deshabilitado y se crea la variante "Taza Flores / azul"
- **THEN** la variante se crea sin talle, con su SKU y código de barras propios

#### Scenario: Variante duplicada por producto y color
- **WHEN** el parámetro está deshabilitado y ya existe la variante sin talle "Taza Flores / azul"
- **AND** se intenta crear otra variante sin talle "Taza Flores / azul"
- **THEN** el sistema rechaza el alta por variante duplicada

#### Scenario: El formulario no muestra el talle
- **WHEN** el parámetro está deshabilitado y un usuario abre el alta o edición de una variante
- **THEN** el formulario no muestra el campo talle

### Requirement: Cambiar el parámetro no altera datos existentes
Cambiar el parámetro SHALL no modificar, borrar ni completar talles en variantes, stock, movimientos, ventas ni clientes existentes. Las variantes con talle lo conservan y se siguen mostrando con él; las variantes sin talle siguen siendo válidas para vender y mover stock.

#### Scenario: Deshabilitar con variantes que ya tienen talle
- **WHEN** existen variantes con talle y el Administrador deshabilita el parámetro
- **THEN** esas variantes conservan su talle, su stock y su historial
- **AND** se muestran con su talle en búsquedas, tickets y kardex

#### Scenario: Habilitar con variantes que no tienen talle
- **WHEN** existen variantes sin talle y el Administrador habilita el parámetro
- **THEN** esas variantes siguen disponibles para vender y para movimientos de stock
- **AND** se muestran sin talle

### Requirement: Venta rápida según el parámetro
En la venta rápida, el sistema SHALL pedir la elección de talle solo cuando el parámetro está habilitado; con el parámetro deshabilitado, la selección de variante SHALL hacerse por producto y color, o directamente por escaneo del código.

#### Scenario: Venta con talles deshabilitados
- **WHEN** el parámetro está deshabilitado y el vendedor busca un producto en la venta rápida
- **THEN** el sistema ofrece elegir solo el color, sin paso de talle

#### Scenario: Escaneo de código con talles deshabilitados
- **WHEN** el parámetro está deshabilitado y el vendedor escanea el código de barras de una variante
- **THEN** la variante se agrega a la venta sin pedir talle

#### Scenario: Venta con talles habilitados
- **WHEN** el parámetro está habilitado y el vendedor busca un producto en la venta rápida
- **THEN** el sistema pide elegir talle y color

### Requirement: Talle habitual del cliente según el parámetro
La ficha de cliente SHALL mostrar y permitir editar el talle habitual solo cuando el parámetro está habilitado. Con el parámetro deshabilitado, el dato SHALL ocultarse sin borrarse.

#### Scenario: Ficha de cliente con talles deshabilitados
- **WHEN** el parámetro está deshabilitado y un usuario abre la ficha de un cliente con talle habitual cargado
- **THEN** la ficha no muestra el talle habitual
- **AND** el valor guardado se conserva

### Requirement: Reportes de talles según el parámetro
Los reportes y filtros basados en talle (ventas por talle, curva de talles) SHALL ofrecerse solo cuando el parámetro está habilitado. Los demás reportes SHALL funcionar igual con el parámetro deshabilitado, agrupando por producto y color.

#### Scenario: Curva de talles con talles deshabilitados
- **WHEN** el parámetro está deshabilitado y un usuario abre el menú de reportes de stock
- **THEN** no aparece el reporte "Curva de talles"

#### Scenario: Ventas por producto con talles deshabilitados
- **WHEN** el parámetro está deshabilitado y un usuario consulta ventas por producto
- **THEN** el reporte agrupa por producto y color, sin columna ni filtro de talle
