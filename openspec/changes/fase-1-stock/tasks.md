# Tasks

> Requiere autorización del usuario antes de correr migraciones sobre `comercio` (5.3). Sin dependencias nuevas. No usar `migrate:fresh` ni `db:wipe`.

## 1. Permisos

- [x] 1.1 Agregar `StockVer` y `StockAjustar` a `Permiso` y la matriz de D6 en `Rol::permisos()`; verificar extendiendo `DatosInicialesTest`: Gerente y Depósito tienen ambos, y Vendedor y Cajero solo `stock.ver`

## 2. Modelo y servicio de movimientos

- [x] 2.1 Crear las migraciones de `stock` y `movimientos_stock` (D1), los modelos `Stock` y `MovimientoStock` (inmutable, D4), las factories y el enum `TipoMovimientoStock` (D2); verificar con tests de unicidad variante-sucursal y de excepción al modificar o borrar un movimiento
- [x] 2.2 Crear las excepciones `StockInsuficiente` y `TransferenciaNoHabilitada` e implementar `MovimientosStock::registrar()` (transacción obligatoria, `insertOrIgnore` más `lockForUpdate`, signo por tipo, saldo encadenado, usuario y dispositivo, D3); verificar con tests: saldos 3 → 2 → 6, cantidad 0 rechazada, signo inválido rechazado, transferencia rechazada y error fuera de transacción
- [x] 2.3 Aplicar la regla de stock negativo en `registrar()`; verificar con tests: egreso de 2 con stock 1 rechazado con el parámetro deshabilitado (sin movimiento), y aceptado con saldo -1 y auditoría `stock.negativo` con el parámetro habilitado; dos egresos sucesivos de 1 con stock 1 dejan uno registrado y otro rechazado
- [x] 2.4 Implementar `ajustarAConteo()` y `actualizarMinimo()` con auditoría (`stock.ajuste`, `stock.minimo_actualizado`); verificar con tests: carga inicial 0 → 4, corrección 4 → 3, sin diferencia no registra nada, auditoría con anterior, nuevo y motivo, y el mínimo no genera movimiento

## 3. Pantallas

- [x] 3.1 Implementar `StockController@index` (D5: `LEFT JOIN`, búsqueda por nombre, SKU o código exacto, categoría, bajo mínimo y estado) y su vista mobile-first; verificar con tests de stock 0 sin fila, aislamiento por sucursal, filtro bajo mínimo, negativo destacado y Vendedor sin enlace de ajuste
- [x] 3.2 Implementar el ajuste por producto (`AjusteStockRequest`, controlador y vista con actual, contado y mínimo por variante, más motivo y fecha) en una transacción ordenada por variante; verificar con tests: carga de dos variantes, campos vacíos sin movimiento, motivo faltante, contado negativo y fecha futura rechazados, mínimo actualizado y 403 para Cajero
- [x] 3.3 Implementar el kardex por variante (paginado, del más reciente al más antiguo, filtro por fecha de negocio, con talle si existe); verificar con tests de orden, filtro, aislamiento por sucursal y 403 sin `stock.ver`
- [x] 3.4 Agregar "Stock" a la navegación (`stock.ver`) y los enlaces "Ajustar stock" y "Kardex" en la ficha del producto según los permisos; verificar extendiendo `NavegacionTest` y `ProductosTest`

## 4. Mantenimiento

- [x] 4.1 Implementar el comando `stock:recalcular {--corregir}` (D7); verificar con tests de consola: sin diferencias informa OK, con diferencia la informa y sin `--corregir` no cambia nada, y con `--corregir` corrige y audita `stock.recalculado`

## 5. Verificación e integración

- [x] 5.1 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff
- [x] 5.2 Revisar que el servicio quede documentado para Ventas y Compras (PHPDoc de `MovimientosStock` con el contrato de transacción, orden de bloqueo y excepciones); verificar leyendo el PHPDoc resultante
- [ ] 5.3 Con autorización, correr `php artisan migrate` y `RolesYPermisosSeeder` sobre `comercio`; verificar con la herramienta `database-schema` de Boost y con un recorrido manual: cargar stock inicial de un producto, corregir una variante, ver el kardex y el filtro bajo mínimo en ancho móvil

## Workflow follow-up

- Cerrar y commitear `fase-1-catalogo` (le falta la 5.4) antes de commitear este cambio.
- Siguiente cambio sugerido de la Fase 1: Compras (proveedores y recepción con ingreso de stock, COM-01 y COM-02) o Caja y medios de pago (CAJ-01, 02, 03 y 05), que son requisitos de la venta rápida.
