# Design

## Context

- **Base existente:**
  - `fase-1-base`: servicios `SucursalActiva`, `Parametros` (con `Parametro::StockNegativo`, deshabilitado por defecto) y `RegistradorAuditoria` (se usa dentro de `DB::transaction`); `IdentificarDispositivo` deja el UUID del navegador en la solicitud; los permisos se definen con `Permiso` y `Rol::permisos()` más el seeder idempotente.
  - `fase-1-catalogo`: `Variante` con el scope `vendibles()` y `descripcion()`, la búsqueda de productos por nombre, SKU o código exacto, y `Formato`.
  - Tests en PHPUnit sobre MariaDB `comercio_testing`.
- **Doc 05:**
  - `stock (variante_id, sucursal_id, cantidad, minimo)`, única por variante y sucursal;
  - `movimientos_stock (variante_id, sucursal_id, fecha, tipo, cantidad con signo, saldo, motivo, origen_type/origen_id, user_id)`;
  - convención de contraasientos (`reversa_de_id`);
  - "venta, stock y caja en una sola transacción con `lockForUpdate` sobre `stock`";
  - saldos cacheados que se recalculan desde los movimientos.
- Ver proposal.md para el alcance y `specs/stock/spec.md` para el comportamiento.

## Goals / Non-Goals

**Goals:**
- Una sola puerta para mover stock (`MovimientosStock`), que Ventas, Compras y Cambios llamen dentro de su propia transacción.
- Saldo consistente con operaciones simultáneas y verificable contra el kardex.
- Carga inicial cómoda desde el celular: un formulario por producto con todas sus variantes.

**Non-Goals:**
- Pantallas de ingreso y egreso manual (decisión del usuario).
- Inventario físico como sesión de conteo con diferencias (STK-03).
- Valorización, alertas y reposición.
- Transferencias reales.

## Decisions

### D1. Esquema
- `stock`:
  - `id`, `variante_id` FK, `sucursal_id` FK, `cantidad int` (con signo) default 0, `minimo int unsigned` default 0, `timestamps`;
  - índice único `(variante_id, sucursal_id)` e índice `(sucursal_id, cantidad)`.
- `movimientos_stock`:
  - `id`, `variante_id` FK, `sucursal_id` FK, `fecha date`;
  - `tipo varchar(20)` (valores del enum D2), `cantidad int` (con signo, distinta de 0), `saldo int`;
  - `motivo varchar(255) null`, `origen_type`/`origen_id` (morph nullable), `reversa_de_id` FK a sí misma, nullable;
  - `user_id` FK nullable (null en procesos de consola), `dispositivo varchar(36) null` y solo `created_at`;
  - índices `(sucursal_id, fecha)`, `(variante_id, sucursal_id, id)` y `(origen_type, origen_id)`.
- Todas las FK son `restrictOnDelete`.
- *Alternativa descartada:* calcular el stock como `SUM(cantidad)` en cada consulta. Es más lento al vender y el doc 05 ya prevé el saldo cacheado.

### D2. Enum `TipoMovimientoStock`
- Casos: `Ingreso`, `Egreso`, `Ajuste` y `Transferencia`, con valores en minúscula.
- `signo()` define el signo esperado: el ingreso es positivo, el egreso negativo, y el ajuste o la transferencia pueden tener cualquiera de los dos.
- `etiqueta()` da el texto para la interfaz.

### D3. Servicio `MovimientosStock`, la API para los módulos
```
registrar(Variante, Sucursal, TipoMovimientoStock, int $cantidad, ?string $motivo,
          ?Model $origen = null, ?CarbonInterface $fecha = null, ?MovimientoStock $reversaDe = null): MovimientoStock
ajustarAConteo(Variante, Sucursal, int $contado, string $motivo, CarbonInterface $fecha): ?MovimientoStock
actualizarMinimo(Variante, Sucursal, int $minimo): void
```
`registrar()`:
1. Exige una transacción abierta; si no la hay, lanza `LogicException`, porque quien llama decide los límites.
2. Rechaza `Transferencia` con `TransferenciaNoHabilitada`, rechaza cantidad 0 y valida el signo según el tipo.
3. Asegura la fila de stock con `insertOrIgnore` y la bloquea con `lockForUpdate`, siempre en orden de `variante_id` cuando quien llama mueve varias variantes, para evitar deadlocks.
4. Calcula el saldo nuevo. Si es negativo y el movimiento es un egreso:
   - con `StockNegativo` deshabilitado, lanza `StockInsuficiente` (excepción de dominio con el disponible), que el controlador convierte en error de validación;
   - si está habilitado, audita `stock.negativo`.
