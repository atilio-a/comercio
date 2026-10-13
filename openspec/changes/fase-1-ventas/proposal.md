# Proposal

## Why

Con catálogo, stock, caja, clientes y vendedores ya disponibles, falta la operación central del comercio: vender en el mostrador desde el celular. La venta tiene que descontar stock, cobrar con uno o varios medios (incluido el saldo a favor de la cuenta corriente), emitir un comprobante interno numerado y poder anularse sin borrar nada (doc 01 decisión de diseño 2; doc 02 VEN-01, VEN-02, VEN-04, VEN-05, VEN-07, VEN-08, VEN-09 y VND-01; doc 04 medios de pago; doc 05 "Ventas").

## What Changes

- **Puntos de venta (VEN-07):** ABM de puntos de venta por sucursal (número único, descripción, activo), solo para el Administrador; los datos iniciales crean uno por sucursal activa que no tenga. Numeración correlativa propia por tipo de comprobante y punto de venta, sin huecos ni repetidos.
- **Venta (VEN-01, VEN-02 sin promociones, VEN-08, VEN-09):**
  - registro en una sola transacción de la venta, sus ítems y pagos, el egreso de stock, los movimientos de caja y de cuenta corriente y el comprobante;
  - precio final con IVA como dato maestro: cada ítem guarda precio final, neto, alícuota, IVA y costo del momento;
  - descuentos por ítem y por venta, siempre autorizados por un usuario con permiso (el propio vendedor si lo tiene, o un Gerente con su contraseña), registrando quién autorizó y auditados;
  - recargo por cuotas de tarjeta según el plan, que integra el total gravado y se distribuye entre los ítems con la alícuota de cada uno;
  - pagos combinados que deben sumar exactamente el total; los medios que pasan por caja exigen una caja abierta del usuario; el saldo a favor se descuenta de la cuenta corriente; la cuenta corriente sin crédito habilitado no genera deuda;
  - stock insuficiente rechazado salvo con el parámetro de stock negativo (auditado);
  - vendedor obligatorio (por defecto el usuario, si es vendedor) y cliente opcional (consumidor final).
- **Comprobante interno (VEN-04, RNF-07):** comprobante inmutable con número correlativo, rotulado "Documento no válido como factura", emitido por un emisor intercambiable (hoy el interno; ARCA en el futuro). Impresión desde el navegador en ticket de 80 mm y en A4.
- **Anulación (VEN-05):** con permiso del Gerente y motivo; no borra: marca la venta anulada, reingresa el stock, contraasienta caja y cuenta corriente y emite un comprobante interno de anulación. Una venta se anula una sola vez.
- **Venta rápida mobile-first:** búsqueda por nombre, SKU o código y escaneo con lector; elección de talle (solo con el parámetro de talles habilitado) y color con la foto del color; carrito; cliente con búsqueda y alta rápida; vendedor; pagos combinados con plan de cuotas y recargo visible; totales calculados por el servidor.
- **Consulta:** listado de ventas de la sucursal con filtros (fecha, vendedor, estado) y detalle; el Vendedor ve solo sus ventas. Historial de compras en la ficha del cliente.
- **Permisos:** `ventas.vender` (Vendedor, Cajero, Gerente), `ventas.ver_todas` (Cajero, Gerente), `ventas.autorizar_descuentos` y `ventas.anular` (Gerente), `puntos_venta.configurar` (solo Administrador). Navegación en el grupo "Ventas y caja".

## Capabilities

### New Capabilities
- `puntos-venta`: puntos de venta por sucursal y numeración correlativa de comprobantes por tipo y punto de venta.
- `ventas`: registro de ventas con cálculo de importes, descuentos autorizados, recargo por cuotas, pagos combinados, stock, caja y cuenta corriente; venta rápida; anulación por reversión; consulta y permisos de ventas.
- `comprobantes`: emisión de comprobantes inmutables desacoplada del emisor, comprobante interno rotulado, comprobante de anulación e impresión en ticket y A4.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. -->

## Impact

- **Código nuevo:** migraciones de `puntos_venta`, `ventas`, `venta_items`, `venta_pagos` y `comprobantes`; modelos y factories; enums `EstadoVenta` y `TipoComprobante`; excepción `VentaRechazada`; servicios `CalculadoraVenta`, `Ventas`, `NumeradorComprobantes`, interfaz `EmisorComprobante` y `EmisorInterno`; Form Requests, controladores, policy y vistas de ventas, comprobantes y puntos de venta; componente Alpine `ventaRapida`; seeder `PuntoVentaSeeder`.
- **Archivos existentes que cambian:** `Permiso`, `Rol`, `AppServiceProvider` (binding del emisor), `routes/web.php`, `layouts/navigation.blade.php`, `resources/js/app.js`, `DatabaseSeeder`, ficha de cliente (`ClienteController::show` y el parcial `clientes.partials.historial-ventas`), tests `DatosInicialesTest`, `NavegacionTest` y de la ficha del cliente.
- **Base de datos:** cinco tablas nuevas en `comercio` (migración autorizada); ninguna tabla existente cambia.
- **Dependencias:** ninguna nueva (el PDF sale de la impresión del navegador).
- **Fuera de alcance:** promociones y listas de precios (Fase 2), ventas en cuotas propias, cambios y devoluciones (próximo cambio), reservas y señas, facturación ARCA real, tope configurable de descuento sin autorización.
