# Design

## Context

- **Base existente:**
  - `fase-1-stock`: `MovimientosStock::registrar()` es la única puerta del stock (transacción obligatoria, `lockForUpdate` por variante y sucursal, orden ascendente de `variante_id`, `StockInsuficiente` si el parámetro de stock negativo está deshabilitado, origen morph y `reversaDe`). Los movimientos se guardan con usuario y dispositivo.
  - `fase-1-caja`: patrón de pantallas por sucursal activa (404 para registros de otra sucursal), ABM auditado con `getDirty()`, `Importes` para dinero en centavos.
  - `fase-1-catalogo`: `Variante` con `costo decimal(14,2)`, búsqueda por nombre, SKU o código exacto, `Variante::vendibles()`.
- Ver proposal.md para el alcance y `specs/` para el comportamiento.

## Goals / Non-Goals

**Goals:**
- Que la recepción sea la forma normal de cargar stock, con trazabilidad orden → recepción → movimiento de stock.
- Operación cómoda desde el celular del depósito: escaneo por teclado (lectores USB/Bluetooth escriben el código y Enter).
- Consistencia ante dos recepciones simultáneas de la misma orden.

**Non-Goals:**
- Facturas de compra, pagos y cuenta corriente de proveedores (COM-03). La recepción guarda la referencia del remito o factura como texto.
- Cerrar una orden con pendientes "por saldo" (queda `parcial`); se puede agregar luego sin cambiar el esquema.
- Proveedores por sucursal: son un catálogo común.

## Decisions

### D1. Esquema
- `proveedores`: `razon_social varchar(150)`, `cuit char(11) null unique` (solo dígitos; un único admite varios NULL), `contacto varchar(100) null`, `telefono varchar(50) null`, `email varchar(150) null`, `condiciones_pago varchar(255) null`, `activo bool` (índice), `timestamps`.
- `ordenes_compra`: `sucursal_id`, `proveedor_id`, `numero int unsigned` (único con `sucursal_id`), `fecha date`, `estado varchar(10)` (índice con `sucursal_id`), `observaciones varchar(500) null`, `user_id`, `timestamps`.
- `orden_compra_items`: `orden_compra_id`, `variante_id` (único con la orden), `cantidad_pedida int unsigned`, `cantidad_recibida int unsigned default 0`, `costo decimal(14,2)`, `timestamps`.
- `recepciones`: `sucursal_id`, `proveedor_id`, `orden_compra_id null`, `fecha date`, `referencia varchar(100) null`, `observaciones varchar(500) null`, `user_id`, `dispositivo varchar(36) null`, `anulada_at timestamp null`, `anulada_por_id null`, `motivo_anulacion varchar(255) null`, `created_at`; índices `(sucursal_id, fecha)` y `orden_compra_id`.
- `recepcion_items`: `recepcion_id`, `variante_id` (único con la recepción), `orden_compra_item_id null`, `cantidad int unsigned`, `costo decimal(14,2)`, `actualiza_costo bool`, `created_at`.
- Todas las FK `restrictOnDelete`. Las columnas `anulada_*` en la cabecera evitan una tabla de anulaciones: la anulación es única y su detalle son los movimientos de stock con `reversa_de_id`.
- *Alternativa descartada:* sumar ítems repetidos de una variante en filas distintas. Una fila por variante simplifica la reversión (un movimiento de ingreso por ítem) y el escaneo ya suma cantidades en el cliente.

### D2. Estados y numeración
- Enum `EstadoOrdenCompra`: `Borrador`, `Emitida`, `Parcial`, `Recibida`, `Anulada` con `etiqueta()`, `editable()` (borrador), `recibible()` (emitida o parcial) y `anulable()` (borrador o emitida).
- El estado después de recibir o anular una recepción se recalcula desde los ítems: todo recibido → `recibida`; algo recibido → `parcial`; nada → `emitida`. Así anular la única recepción devuelve la orden a `emitida` y vuelve a ser anulable.
- Número correlativo por sucursal: dentro de la transacción se bloquea la fila de la sucursal (`lockForUpdate`) y se toma `max(numero) + 1`. El único `(sucursal_id, numero)` respalda la regla en la base. El número se asigna al crear el borrador (no al emitir) para que el usuario pueda referirse a él desde el principio.

### D3. Servicios
- `OrdenesCompra`: `crear(datos, items)`, `actualizar(orden, datos, items)` (reemplaza los ítems; solo borrador), `emitir(orden)`, `anular(orden, motivo)`. Bloquean la orden con `lockForUpdate` y verifican el estado dentro de la transacción (`LogicException` → mensaje de validación).
- `RecepcionesCompra`:
  - `registrar(sucursal, proveedor, ?orden, fecha, referencia, observaciones, items)`: bloquea la orden, verifica `recibible()` y que cada cantidad ≤ pendiente **con los valores bloqueados** (dos recepciones simultáneas no pueden superar lo pedido), crea la recepción y sus ítems, registra los ingresos con `MovimientosStock` en orden ascendente de `variante_id`, actualiza `cantidad_recibida` y el estado, y actualiza costos marcados (auditoría `variante.costo_actualizado` con `costo` antes/después y `recepcion_id`).
  - `anular(recepcion, motivo)`: bloquea la recepción y verifica que no esté anulada; bloquea la orden si tiene; por cada ítem (ascendente por variante) registra un `Egreso` por la cantidad con `reversaDe` el ingreso original y origen la recepción; descuenta la cantidad recibida y recalcula el estado; marca la anulación y audita `compras.recepcion_anulada`. `StockInsuficiente` revierte toda la transacción y el controlador lo muestra como error de validación.
  - Orden de bloqueos: orden → filas de stock (ascendente). La anulación bloquea antes la recepción, que `registrar()` nunca bloquea, así que no hay ciclos.