5. Inserta el movimiento con `user_id = Auth::id()` y el dispositivo de la solicitud, y actualiza `stock.cantidad`.

`ajustarAConteo()` calcula `contado − cantidad` con el stock bloqueado: si da 0 devuelve `null`, y si no, registra un `Ajuste` y audita `stock.ajuste` (anterior, nuevo, motivo). El ajuste nunca queda en negativo, porque el conteo es ≥ 0, así que no aplica la regla de stock negativo.

- *Alternativa descartada:* observers sobre `MovimientoStock` que actualicen el saldo. Esconden el bloqueo y el orden, que son críticos.

### D4. Modelos inmutables
- `MovimientoStock` (tabla `movimientos_stock`, `UPDATED_AT = null`) lanza `LogicException` en `updating` y `deleting`, como `Auditoria`.
- `Stock` solo se modifica a través del servicio. No hay `fillable` para `cantidad` desde formularios.

### D5. Pantallas y rutas (bajo `/stock`, con la sucursal activa)
- `GET /stock`: listado por variante con join a `stock` (`LEFT JOIN`, para mostrar 0 cuando falta la fila).
  - Búsqueda: nombre, SKU o código exacto; filtros: categoría, `bajo_minimo` y estado (vendibles por defecto).
  - Paginado de 50, en tarjetas.
- `GET /stock/productos/{producto}/ajuste` y `PUT /stock/productos/{producto}/ajuste`:
  - formulario con todas las variantes del producto, con columnas actual, contado (vacío = sin cambio) y mínimo;
  - motivo y fecha comunes;
  - `AjusteStockRequest` valida `contado.*` (entero ≥ 0, opcional), `minimo.*` (entero ≥ 0), `motivo` (obligatorio si hay algún contado, máximo 255) y `fecha` (`before_or_equal:today`);
  - todo en una transacción, recorriendo las variantes ordenadas por id.
- `GET /stock/variantes/{variante}/kardex`: paginado de 50 con filtro desde/hasta sobre `fecha`, ordenado por `id desc`. Se ordena por id y no por fecha, porque el saldo sigue el orden de registro.
- La ficha del producto en el catálogo suma los enlaces "Ajustar stock" y "Kardex" según los permisos.

### D6. Permisos
- `StockVer = 'stock.ver'` y `StockAjustar = 'stock.ajustar'`.
- Gerente y Depósito: ambos. Vendedor y Cajero: `stock.ver`.
- Depósito todavía no tenía `catalogo.ver`; lo conserva, porque necesita ver productos para ajustar.

### D7. Comando `stock:recalcular {--corregir}`
- Compara `stock.cantidad` con el `saldo` del último movimiento de cada par variante y sucursal (si no hay movimientos, compara con 0).
- Informa las diferencias en una tabla. Con `--corregir`, en una transacción, actualiza la cantidad y audita `stock.recalculado`.
- No toca movimientos.

### D8. Errores de dominio
- `App\Exceptions\StockInsuficiente` (con `disponible` y `variante`) y `App\Exceptions\TransferenciaNoHabilitada`.
- Los controladores los traducen a errores de validación en español. Ventas hará lo mismo.

## Risks / Trade-offs

- [Deadlock si dos operaciones bloquean variantes en distinto orden] → El servicio documenta, y el ajuste por producto aplica, el orden por `variante_id`. Ventas deberá respetarlo.
- [El saldo cacheado se desalinea por un error o una edición manual en la base] → Existe el comando `stock:recalcular` y la auditoría registra cada corrección.
- [`LEFT JOIN` del listado con muchas variantes] → Índice único `(variante_id, sucursal_id)` y paginación. El volumen esperado es el de un solo comercio.
- [Ajustar con fecha pasada intercala la fecha de negocio, pero el saldo sigue el orden de registro] → Es lo esperado: el kardex muestra las dos fechas, y los reportes por fecha usan `fecha`.
- [Probar la concurrencia en PHPUnit] → Se prueba el bloqueo de forma determinista: el segundo egreso se calcula sobre el saldo ya actualizado dentro de transacciones sucesivas. La verificación con dos conexiones simultáneas queda como prueba manual opcional.

## Migration Plan

1. Migraciones nuevas, que requieren autorización para correrlas en `comercio`.
2. Re-ejecutar `RolesYPermisosSeeder`.
3. Cargar el stock inicial desde "Ajustar stock" con motivo "Stock inicial".
4. Rollback: `migrate:rollback` del lote. Tablas nuevas.

## Open Questions

- Si más adelante conviene un permiso aparte para ver costos en el stock: hoy no se muestran costos en estas pantallas.
