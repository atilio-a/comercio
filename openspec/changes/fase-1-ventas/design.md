# Design

## Context

- **Puertas únicas existentes** (leer su PHPDoc): `MovimientosStock` (transacción obligatoria, `lockForUpdate` por variante en orden ascendente de `variante_id`, `StockInsuficiente`, auditoría `stock.negativo`, `reversaDe`), `MovimientosCaja` (sesión abierta del usuario, `CajaNoAbierta`, `contraasentar()` solo en sesiones abiertas), `CuentasCorrientes` (`debitar()` consume saldo a favor y lanza `CreditoNoDisponible` si generaría deuda sin crédito; `contraasentar()`), `CalculadoraIva::desglosar()` e `Importes` (centavos enteros), `Vendedores::disponibles()`, `RegistradorAuditoria`, `Parametros`.
- **Orden de bloqueo** que fijaron esos servicios: stock (por `variante_id` ascendente) → caja → cuenta corriente. Este cambio agrega al final el punto de venta (numeración).
- `medios_pago.tipo` distingue efectivo, tarjetas, transferencia, QR, cuenta corriente y saldo a favor; `planes_cuota.recargo_pct` es `decimal(5,2)`.
- La búsqueda y el alta rápida de clientes en JSON (`clientes.buscar`, `clientes.rapido`) ya existen; `Producto::fotoPara($color)` resuelve la foto de un color.
- No hay GD: cualquier PDF del servidor debería evitar imágenes raster.
- Ver proposal.md para el alcance y `specs/` para el comportamiento.

## Goals / Non-Goals

**Goals:**
- Un único servicio de cálculo, puro y probado, que usan tanto la previsualización como el registro.
- Venta en menos de 2 s: pocas consultas, sin N+1, una transacción corta.
- Dejar listo el punto de extensión para ARCA (`EmisorComprobante`) y la numeración por tipo y punto de venta.

**Non-Goals:**
- Promociones y listas de precios (`promocion_id` queda nullable y sin uso; `lista_precio_id` se agregará en Fase 2).
- Tope configurable de descuento sin autorización (ver D5).
- Cambios, devoluciones, notas de crédito fiscales y reservas.
- Vuelto en efectivo: el pago en efectivo se carga por el importe exacto.

## Decisions

### D1. Esquema
- `puntos_venta`: `sucursal_id` FK, `numero` unsigned int **único global** (como el punto de venta de ARCA, que es por CUIT), `descripcion varchar(100) null`, `activo bool`, `timestamps`; índice `(sucursal_id, activo)`.
- `ventas`: `sucursal_id`, `fecha date`, `cliente_id null`, `vendedor_id`, `caja_sesion_id null` (null si ningún pago pasa por caja), `punto_venta_id`, `estado varchar(10)` (`confirmada`/`anulada`), `subtotal_neto`, `iva`, `descuento` (ítems + general), `recargo`, `total` en `decimal(14,2)`, `descuento_autorizado_por null` (FK users), `anulada_por null` (FK users), `anulada_at timestamp null`, `motivo_anulacion varchar(200) null`, `user_id`, `dispositivo varchar(36) null`, `timestamps`. Índices `(sucursal_id, fecha)`, `vendedor_id`, `cliente_id`, `(sucursal_id, estado)`.
- `venta_items`: `venta_id`, `variante_id`, `cantidad` unsigned int, `precio_final` (unitario de lista), `precio_neto` (unitario, desglosado del precio final), `alicuota decimal(5,2)`, `descuento` (propio del ítem, por la línea), `descuento_autorizado_por null`, `descuento_venta` (parte del descuento general), `recargo` (parte del recargo por cuotas), `total`, `neto`, `iva` (de la línea, desglosados de `total`), `promocion_id null` (sin FK: la tabla llega en Fase 2), `costo_unitario`, `created_at`. Índice `variante_id`.
  - Doc 05 nombra `precio_neto` e `iva` por ítem; se agregan `descuento_venta`, `recargo`, `total` y `neto` porque con descuento general y recargo distribuidos el neto unitario ya no reconstruye el total gravado de la línea, que es lo que pide ARCA por ítem.
