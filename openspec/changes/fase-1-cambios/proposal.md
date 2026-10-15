# Proposal

## Why

Los cambios de talle y de producto son frecuentes en el comercio (doc 06, Fase 1) y hoy la única forma de revertir una venta es anularla, que exige que la caja de la venta siga abierta. Falta registrar devoluciones parciales o totales en cualquier momento, con nota de crédito, saldo a favor en la cuenta corriente del cliente o reembolso en dinero, y poder usar ese saldo en una nueva venta (doc 02 VEN-03; doc 04 "Saldo a favor" y "Devolución de una venta a cuenta"; doc 05 `devoluciones`, `devolucion_items` y nota de crédito).

## What Changes

- **Devolución de una venta confirmada:** se eligen los ítems y las cantidades a devolver (parcial o total, en una o varias devoluciones sucesivas), con motivo obligatorio. Nunca se devuelve más de lo vendido menos lo ya devuelto; las ventas anuladas no admiten devoluciones. La devolución es inmutable.
- **Importes devueltos:** cada ítem devuelve la parte proporcional de lo efectivamente cobrado por él (con sus descuentos y su parte del recargo por cuotas), con neto e IVA; al devolver la última unidad de un ítem se devuelve exactamente lo que quedaba, así la suma nunca supera lo cobrado y los centavos cierran.
- **Stock:** reingreso de cada variante devuelta en la sucursal de la venta, con la devolución como origen en el kardex.
- **Resolución:**
  - *Saldo a favor:* emite una nota de crédito interna y acredita el importe como haber en la cuenta corriente del cliente (que se crea sin crédito si no existía). Si la venta tuvo deuda en cuenta corriente, el haber la reduce. Exige cliente: si la venta fue a consumidor final se elige o se da de alta uno en la devolución.
  - *Reembolso en dinero:* solo con permiso de reembolso (Gerente) y la caja del usuario abierta; registra un egreso de devolución por el medio elegido y también emite la nota de crédito. No se admite si la venta se cobró con cuenta corriente o saldo a favor.
- **Nota de crédito interna:** nuevo tipo de comprobante interno con numeración correlativa propia por punto de venta, vinculado a la devolución y a la venta, rotulado "Documento no válido como factura", imprimible en ticket y A4.
- **Cambio:** devolución con saldo a favor seguida de una nueva venta en la que el cliente paga con ese saldo y la diferencia con otros medios. Desde el detalle de la devolución, "Usar el saldo en una nueva venta" abre la venta rápida con el cliente precargado.
- **Anulación:** una venta con devoluciones ya no se puede anular (se seguiría devolviendo lo ya devuelto).
- **Pantallas mobile-first:** "Devolver / cambiar" desde el detalle de la venta; listado de devoluciones de la sucursal; detalle con la nota de crédito imprimible. El detalle de la venta muestra sus devoluciones y la cantidad devuelta por ítem.
- **Permisos y auditoría:** `ventas.devolver` (Vendedor, Cajero, Gerente) y `ventas.reembolsar` (Gerente). Cada devolución queda auditada. Navegación: "Devoluciones" en el grupo "Ventas y caja".

## Capabilities

### New Capabilities
- `devoluciones`: devoluciones y cambios de ventas confirmadas con reingreso de stock, importes proporcionales a lo cobrado, nota de crédito interna, saldo a favor en cuenta corriente o reembolso en dinero, uso del saldo en una nueva venta, consulta y permisos.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. La regla nueva sobre la anulación de ventas con devoluciones se especifica en `devoluciones`. -->

## Impact

- **Código nuevo:** migraciones de `devoluciones`, `devolucion_items` y de la FK `comprobantes.devolucion_id`; modelos `Devolucion` y `DevolucionItem`; enum `ResolucionDevolucion`; excepción `DevolucionRechazada`; servicio `Devoluciones`; `RegistrarDevolucionRequest`; `DevolucionController` y vistas `ventas/devoluciones/*`; componente Alpine `devolucion`.
- **Archivos existentes que cambian:** `Permiso`, `Rol`, `TipoComprobante` (nota de crédito interna), `EmisorComprobante`, `EmisorInterno`, `EmisorNoDisponible`, `Ventas::anular()`, `VentaPolicy`, modelos `Venta`, `VentaItem` y `Comprobante` (relaciones), `VentaController` (detalle y cliente precargado en la venta rápida), `venta-rapida.js`, vistas `ventas/show`, `ventas/create` y `comprobantes/imprimir`, `routes/web.php`, navegación, `resources/js/app.js`; tests `DatosInicialesTest`, `NavegacionTest` y `AnularVentaTest`.
- **Base de datos:** dos tablas nuevas y una FK nueva en `comprobantes` (migración de `comercio` autorizada). Sin cambios de columnas existentes.
- **Dependencias:** ninguna nueva.
- **Fuera de alcance:** devoluciones en una sucursal distinta de la de la venta, notas de crédito fiscales (ARCA), cambio en una sola transacción con la nueva venta, reembolso de ventas cobradas con cuenta corriente, vencimiento del saldo a favor, reportes de cambios y devoluciones.
