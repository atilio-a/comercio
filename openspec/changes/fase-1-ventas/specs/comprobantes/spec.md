# Spec Delta

## Purpose

Separa la venta (operación comercial) del comprobante (documento emitido) para emitir hoy comprobantes internos no fiscales y habilitar mañana la facturación electrónica sin rehacer las ventas, con documentos inmutables que se imprimen en ticket o en A4.

## ADDED Requirements

### Requirement: Comprobante de la venta
Cada venta confirmada SHALL tener un comprobante emitido en la misma operación, con sucursal, tipo, punto de venta, número, fecha, emisor y una copia de los datos del documento (comercio, cliente, vendedor, ítems, pagos y totales). Los campos de CAE y su vencimiento SHALL quedar vacíos en los comprobantes internos.

#### Scenario: Emisión con la venta
- **WHEN** se registra una venta en el punto de venta 1
- **THEN** la venta tiene un comprobante interno del punto de venta 1 con el número siguiente, emisor "interno" y sin CAE

### Requirement: Comprobante interno rotulado
Mientras la facturación electrónica no esté habilitada, el sistema SHALL emitir comprobantes internos, y todo comprobante interno, en pantalla y al imprimir, SHALL llevar la leyenda "Documento no válido como factura".

#### Scenario: Leyenda
- **WHEN** se abre la impresión del comprobante de una venta
- **THEN** el documento muestra "Documento no válido como factura", el número con formato 00001-00000001, los ítems, los pagos y el total

### Requirement: Emisor intercambiable
La emisión SHALL depender del parámetro "Facturación electrónica": deshabilitado, SHALL usarse el emisor interno; habilitado, SHALL usarse el emisor fiscal. Mientras no exista un emisor fiscal, una venta con el parámetro habilitado SHALL rechazarse sin grabar nada.

#### Scenario: Facturación electrónica habilitada sin emisor fiscal
- **WHEN** el parámetro "Facturación electrónica" figura habilitado y se intenta registrar una venta
- **THEN** el sistema la rechaza indicando que la facturación electrónica no está disponible y no graba nada

### Requirement: Comprobantes inmutables
Un comprobante emitido SHALL no modificarse ni borrarse, y su impresión SHALL salir de los datos copiados al emitirlo, de modo que cambios posteriores de precios, clientes o productos no lo alteren.

#### Scenario: Cambio posterior del cliente
- **WHEN** después de la venta se cambia el nombre del cliente
- **THEN** la reimpresión del comprobante muestra el nombre que tenía al emitirse

### Requirement: Comprobante de anulación
Al anular una venta SHALL emitirse un comprobante interno de anulación, con su propia numeración en el punto de venta del comprobante original, que referencia al comprobante anulado y el motivo. El comprobante original SHALL conservarse sin cambios.

#### Scenario: Anulación de una venta
- **WHEN** se anula la venta con comprobante 00001-00000007
- **THEN** se emite el comprobante de anulación 00001-00000001 que referencia al 00001-00000007, y este último sigue igual

### Requirement: Impresión en ticket y A4
Cada comprobante SHALL poder imprimirse desde el navegador en formato ticket de 80 mm y en formato A4, sin elementos de navegación en la impresión, y guardarse como PDF con la función de impresión del navegador. Solo SHALL poder verlo quien puede ver la venta.

#### Scenario: Ticket
- **WHEN** el vendedor abre el comprobante de su venta en formato ticket
- **THEN** ve el documento con ancho de 80 mm y un botón para imprimir

#### Scenario: Comprobante ajeno
- **WHEN** un Vendedor abre el comprobante de una venta de otro vendedor
- **THEN** el sistema responde 403
