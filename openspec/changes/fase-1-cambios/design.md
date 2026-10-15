# Design

## Context

- Ver proposal.md (Why) y `specs/devoluciones/spec.md` (comportamiento).
- **Puertas únicas existentes** (leer su PHPDoc): `MovimientosStock` (transacción obligatoria, orden por `variante_id`, `origen` morph), `MovimientosCaja` (sesión abierta del usuario, `CajaNoAbierta`, tipo `Devolucion` con signo negativo ya previsto), `CuentasCorrientes` (`acreditar()` con `cuentaDe()` que crea la cuenta sin crédito; convención `saldo = debe − haber`, negativo = a favor), `EmisorComprobante` + `NumeradorComprobantes` (numeración por tipo y punto de venta, bloqueo al final), `RegistradorAuditoria`, `CalculadoraIva`, `Importes`.
- **Orden de bloqueo** acordado: (venta) → stock por `variante_id` ascendente → sesión de caja → cuenta corriente → punto de venta. `Ventas::anular()` ya bloquea primero la venta.
- `venta_items` guarda por línea `total` (lo cobrado: con descuento propio, parte del descuento general y parte del recargo por cuotas), `neto` e `iva` con `neto + iva = total`; la suma de `total` de los ítems es el total de la venta y lo cobrado por los pagos.
- `comprobantes.devolucion_id` existe sin FK (fase-1-ventas D1): este cambio la agrega.
- La anulación exige la sesión de caja de la venta abierta (fase-1-ventas D9) y remite a este cambio para después del cierre.

## Goals / Non-Goals

**Goals:**
- Una única operación transaccional `Devoluciones::registrar()` que use las puertas existentes, con el mismo orden de bloqueo.
- Importes devueltos que nunca superen lo cobrado y cierren al centavo.
- Reusar la impresión de comprobantes y la venta rápida para el cambio, sin acoplar transacciones.

**Non-Goals:**
- Devoluciones en otra sucursal (la venta debe ser de la sucursal activa).
- Nota de crédito fiscal (ARCA): queda el punto de extensión en `EmisorComprobante`.
- Cambio "en una sola operación" (devolución + venta en la misma transacción).
- Reembolso de ventas cobradas con cuenta corriente o saldo a favor.

## Decisions

### D1. Esquema
- `devoluciones`: `sucursal_id`, `venta_id`, `cliente_id null` (FK clientes), `fecha date`, `motivo varchar(200)`, `resolucion varchar(30)` (`nota_credito_saldo_a_favor` | `reembolso`), `importe`, `neto`, `iva` en `decimal(14,2)`, `medio_pago_id null` y `caja_sesion_id null` (solo reembolso), `user_id`, `dispositivo varchar(36) null`, `created_at`. Índices `(sucursal_id, fecha)`, `venta_id`, `cliente_id`. Todas las FK `restrictOnDelete`.
- `devolucion_items`: `devolucion_id`, `venta_item_id`, `variante_id`, `cantidad` unsigned int, `importe`, `neto`, `iva` (`decimal(14,2)`), `created_at`. Índice `venta_item_id`.
- Migración aparte que agrega la FK `comprobantes.devolucion_id → devoluciones.id` (`restrictOnDelete`); el índice ya existe. El `down()` solo quita la FK.
- Doc 05 lista la resolución como "nota_credito, saldo o reembolso". Como toda devolución emite nota de crédito y el saldo a favor *es* la nota de crédito acreditada en cuenta (doc 05, "Nota de crédito"), se usan dos valores: `nota_credito_saldo_a_favor` y `reembolso` (enum `ResolucionDevolucion`).
- Modelos `Devolucion` y `DevolucionItem` inmutables (`LogicException` en `updating`/`deleting`). Sin factories: como las ventas, solo se crean con el servicio.

### D2. Importes (en centavos, por ítem)
Para un ítem con `n` unidades vendidas y lo cobrado `T` (`total`), `N` (`neto`), `I` (`iva`), del que ya se devolvieron `d` unidades por `D_T`, `D_N`, `D_I`, devolver `q` unidades:
- `importe = ⌊T × (d + q) / n⌋ − D_T` (acumulado proporcional; `D_T` siempre es `⌊T × d / n⌋`, así la suma nunca supera `T` y la última devolución cierra exactamente en `T`).
- Si `d + q = n` (cierra el ítem): `neto = N − D_N`, `iva = I − D_I`, así lo devuelto replica el desglose original. Si no: `neto` e `iva` con `CalculadoraIva::desglosar(importe, alícuota)` (si al cerrar quedara algún valor negativo por redondeos previos, también se desglosa).
- El producto usa `Brick\Math\BigInteger` (ya en uso en `CalculadoraVenta`) para no desbordar 64 bits.
- *Alternativa descartada:* recalcular con `CalculadoraVenta` sobre las unidades devueltas: devolvería al precio de lista o redistribuiría el descuento general distinto de como se cobró.
- *Alternativa descartada:* precio unitario redondeado × cantidad: puede devolver más de lo cobrado en devoluciones sucesivas.

