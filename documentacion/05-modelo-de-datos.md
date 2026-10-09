# 05 — Modelo de datos (Laravel 12, MySQL/MariaDB)

**Convenciones:**
- `id` bigint, `timestamps`, importes `decimal(14,2)`, cantidades `integer`.
- `sucursal_id` en toda tabla operativa.
- **Dos fechas** en toda tabla operativa: `fecha` (fecha de negocio, la que se usa en reportes, cierres y vencimientos) y `created_at` (momento técnico de registro). Normalmente coinciden; difieren cuando se carga una operación con fecha distinta (por ejemplo, una venta registrada al día siguiente).
- `dispositivo` en toda operación de dinero (identificador del equipo y navegador desde el que se registró).
- Los registros de dinero y stock no se editan: se corrigen con contraasiento (`reversa_de_id`).

## Seguridad y configuración
- `sucursales` (nombre, domicilio, activa)
- `users` (nombre, email, password, activo)
- `sucursal_user` (user_id, sucursal_id)
- `roles`, `permissions`, `model_has_roles`: con spatie/laravel-permission en **modo teams**, donde el *team* es la sucursal (`team_id` = `sucursal_id`). Así un usuario puede tener un rol distinto en cada sucursal. Se activa desde la primera migración.
- `parametros` (clave, valor): multi-sucursal, stock negativo, facturación
- `auditorias` (user_id, accion, modelo, modelo_id, antes, despues, ip, dispositivo)

## Catálogo
- `categorias` (botas, sandalias, zapatos, zapatillas), `marcas`, `temporadas`, `colores`
- `alicuotas_iva` (nombre, porcentaje)
- `productos` (nombre, categoria_id, marca_id, temporada_id, alicuota_iva_id, activo)
- `variantes` (producto_id, color_id, **talle**, **sku** único, codigo_barras único, costo, **precio_final**, activo). Única por producto, color y talle. El precio de la lista base es el precio final con IVA; el neto se calcula.
- `listas_precio` (nombre, es_base, activa)
- `lista_precio_items` (lista_precio_id, variante_id, precio_final). Única por lista y variante. Si una variante no figura en la lista, se usa el precio base.
- `producto_fotos` (producto_id, color_id nullable, ruta, ruta_miniatura, es_principal, orden, ancho, alto, bytes). Hasta 10 por producto, una principal. Los archivos se guardan en el disco privado y se sirven con control de acceso; el navegador los achica antes de subirlos.

## Stock
- `stock` (variante_id, sucursal_id, cantidad, minimo). Única por variante y sucursal. `cantidad` puede ser negativa si el parámetro lo permite.
- `movimientos_stock` (variante_id, sucursal_id, fecha, tipo, cantidad con signo, saldo, motivo, origen_type/origen_id, user_id). Es el kardex.
- `transferencias` y `transferencia_items` (origen, destino, estado). Modeladas pero desactivadas.
- `inventarios` (sucursal_id, fecha, estado, user_id) e `inventario_items` (variante_id, contado, sistema, diferencia)

## Compras
- `proveedores` (razon_social, cuit, contacto, condiciones_pago)
- `ordenes_compra` (sucursal_id, proveedor_id, fecha, estado) y `orden_compra_items` (cantidad_pedida, cantidad_recibida, costo)
- `recepciones` (sucursal_id, fecha) y `recepcion_items`: generan `movimientos_stock`
- `facturas_compra` (proveedor_id, sucursal_id, numero, fecha, total, vencimiento)
- `pagos_proveedor` (sucursal_id, proveedor_id, fecha, medio_pago_id, importe, caja_sesion_id, user_id, dispositivo) e `imputaciones_pago_proveedor` (pago_id, factura_id, importe)

## Clientes y vendedores
- `clientes` (nombre, dni_cuit, telefono, email, talle_habitual, condicion_iva)
- `vendedores` (user_id, activo)
- `metas_vendedor` (vendedor_id, periodo, monto)
- `reglas_comision` (vendedor_id o categoria_id, porcentaje, escala_json)
- `comisiones` (vendedor_id, venta_id, importe, estado)