- `venta_pagos`: `venta_id`, `medio_pago_id`, `plan_cuota_id null`, `cuotas` y `recargo_pct` (foto del plan), `importe` (lo que cubre de la venta, sin recargo), `recargo`, `referencia varchar(100) null`, `lote varchar(20) null`, `cupon varchar(20) null`, `created_at`. Lo cobrado por el medio es `importe + recargo`.
- `comprobantes`: `sucursal_id`, `venta_id null` FK, `devolucion_id` unsigned bigint null **sin FK** (la tabla llega con el cambio de devoluciones, que agregará la FK), `tipo varchar(30)`, `punto_venta_id`, `numero` unsigned int, `fecha date`, `cae varchar(20) null`, `cae_vto date null`, `emisor varchar(20)`, `comprobante_anulado_id null` (FK a sí misma, única), `datos_json json`, `created_at`. Único `(tipo, punto_venta_id, numero)`; índices `(sucursal_id, fecha)` y `venta_id`.
- Todas las FK `restrictOnDelete`. Modelos `Venta`, `VentaItem`, `VentaPago`, `Comprobante` con `LogicException` en `deleting`; ítems, pagos y comprobantes también en `updating`; `Venta` solo admite el cambio `confirmada → anulada` de las columnas de anulación (D9).

### D2. Cálculo (`CalculadoraVenta`, puro, en centavos)
Entrada: líneas (precio final unitario y alícuota tomados de la variante en el servidor, cantidad, descuento propio), descuento general y pagos (importe cubierto y `recargo_pct`).
1. `bruto = precio × cantidad`; `0 ≤ descuento ≤ bruto`; `subtotal = bruto − descuento`.
2. `S = Σ subtotal`; `0 ≤ descuento general ≤ S`. Se distribuye en proporción a `subtotal` con división entera hacia abajo; el **último ítem** recibe el resto (lo pidió el usuario: centavos cerrados en el último). `T = S − descuento general` debe ser > 0.
3. Recargo de cada pago = `round_half_up(importe × recargo_pct / 100)` con enteros (porcentaje en centésimos). `R = Σ recargos`, distribuido igual que el descuento general sobre `subtotal − descuento_venta`.
4. `total ítem = subtotal − descuento_venta + recargo`; `neto` e `iva` con `CalculadoraIva::desglosar(total, alícuota)`, así `neto + iva = total` por ítem y en la venta.
5. Pagos: cada importe > 0 y `Σ importes = T`; si no, `VentaRechazada` con lo que falta o sobra. La previsualización llama a la misma clase sin exigir esta regla y devuelve lo pendiente.
- El reparto proporcional (`monto × base / total`) usa `Brick\Math\BigInteger`, que ya es dependencia directa de `laravel/framework`: con enteros de 64 bits el producto se desborda en ventas grandes (más de unos 30 millones de pesos). No es una dependencia nueva.
- *Alternativa descartada:* calcular en JS. Duplicaría reglas y el redondeo; el cliente solo arma el pedido y muestra lo que devuelve `POST /ventas/previsualizar`.
- *Alternativa descartada:* largest-remainder para repartir centavos. Más "justo", pero el criterio pedido es cerrar en el último ítem y es más fácil de auditar.

### D3. Servicio `Ventas` (registro y anulación)
`registrar(Sucursal, PuntoVenta, Vendedor, ?Cliente, array $items, string $descuentoGeneral, array $pagos, ?User $autorizador): Venta` en una `DB::transaction`:
1. Resuelve el emisor (D7) antes de grabar: con facturación electrónica habilitada falla sin efectos.
2. Carga variantes vendibles (con producto, alícuota y color) y planes; calcula con D2.
3. Crea la venta, ítems y pagos.
4. Stock: egresos por ítem en orden de `variante_id` con origen la venta (`StockInsuficiente`).
5. Caja: un ingreso `Venta` por cada pago con medio que no sea cuenta corriente ni saldo a favor, por `importe + recargo`, en la sesión abierta del usuario (`CajaNoAbierta`); la venta guarda esa sesión.
6. Cuenta corriente: débito por pago con concepto "Venta N° id"; para saldo a favor primero se bloquea la cuenta y se verifica que el saldo a favor alcance (si no, `VentaRechazada`), porque `debitar()` aceptaría generar deuda en una cuenta con crédito.
7. Emite el comprobante (D6, D7) y audita la autorización de descuentos (`ventas.descuento_autorizado`).
- Errores de negocio: excepción `VentaRechazada(mensaje, campo)` (mismo patrón que `CompraRechazada`). El controlador traduce `VentaRechazada`, `StockInsuficiente`, `CajaNoAbierta` y `CreditoNoDisponible` a errores de validación (422 en JSON).

