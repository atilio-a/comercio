# Tasks

> Migración de `comercio` autorizada para este cambio. Sin dependencias nuevas. No usar `migrate:fresh` ni `db:wipe`.

## 1. Permisos

- [x] 1.1 Agregar `VentasDevolver` y `VentasReembolsar` a `Permiso` y a la matriz de `Rol::permisos()`; verificar actualizando `DatosInicialesTest`

## 2. Esquema y modelos

- [x] 2.1 Crear enum `ResolucionDevolucion`, el caso `NotaCreditoInterna` de `TipoComprobante`, migraciones de `devoluciones`, `devolucion_items` y la FK `comprobantes.devolucion_id`, y los modelos inmutables `Devolucion` y `DevolucionItem` con sus relaciones (y las de `Venta`, `VentaItem`, `Comprobante`); verificar con tests de inmutabilidad y de la FK

## 3. Registro de la devolución

- [x] 3.1 Agregar `emitirNotaCredito()` a `EmisorComprobante`, `EmisorInterno` y `EmisorNoDisponible`; verificar con tests: nota de crédito con numeración correlativa propia, FK a la devolución, datos copiados y leyenda
- [x] 3.2 Implementar `DevolucionRechazada` y `Devoluciones::registrar()` (D2, D3) con stock, cuenta corriente, caja, nota de crédito y auditoría; verificar con tests: parcial y total, no superar lo vendido (incluye sucesivas), venta anulada, reingreso con origen, saldo a favor acreditado (cuenta creada sin crédito y venta a cuenta que reduce deuda), consumidor final exige cliente, reembolso con caja abierta y permiso, venta cobrada con saldo a favor no se reembolsa, importes con descuentos y recargo prorrateados que cierran al centavo, atomicidad (falla en cuenta corriente revierte stock y numeración)
- [x] 3.3 Rechazar en `Ventas::anular()` y `VentaPolicy::anular` las ventas con devoluciones; verificar con un test en `AnularVentaTest`

## 4. Pantallas

- [x] 4.1 Implementar `VentaPolicy::devolver`, `RegistrarDevolucionRequest`, `DevolucionController` (formulario, registro, listado, detalle), rutas y vistas mobile-first con el componente Alpine `devolucion`; verificar con tests HTTP: Vendedor devuelve con saldo a favor, Vendedor no puede reembolsar, Gerente reembolsa, Depósito 403, otra sucursal 404, listado de la sucursal, detalle con nota de crédito imprimible (ticket y A4)
- [x] 4.2 Mostrar en el detalle de la venta las devoluciones y la cantidad devuelta por ítem, con el botón "Devolver / cambiar"; verificar con tests HTTP
- [x] 4.3 Precargar el cliente en la venta rápida (`?cliente_id=`) y el botón "Usar el saldo en una nueva venta"; verificar con tests: venta rápida con el cliente y su saldo, y venta registrada con `Ventas::registrar` pagando con el saldo a favor de la devolución

## 5. Navegación e integración

- [x] 5.1 Agregar "Devoluciones" al grupo "Ventas y caja"; verificar con `NavegacionTest` por rol
- [x] 5.2 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff
- [x] 5.3 Correr `php artisan migrate` y `RolesYPermisosSeeder` sobre `comercio`; verificar con `migrate:status` y consultando los permisos

## Workflow follow-up

- Recorrido manual en ancho móvil: devolver un par de una venta a consumidor final con alta rápida de cliente, imprimir la nota de crédito en ticket y A4, usar el saldo en una nueva venta pagando la diferencia en efectivo.
- Reembolso en efectivo como Gerente y revisar el egreso en el arqueo de la caja.
- Confirmar con el usuario si el Vendedor debe poder devolver sin autorización del Gerente pasado cierto plazo, y si el saldo a favor debe vencer.
- Archivar el cambio cuando se complete el recorrido manual.
