# 04 — Caja, medios de pago y cuentas corrientes

## Medios de pago
Catálogo configurable (alta/baja sin tocar código):

- **Efectivo.**
- **Tarjeta de débito y crédito:** marca, cuotas (con o sin interés), recargo configurable por plan, número de lote y cupón. El recargo integra el total gravado y lleva IVA.
- **Transferencia bancaria:** cuenta destino, referencia y comprobante adjunto (foto).
- **QR / billeteras (Mercado Pago, etc.):** registro manual al inicio, con integración posterior por API.
- **Cuenta corriente propia.**
- **Seña / saldo a favor / nota de crédito** (por cambios y devoluciones).

Un mismo ticket admite **pagos combinados**. Cada medio define si impacta en caja física, si tiene recargo o descuento, y en qué plazo se acredita.

## Cuentas corrientes de clientes
- Habilitación del crédito por cliente, con **límite de crédito** y bloqueo automático al superarlo o con mora (el proceso automático de bloqueo queda pendiente de definir).
- **Saldo a favor:** se acredita siempre en la cuenta corriente. Si el cliente no tiene, se le crea una **sin crédito habilitado**: no admite ventas a cuenta, pero sí usar el saldo a favor como medio de pago.
- Venta a cuenta: genera deuda; **cobranza** parcial o total con imputación a comprobantes (el más antiguo primero, o manual).
- **Cuotas propias:** plan de cuotas con vencimientos, interés configurable y cronograma por cliente.
- Estado de cuenta: movimientos, saldo y vencimientos, enviable por **WhatsApp o PDF**.
- Recordatorios de vencimiento y **reporte de morosidad** por antigüedad (0-30, 31-60, 61-90, +90 días).
- Intereses punitorios opcionales; refinanciación y quitas solo con permiso y auditoría.
- Devolución de una venta a cuenta: reduce deuda o genera saldo a favor.

## Caja
- **Apertura** con fondo inicial y **cierre diario** por usuario y turno.
- Arqueo: esperado contra contado por medio de pago, con diferencia justificada.
- Egresos (gastos, retiros, pago a proveedores) e ingresos varios, siempre con motivo.
- **Conciliación** de tarjetas y transferencias contra acreditaciones (reporte de cobros pendientes de acreditar).
- Varias cajas por sucursal (listo para crecer).

## Proveedores
Pagos con los mismos medios, imputación a facturas de compra y cheques propios o de terceros con fecha de cobro (opcional).

## Permisos y auditoría
| Rol | Capacidad |
|-----|-----------|
| Cajero | Cobra y cierra su caja. |
| Gerente | Autoriza descuentos, quitas, anulaciones y diferencias de caja. |
| Administrador | Configura medios de pago, planes de cuotas, intereses y límites. |

Todo movimiento de dinero es **inmutable**: se corrige con contraasiento, nunca editando.

## Requerimientos no funcionales asociados
- Importes con **decimales exactos** (`DECIMAL`, nunca flotantes) y redondeo configurable.
- Todo cobro y venta dentro de **transacciones** de base de datos.
- Registro de **dispositivo y usuario** en cada operación de dinero: ventas, cobranzas, devoluciones, pagos a proveedores, movimientos de caja y de cuenta corriente.
- Cada operación guarda su **fecha de negocio** además del momento de registro.