### D4. Caja: qué medios pasan por caja
Todos los medios salvo cuenta corriente y saldo a favor (`TipoMedioPago::admiteMovimientoManual()`) generan un movimiento de caja, aunque no impacten en caja física (tarjetas, transferencias, QR). Así el arqueo muestra el esperado de cada medio (el cierre ya lo guarda sin contarlo) y la futura conciliación parte de la sesión. Consecuencia: cobrar con cualquiera de esos medios exige caja abierta. *Alternativa descartada:* exigir caja solo para `impacta_caja`; dejaría cobros con tarjeta fuera de todo cierre.

### D5. Autorización de descuentos
- **Siempre**: cualquier descuento (> 0) requiere un autorizante con `ventas.autorizar_descuentos`. *Alternativa descartada:* tope configurable; los parámetros del sistema son solo sí/no y el doc 02 dice "descuentos con autorización" sin tope. Se puede agregar luego sin cambiar el esquema.
- Si el usuario tiene el permiso, se autoriza a sí mismo. Si no, el formulario pide email y contraseña del autorizante; el Form Request verifica usuario activo, asignado a la sucursal, contraseña (`Hash::check`) y permiso con `Gate::forUser()` en la sucursal activa (el team de spatie ya es la sucursal, y el Administrador pasa por `Gate::before`). Intentos fallidos limitados con `RateLimiter` (5 por minuto por usuario e IP).
- La venta guarda el autorizante del descuento general y cada ítem el del suyo; se audita `ventas.descuento_autorizado` con autorizante, descuentos por ítem y general.

### D6. Numeración (`NumeradorComprobantes`)
`siguiente(PuntoVenta, TipoComprobante): int` bloquea la fila del punto de venta (`lockForUpdate`) y toma `MAX(numero) + 1` para el tipo. El bloqueo serializa emisiones del mismo punto de venta y el índice único es la red de seguridad. Como se ejecuta dentro de la transacción de la venta, un rollback no deja huecos. Se bloquea **al final** (después de stock, caja y cuenta) para respetar el orden de bloqueo y retener la fila el menor tiempo posible. *Alternativa descartada:* tabla de contadores; agrega una fila más que mantener sin ventaja con un solo punto de venta por sucursal.

### D7. `EmisorComprobante`
- Interfaz `App\Services\Comprobantes\EmisorComprobante` (junto a `EmisorInterno` y `EmisorNoDisponible`, sin crear una carpeta base nueva) con `emitirVenta(Venta): Comprobante`, `emitirAnulacion(Venta, Comprobante $original, string $motivo): Comprobante` y `nombre(): string`.
- `EmisorInterno` emite `TipoComprobante::VentaInterna` y `AnulacionInterna`, con `emisor = 'interno'`, sin CAE, y guarda en `datos_json` la foto completa del documento (comercio = `config('app.name')`, sucursal, punto de venta, número formateado `00001-00000001`, fecha y hora, cliente, vendedor, ítems, pagos, totales con IVA discriminado y la leyenda "Documento no válido como factura").
- Binding en `AppServiceProvider::register()`: con `FacturacionElectronica` deshabilitado resuelve `EmisorInterno`; habilitado resuelve `EmisorNoDisponible`, que rechaza toda emisión con `VentaRechazada` ("La facturación electrónica todavía no está disponible"). Hoy el parámetro no se puede habilitar desde la pantalla; el emisor ARCA reemplazará a `EmisorNoDisponible` sin tocar ventas.
- *Alternativa descartada:* emitir internos aunque el parámetro esté habilitado; el usuario creería estar facturando.

### D8. Comprobante e impresión (sin dependencias nuevas)
- Vista HTML independiente (`comprobantes.imprimir`, sin navegación) con `?formato=ticket|a4`, CSS de impresión (`@page { size: 80mm auto }` o `A4`), botón "Imprimir" y la leyenda. Se renderiza **solo** desde `datos_json`, así una reimpresión no cambia aunque cambien precios o clientes.
- **PDF:** con "Imprimir → Guardar como PDF" del navegador. *Alternativa descartada:* `barryvdh/laravel-dompdf`; agrega una dependencia y su mantenimiento (y sin GD no puede llevar imágenes raster) para algo que el navegador ya hace, incluso en el celular. Si más adelante se necesita adjuntar el PDF por WhatsApp o email desde el servidor, se evalúa en ese cambio.