- La validación de forma (tipos, rangos, existencia, variante repetida, proveedor activo) está en Form Requests; las reglas que dependen de filas bloqueadas (pendiente, estado) se revalidan en el servicio y vuelven como errores del campo correspondiente.

### D4. Inmutabilidad
- `RecepcionItem` lanza `LogicException` en `updating` y `deleting`.
- `Recepcion` lanza en `deleting` y en `updating` salvo la transición única de anulación (solo cambian `anulada_at`, `anulada_por_id` y `motivo_anulacion`, y antes no estaba anulada).
- Las órdenes no son inmutables (cambian de estado y de cantidad recibida), pero solo el servicio las modifica y nunca se borran.

### D5. Excedentes
- Se rechaza recibir más de lo pendiente contra la orden. El excedente se registra como recepción sin orden del mismo proveedor. *Alternativa descartada:* aceptar excedentes y marcar la orden como recibida; mezcla lo pedido con lo no pedido y complica los reportes de pendientes.

### D6. Anulación de recepciones
- Requiere `compras.gestionar` (Gerente): es una corrección que mueve stock hacia abajo. *Alternativa descartada:* permitirla con `compras.recibir`; Depósito podría deshacer ingresos sin supervisión.
- No revierte costos actualizados: el costo es un dato del catálogo y pudo cambiar después por otra vía. La pantalla lo advierte.
- El motivo es obligatorio y queda en los egresos de stock y en la auditoría.

### D7. CUIT
- `App\Services\Cuit` (normalizar, esValido, digitoVerificador, formatear) y la regla `CuitValido`. El Form Request normaliza en `prepareForValidation` (saca guiones y espacios) para que el único funcione con o sin guiones. Se guarda sin guiones y se muestra `XX-XXXXXXXX-X`.
- Prefijos admitidos: 20, 23, 24, 25, 26, 27 (personas), 30, 33, 34 (empresas). Si el cálculo da 10 el CUIT es inválido (AFIP en ese caso asigna otro prefijo).

### D8. Pantallas y componente Alpine
- Rutas bajo `compras.` con modelos `{orden}` y `{recepcion}`; los controladores filtran por sucursal activa y responden 404 a lo ajeno (como Caja).
- Buscador de variantes: `GET compras/variantes?q=` devuelve JSON (máx. 20) con id, producto, descripción, SKU, código y costo de variantes vendibles; con `exacto=1` busca solo SKU o código de barras exacto (escaneo). Requiere `compras.ver`.
- Un componente Alpine `itemsCompra` (`resources/js/compras/items-compra.js`) maneja la lista de ítems de órdenes y recepciones: agregar desde el buscador, escanear (suma 1 o agrega), quitar, y en recepciones contra orden limitar a las variantes de la orden y mostrar lo pendiente. Los datos iniciales se pasan con `@js()` (JSON escapado) y se muestran con `x-text`, nunca como HTML.
- Sin JavaScript el formulario no permite agregar ítems; se acepta porque toda la aplicación usa Alpine y el uso es desde navegadores modernos.

### D9. Navegación
- Escritorio: Inicio + grupos con `x-dropdown` (`align="left"`); el disparador es un `<button>` con `aria-expanded` y `aria-haspopup`, y se marca activo si alguna ruta del grupo lo está. Se agrega `@keydown.escape.window` al componente `dropdown` (también beneficia al menú de usuario).
- Móvil: secciones con `<p id>` como título y `role="group" aria-labelledby`.
- La visibilidad de cada grupo se calcula en la vista con los mismos `@can` de sus entradas.
- Los tests de navegación siguen afirmando por URL de cada entrada (presentes en el HTML aunque el desplegable esté cerrado) y se agregan afirmaciones por grupo.

## Risks / Trade-offs

- [Una orden con faltantes que nunca llegarán queda `parcial` para siempre] → Fuera de alcance; se puede agregar "cerrar con pendientes" sin cambiar el esquema.
- [Anular una recepción no restaura el costo anterior] → Advertencia en la pantalla y auditoría del costo con la recepción para corregirlo a mano.
- [El buscador JSON expone datos de variantes (incluido el costo) a quien tiene `compras.ver`] → Solo Gerente y Depósito, que ya ven el costo en compras; no se expone a Vendedor ni Cajero.
- [Los lectores de código que no envían Enter] → El campo de escaneo también tiene botón "Agregar".

## Migration Plan

- `php artisan migrate` crea las cinco tablas (sin cambios en tablas existentes) y `RolesYPermisosSeeder` agrega los permisos. Rollback: `migrate:rollback` del lote, sin datos previos afectados.