### D3. Servicio `Devoluciones::registrar()`
`registrar(Venta, array $cantidades /* venta_item_id => q */, string $motivo, ResolucionDevolucion, ?Cliente, ?MedioPago $medioReembolso, ?string $referencia): Devolucion`. Antes de la transacción verifica el emisor. En una `DB::transaction`:
1. Bloquea la venta (`lockForUpdate`), así dos devoluciones simultáneas de la misma venta se serializan y la cantidad devolvible se calcula con lo ya grabado. Rechaza si está anulada o no es de la sucursal activa.
2. Valida cantidades contra `vendida − devuelta` (suma de `devolucion_items` del ítem).
3. Resolución: saldo a favor → cliente = el de la venta o el indicado (obligatorio); reembolso → `Gate::allows('ventas.reembolsar')`, todos los pagos de la venta pasan por caja, medio activo que pasa por caja, referencia si el medio la requiere, sesión abierta del usuario (`CajaNoAbierta`).
4. Graba devolución e ítems; reingresa stock (`TipoMovimientoStock::Ingreso`, origen la devolución, orden por `variante_id`); registra el egreso `TipoMovimientoCaja::Devolucion` (importe negativo, origen la devolución) o acredita la cuenta (`CuentasCorrientes::acreditar`, origen la devolución, sucursal de la venta); emite la nota de crédito; audita `devoluciones.saldo_a_favor` o `devoluciones.reembolso`.
- Errores: `DevolucionRechazada(mensaje, campo)` (mismo patrón que `VentaRechazada`), `CajaNoAbierta`. El controlador los traduce a errores de validación.
- Tipo de stock: `Ingreso` con origen la devolución, sin `reversa_de_id` (una devolución parcial no revierte un movimiento entero). *Alternativa descartada:* un tipo `devolucion` nuevo en `TipoMovimientoStock`; el origen ya distingue el motivo y el kardex lo muestra en el motivo.

### D4. Nota de crédito
- `TipoComprobante::NotaCreditoInterna` (`nota_credito_interna`), numeración propia por punto de venta (el de la venta, como la anulación usa el del original).
- `EmisorComprobante::emitirNotaCredito(Devolucion): Comprobante`; `EmisorInterno` guarda en `datos_json` la foto completa (encabezado, comercio, sucursal, cliente de la devolución, vendedor de la venta, ítems devueltos, totales con IVA por alícuota, reintegro según la resolución, `devolucion` con número, motivo y resolución, y `referencia` al comprobante de la venta). `EmisorNoDisponible` la rechaza.
- El comprobante lleva `venta_id` y `devolucion_id`: la impresión reutiliza `comprobantes.imprimir` (ticket/A4) y su autorización (ver la venta). La vista agrega el bloque de la devolución y titula "Reintegro" en lugar de "Pagos".

### D5. Cambio (VEN-03)
Pragmático y sin acoplar transacciones: el cambio es devolución con saldo a favor + venta nueva. El detalle de la devolución ofrece "Usar el saldo en una nueva venta", que abre `ventas.create?cliente_id=…`; el controlador precarga el cliente (activo, con `clientes.ver`) con su saldo a favor y la venta rápida propone como primer pago el medio "saldo a favor". La venta usa `Ventas::registrar()` tal como está (el saldo a favor ya es medio de pago).
- *Alternativa descartada:* una sola transacción devolución + venta. Duplica la pantalla de venta y obliga a revertir la devolución si la venta falla; con el saldo en la cuenta el cliente puede volver otro día.

### D6. Anulación y devoluciones
`Ventas::anular()` rechaza una venta con devoluciones y `VentaPolicy::anular` oculta el botón: la anulación reingresaría todo el stock y revertiría todos los cobros, duplicando lo ya devuelto.

### D7. Pantallas, rutas y permisos
- `GET /ventas/devoluciones` (listado de la sucursal, filtros `desde`/`hasta`, de ventas visibles para el usuario), `GET /ventas/{venta}/devolucion` (formulario), `POST /ventas/{venta}/devolucion`, `GET /ventas/devoluciones/{devolucion}` (detalle). Otra sucursal → 404.
- `VentaPolicy::devolver` = `ventas.devolver` + puede ver la venta + no anulada. El reembolso además `ventas.reembolsar` (Form Request y servicio).
- Formulario POST clásico (validado por `RegistrarDevolucionRequest`); el componente Alpine `devolucion` solo muestra los campos de la resolución elegida y resuelve la búsqueda y el alta rápida de cliente con los endpoints JSON existentes (`clientes.buscar`, `clientes.rapido`).
- Permisos: `ventas.devolver` (Vendedor, Cajero, Gerente) y `ventas.reembolsar` (Gerente). Navegación: "Devoluciones" en "Ventas y caja" para `ventas.devolver`.

## Risks / Trade-offs

- [El vendedor podría devolver al día siguiente sin control del Gerente] → solo con saldo a favor (no sale dinero), auditado; el reembolso es del Gerente.
- [El cliente del alta rápida queda creado aunque la devolución falle] → mismo comportamiento que la venta rápida; es un cliente válido y sin movimientos.
- [Precios cambiados entre la venta y el cambio] → se devuelve lo cobrado y la venta nueva cobra el precio actual: la diferencia la paga o la conserva el cliente como saldo.
- [La nota de crédito interna no es fiscal] → rotulada "Documento no válido como factura"; con ARCA el emisor fiscal emitirá la nota de crédito real por el mismo punto de extensión.

## Migration Plan

- `php artisan migrate` crea `devoluciones`, `devolucion_items` y la FK de `comprobantes.devolucion_id` (no hay filas con `devolucion_id` cargado, así que la FK no falla). `RolesYPermisosSeeder` agrega los permisos nuevos.
- Rollback: `migrate:rollback` del lote quita la FK y las tablas (solo si no hay devoluciones reales).
