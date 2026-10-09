# Proposal

## Why

Para "vender en el mostrador" (Fase 1), el sistema tiene que saber cuántas unidades hay de cada variante en cada sucursal y por qué cambió esa cantidad. `fase-1-catalogo` dejó las variantes (la unidad de stock y de venta), pero todavía no hay existencias. Este cambio crea el stock por variante y sucursal, su kardex y la forma de cargarlo y corregirlo. También deja listo el servicio de movimientos que van a usar Ventas, Compras y Cambios, con la regla de stock negativo configurable (VEN-09).

## What Changes

- **Stock por variante y sucursal (STK-01):** cantidad actual y stock mínimo, siempre de la sucursal activa. La cantidad es un saldo que se recalcula desde los movimientos, que son la fuente de verdad.
- **Movimientos y kardex (STK-02):**
  - Cada cambio de stock es un movimiento: ingreso, egreso, ajuste o transferencia.
  - Cada movimiento guarda fecha de negocio, momento de registro, cantidad con signo, saldo resultante, motivo, origen, usuario y dispositivo.
  - Los movimientos no se editan ni se borran; un error se corrige con otro movimiento.
  - La transferencia queda modelada, pero rechazada mientras no se habiliten las transferencias entre sucursales.
- **Ajuste por conteo:**
  - Se carga la cantidad contada de una o varias variantes de un producto. El sistema registra la diferencia como ajuste, con motivo obligatorio (por ejemplo, "Stock inicial").
  - Es la forma de cargar el stock inicial y de corregirlo hasta que existan Compras e Inventario físico.
  - Cada ajuste se audita (USR-03).
- **Stock negativo configurable (VEN-09):**
  - Un egreso que deja stock negativo se rechaza si "Permitir venta con stock negativo" está deshabilitado.
  - Si está habilitado, el egreso se registra y queda auditado.
  - Todo movimiento bloquea la fila de stock dentro de la transacción, para mantener el saldo consistente ante operaciones simultáneas.
- **Consultas (parte de REP-02 de la Fase 1):**
  - pantalla de stock actual de la sucursal activa, con búsqueda y filtros, incluido "bajo mínimo";
  - kardex por variante con filtro por período.
- **Mínimos:** edición del stock mínimo por variante en la sucursal activa, auditada. Las alertas y la reposición (STK-04) quedan para la Fase 3.
- **Permisos:** `stock.ver` y `stock.ajustar`.
- **Comando de consola** para verificar y recalcular los saldos desde los movimientos.

## Capabilities

### New Capabilities
- `stock`: existencias por variante y sucursal, movimientos y kardex, ajuste por conteo, regla de stock negativo, mínimos, consultas y permisos de stock.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. -->

## Impact

- **Código nuevo:**
  - migraciones de `stock` y `movimientos_stock`;
  - modelos, enum `TipoMovimientoStock` y servicio `MovimientosStock`, que será la API para Ventas y Compras;
  - Form Requests, policy, controladores y vistas de stock actual, ajuste y kardex;
  - comando `stock:recalcular`.
- **Archivos existentes que cambian:**
  - `app/Enums/Permiso.php` y `app/Enums/Rol.php`: permisos y matriz;
  - `routes/web.php` y `resources/views/layouts/navigation.blade.php`;
  - `resources/views/catalogo/productos/show.blade.php`: acceso a stock y kardex de cada variante.
- **Base de datos:** dos tablas nuevas en `comercio`. Correr la migración requiere autorización al implementar.
- **Dependencias:** ninguna nueva.
- **Relación con otros cambios:**
  - Depende de `fase-1-base` y de `fase-1-catalogo`. A ambos les falta el recorrido manual, y el catálogo no está commiteado.
  - Ventas (VEN-01, VEN-09), Compras (COM-02) y Cambios (VEN-03) van a registrar movimientos con el servicio de este cambio.
- **Fuera de alcance:**
  - ingresos y egresos manuales: por decisión del usuario, los ingresos llegan con Compras y los egresos con Ventas;
  - inventario físico con diferencias (STK-03, Fase 2);
  - alertas de mínimo y reposición (STK-04);
  - stock valorizado (resto de REP-02);
  - transferencias entre sucursales (Fase 4).