## Ventas
- `puntos_venta` (sucursal_id, numero, descripcion, activo). Única por número.
- `ventas` (sucursal_id, fecha, cliente_id, vendedor_id, caja_sesion_id, lista_precio_id, estado, subtotal_neto, iva, descuento, **descuento_autorizado_por** (user_id, nullable), recargo, total, anulada_por, user_id, dispositivo). No se borran.
- `venta_items` (variante_id, cantidad, precio_final, precio_neto, alicuota, iva, descuento, **descuento_autorizado_por** (user_id, nullable), **promocion_id** (nullable), **costo_unitario** como foto del costo)
- `venta_pagos` (venta_id, medio_pago_id, plan_cuota_id, importe, recargo, referencia, lote, cupon)
- `comprobantes` (sucursal_id, venta_id nullable, devolucion_id nullable, tipo, punto_venta_id, numero, fecha, cae, cae_vto, emisor, datos_json). Única por `(tipo, punto_venta_id, numero)`. Inmutables una vez emitidos.
- `devoluciones` (sucursal_id, venta_id, fecha, motivo, resolucion: nota_credito, saldo o reembolso, user_id, dispositivo) y `devolucion_items`
- `promociones` (tipo, valor, vigencia, alcance)
- `reservas` (sucursal_id, cliente_id, fecha, seña, vence_el, estado, caja_movimiento_id de la seña) y `reserva_items`

## Medios de pago y caja
- `medios_pago` (nombre, impacta_caja, dias_acreditacion, requiere_referencia, activo)
- `planes_cuota` (medio_pago_id, cuotas, recargo_pct)
- `cajas` (sucursal_id, nombre)
- `caja_sesiones` (caja_id, user_id, apertura, fondo_inicial, cierre, esperado, contado, diferencia, justificacion)
- `caja_movimientos` (caja_sesion_id, fecha, tipo, medio_pago_id, importe, motivo, origen_type/origen_id, reversa_de_id, user_id, dispositivo)
- `acreditaciones` (venta_pago_id, fecha_estimada, fecha_real, comision, neto). Para conciliar.

## Cuentas corrientes de clientes
- `cuentas_corrientes` (cliente_id, **admite_credito**, limite_credito, bloqueada, saldo cacheado)
- `cc_movimientos` (cuenta_id, sucursal_id, fecha, tipo debe/haber, importe, origen_type/origen_id, reversa_de_id, vencimiento, user_id, dispositivo)
- `planes_cuotas_cliente` (venta_id, cantidad, interes_pct) y `cuotas` (numero, vencimiento, importe, pagado, estado)
- `cobranzas` (sucursal_id, cliente_id, fecha, medio_pago_id, importe, caja_sesion_id, user_id, dispositivo)
- `cobranza_imputaciones` (cobranza_id, cuota_id o cc_movimiento_id, importe)

## Relaciones clave
- Producto 1–N Variante. **La variante es la unidad de stock y de venta.**
- Variante y Sucursal 1–1 en `stock`, con historial en `movimientos_stock`.
- Venta 1–N Items, 1–N Pagos, 0–1 Comprobante.
- Venta con medio "cuenta corriente" genera `cc_movimientos` (debe) y, si hay cuotas, `cuotas`.
- Cobranza imputa a cuotas o movimientos y genera `caja_movimientos`.
- **Saldo a favor:** vive siempre en la cuenta corriente del cliente, como `cc_movimientos` de tipo haber. Todo cliente con saldo a favor tiene cuenta corriente: se crea automáticamente con `admite_credito = false` si no existía. Una cuenta sin crédito habilitado no admite ventas a cuenta, pero sí usar su saldo a favor como medio de pago.
- Nota de crédito: es un `comprobante` vinculado a la `devolucion`; su importe se acredita como haber en la cuenta corriente.

## Decisiones de diseño
- Venta, stock y caja se registran en **una sola transacción** con `lockForUpdate` sobre `stock`. El bloqueo mantiene el saldo consistente ante ventas simultáneas; si el parámetro de stock negativo está desactivado, además impide la sobreventa.
- Saldos cacheados (`stock.cantidad`, `cuentas_corrientes.saldo`) se recalculan desde los movimientos. Los movimientos son la fuente de verdad.
- **Precio final como dato maestro:** se fija el precio final con IVA (lo que ve la clienta) y se calculan neto e IVA a partir de él. Los items guardan precio final, neto, alícuota, IVA y costo del momento, así los reportes de margen no cambian si luego se actualizan precios.
- **El recargo por cuotas integra el total gravado:** se distribuye entre los items y se le aplica la alícuota de IVA de cada uno, de modo que el comprobante cierre igual cuando se habilite ARCA.
- Índices en `(sucursal_id, fecha)` en todas las tablas operativas, y además en `variante_id`, `vendedor_id`, `cliente_id` y `codigo_barras`.