### D9. Anulación
`Ventas::anular(Venta, string $motivo)` en una transacción: bloquea la venta (si ya está anulada → `VentaRechazada`); reingresa stock por ítem (orden de `variante_id`) con `reversaDe` del egreso; contraasienta cada movimiento de caja de la venta (`MovimientosCaja::contraasentar`, que exige la sesión **abierta**: si ya cerró, `CajaNoAbierta` → "La caja de la venta ya se cerró: registrá un cambio o devolución"); contraasienta cada débito de cuenta corriente; emite el comprobante de anulación; marca `estado = anulada`, `anulada_por`, `anulada_at`, `motivo_anulacion`; audita `ventas.anulada`.
- *Por qué comprobante de anulación y no marcar el original:* el comprobante es inmutable; con ARCA la anulación será una nota de crédito, que también es un comprobante nuevo que referencia al original.
- *Por qué exigir la sesión abierta:* el diseño de caja (fase-1-caja D3) ya decidió no mover el efectivo de hoy por un error de un turno cerrado. Pasado el cierre, la reversión es una devolución (próximo cambio).

### D10. Pantallas y rutas (prefijo `/ventas`, sucursal activa)
- `GET /ventas` listado (filtros `desde`, `hasta`, `vendedor_id` solo con `ventas.ver_todas`, `estado`), `GET /ventas/create` venta rápida, `POST /ventas` (JSON → 201 con la URL del detalle; formulario → redirección), `POST /ventas/previsualizar` (JSON), `GET /ventas/variantes` (búsqueda JSON), `GET /ventas/{venta}` detalle, `POST /ventas/{venta}/anular`, `GET /comprobantes/{comprobante}` impresión, `/ventas/puntos-venta` ABM (index, create, store, edit, update).
- `VentaPolicy`: `view` = mismo sucursal (si no, 404 en el controlador) y (`ventas.ver_todas` o vendedor/registrante); `anular` = `ventas.anular` y confirmada. Scope `Venta::visiblesPara(User)` reutilizado en listado y ficha del cliente.
- **Búsqueda JSON** (`ventas.vender`): agrupa por producto las variantes vendibles que coinciden por nombre, SKU o código (o solo SKU/código con `exacto=1`), con precio, stock de la sucursal y foto por color (`fotoPara`, fotos precargadas). Con el parámetro de talles deshabilitado no envía la clave `talle`; si un color tiene varias variantes se distinguen por su descripción (que muestra el talle guardado, según el diseño de talles configurables).
- **Componente Alpine `ventaRapida`** (`resources/js/ventas/venta-rapida.js`): buscador con debounce, campo de escaneo (código + Enter), selección talle → color con foto, carrito, descuentos, cliente (búsqueda y alta rápida), vendedor, pagos con plan de cuotas, previsualización con debounce y envío con `fetch` (CSRF por meta tag). Los datos iniciales se pasan con `Js::from`.
- Vistas con tarjetas y `x-app-layout`; la navegación agrega "Vender", "Ventas" y "Puntos de venta" al grupo "Ventas y caja".

### D11. Permisos y seeders
- `Permiso`: `VentasVender`, `VentasVerTodas`, `VentasAutorizarDescuentos`, `VentasAnular`, `PuntosVentaConfigurar`; matriz según proposal. `puntos_venta.configurar` no se asigna (Administrador por `Gate::before`), igual que `caja.configurar`.
- `PuntoVentaSeeder`: para cada sucursal activa sin puntos de venta crea uno con el menor número libre; se agrega a `DatabaseSeeder` después de `CajaSeeder`.

## Risks / Trade-offs

- [Contraseña del Gerente tipeada en el celular del vendedor] → se envía por HTTPS en producción, no se guarda, se limita por intentos y queda auditado quién autorizó. Mejora futura: PIN o aprobación remota.
- [Anulación imposible tras el cierre de caja] → es deliberado (D9); el mensaje orienta a la devolución del próximo cambio.
- [Bloqueo de la fila del punto de venta] → serializa la emisión por punto de venta; con un mostrador por punto de venta y transacciones cortas no es un cuello de botella. Si lo fuera, se agregan puntos de venta.
- [Distribución que cierra centavos en el último ítem] → el último ítem puede llevar hasta algunos centavos de diferencia de proporción; el total y el IVA por ítem siguen cerrando exactos.
- [Columna `devolucion_id` sin FK] → la agrega el cambio de devoluciones con una migración nueva.

## Migration Plan

- `php artisan migrate` crea las cinco tablas; `RolesYPermisosSeeder` y `PuntoVentaSeeder` cargan permisos y el punto de venta inicial. Son idempotentes.
- Rollback: `migrate:rollback` del lote borra las tablas nuevas (solo si no hay ventas reales).
