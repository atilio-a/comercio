# Spec Delta

## Purpose

Registra lo que el comercio le pide a sus proveedores (órdenes de compra) y la mercadería que efectivamente llega (recepciones), dando ingreso al stock de la sucursal con trazabilidad hasta el remito o la factura.

## ADDED Requirements

### Requirement: Órdenes de compra
Una orden de compra SHALL pertenecer a la sucursal activa y guardar proveedor activo, número correlativo por sucursal, fecha de negocio, estado, observaciones, usuario e ítems (variante única por orden, cantidad pedida entera mayor que 0, cantidad recibida y costo unitario con dos decimales).

#### Scenario: Numeración por sucursal
- **WHEN** se crean dos órdenes en la sucursal A y una en la sucursal B
- **THEN** las de A tienen los números 1 y 2 y la de B el número 1

#### Scenario: Variante repetida
- **WHEN** se intenta guardar una orden con la misma variante en dos ítems
- **THEN** el sistema lo rechaza con un error de validación

### Requirement: Ciclo de vida de la orden
Una orden SHALL crearse en `borrador` y solo en ese estado SHALL poder editarse. Al emitirla pasa a `emitida`. Su estado SHALL pasar a `parcial` cuando tenga algo recibido y a `recibida` cuando todo lo pedido esté recibido. Emitir, anular y editar SHALL quedar auditados.

#### Scenario: Edición de una orden emitida
- **WHEN** se intenta editar una orden emitida
- **THEN** el sistema lo rechaza

#### Scenario: Emisión
- **WHEN** el Gerente emite una orden en borrador
- **THEN** la orden queda `emitida` y lista para recibir

### Requirement: Anulación de órdenes
Una orden SHALL poder anularse solo si no tiene mercadería recibida (estado `borrador` o `emitida`). Una orden anulada SHALL no admitir recepciones ni cambios.

#### Scenario: Orden con recepciones
- **WHEN** se intenta anular una orden `parcial`
- **THEN** el sistema lo rechaza con un mensaje y la orden no cambia

#### Scenario: Orden sin recepciones
- **WHEN** el Gerente anula una orden emitida sin recepciones
- **THEN** la orden queda `anulada` y la anulación queda auditada

### Requirement: Recepción de mercadería
Una recepción SHALL guardar sucursal activa, proveedor, orden opcional, fecha de negocio, referencia de remito o factura, usuario, dispositivo e ítems (variante, cantidad entera mayor que 0 y costo). Cada ítem SHALL generar en la misma transacción un movimiento de stock de tipo Ingreso con origen la recepción y la fecha de la recepción.

#### Scenario: Ingreso de stock con origen
- **WHEN** se recibe 5 unidades de una variante sin stock
- **THEN** el stock queda en 5 y el kardex tiene un Ingreso de 5 cuyo origen es la recepción, con usuario y dispositivo

#### Scenario: Recepción sin orden
- **WHEN** Depósito registra una recepción libre de un proveedor activo con dos variantes
- **THEN** se crean la recepción, sus ítems y dos ingresos de stock

### Requirement: Recepción contra una orden
Solo una orden `emitida` o `parcial` de la sucursal activa SHALL poder recibirse; el proveedor de la recepción SHALL ser el de la orden y los ítems SHALL ser variantes de la orden. La cantidad recibida de cada ítem SHALL no superar lo pendiente; el excedente se registra como recepción sin orden. La recepción SHALL actualizar la cantidad recibida y el estado de la orden.

#### Scenario: Recepción parcial
- **WHEN** de una orden de 10 unidades se reciben 4
- **THEN** la orden queda `parcial` con 4 recibidas y 6 pendientes

#### Scenario: Recepción total
- **WHEN** se recibe lo pendiente de todos los ítems de una orden
- **THEN** la orden queda `recibida`

#### Scenario: Excede lo pendiente
- **WHEN** se intenta recibir 7 unidades de un ítem con 6 pendientes
- **THEN** el sistema lo rechaza indicando lo pendiente y no registra stock

#### Scenario: Variante ajena a la orden
- **WHEN** se intenta recibir contra una orden una variante que no está en ella
- **THEN** el sistema lo rechaza con un error de validación

#### Scenario: Orden en borrador o anulada
- **WHEN** se intenta recibir una orden en borrador o anulada
- **THEN** el sistema lo rechaza

### Requirement: Actualización de costo al recibir
Cada ítem de la recepción SHALL poder marcarse para actualizar el costo de la variante con el costo recibido. El cambio SHALL quedar auditado con el costo anterior, el nuevo y la recepción que lo originó. Sin la marca, el costo de la variante SHALL no cambiar.

#### Scenario: Costo actualizado
- **WHEN** se recibe una variante de costo 1000.00 a 1250.50 con la marca de actualizar costo
- **THEN** la variante queda con costo 1250.50 y la auditoría registra 1000.00 → 1250.50 con la recepción

#### Scenario: Costo sin marca
- **WHEN** se recibe la misma variante a 1250.50 sin la marca
- **THEN** la variante conserva el costo 1000.00

### Requirement: Recepciones inmutables y anulación
Una recepción y sus ítems SHALL no modificarse ni borrarse. Para corregirla SHALL anularse con motivo: se registra por cada ítem un Egreso de stock que revierte su ingreso, se descuenta la cantidad recibida de la orden y se recalcula su estado. La anulación SHALL ser única, auditada y no revierte costos.

#### Scenario: Anulación revierte stock y orden
- **WHEN** el Gerente anula una recepción de 4 unidades de una orden `parcial`
- **THEN** el kardex tiene un Egreso de -4 que revierte el ingreso, el stock vuelve al valor anterior y la orden vuelve a `emitida`

#### Scenario: Stock insuficiente para anular
- **WHEN** el parámetro de stock negativo está deshabilitado y ya se vendió parte de lo recibido
- **THEN** la anulación se rechaza con un mensaje que indica la variante y lo disponible, y nada cambia

#### Scenario: Stock negativo habilitado
- **WHEN** el parámetro de stock negativo está habilitado y no alcanza el stock
- **THEN** la anulación se registra, el stock queda negativo y queda auditado

#### Scenario: Doble anulación
- **WHEN** se intenta anular una recepción ya anulada
- **THEN** el sistema lo rechaza

### Requirement: Permisos y aislamiento de compras
Ver órdenes y recepciones SHALL requerir `compras.ver`; gestionar proveedores y órdenes y anular recepciones, `compras.gestionar`; registrar recepciones, `compras.recibir`. Por defecto el Gerente tiene los tres y Depósito `compras.ver` y `compras.recibir`. Órdenes y recepciones de otra sucursal SHALL no existir para el usuario.

#### Scenario: Depósito recibe pero no gestiona
- **WHEN** un usuario con rol Depósito registra una recepción y luego intenta crear una orden
- **THEN** la recepción se registra y la creación de la orden responde 403

#### Scenario: Cajero sin acceso
- **WHEN** un usuario con rol Cajero abre el listado de órdenes
- **THEN** el sistema responde 403

#### Scenario: Orden de otra sucursal
- **WHEN** un usuario abre o recibe una orden de una sucursal que no es la activa
- **THEN** el sistema responde 404
