# Tasks

> Migración de `comercio` autorizada para este cambio. Sin dependencias nuevas. No usar `migrate:fresh` ni `db:wipe`.

## 1. Permisos

- [x] 1.1 Agregar `VentasVender`, `VentasVerTodas`, `VentasAutorizarDescuentos`, `VentasAnular` y `PuntosVentaConfigurar` a `Permiso` y a la matriz de `Rol::permisos()`; verificar actualizando `DatosInicialesTest`

## 2. Puntos de venta y numeración

- [x] 2.1 Crear migración, modelo y factory de `puntos_venta` y el `PuntoVentaSeeder` (en `DatabaseSeeder`); verificar con tests: seeder crea el punto 1 y es idempotente, segunda sucursal recibe el siguiente número libre
- [x] 2.2 Implementar el ABM (`GuardarPuntoVentaRequest`, `PuntoVentaController`, vistas) con auditoría; verificar con tests HTTP: Administrador crea y edita, número repetido rechazado, número no editable, Gerente 403, punto de otra sucursal 404
- [x] 2.3 Crear enum `TipoComprobante`, migración y modelo inmutable de `comprobantes` y el servicio `NumeradorComprobantes`; verificar con tests: numeración independiente por tipo y punto de venta, índice único rechaza duplicados, comprobante no se modifica ni borra

## 3. Cálculo de la venta

- [x] 3.1 Implementar `VentaRechazada` y `CalculadoraVenta` (D2); verificar con tests: desglose de un ítem, descuento por ítem y general con centavos cerrados en el último ítem, recargo distribuido con alícuotas distintas, neto + IVA = total, descuentos excesivos, total 0, pagos que no suman, previsualización sin exigir pagos

## 4. Registro de la venta

- [x] 4.1 Crear enum `EstadoVenta`, migraciones, modelos inmutables y factories de `ventas`, `venta_items` y `venta_pagos`; verificar con tests de inmutabilidad (venta solo admite la anulación, ítems y pagos no se modifican ni borran)
- [x] 4.2 Implementar `EmisorComprobante`, `EmisorInterno`, `EmisorNoDisponible` y el binding; verificar con tests: comprobante interno con datos copiados y leyenda, facturación habilitada rechaza la venta sin grabar nada
- [x] 4.3 Implementar `Ventas::registrar()` (D3, D4) con stock, caja, cuenta corriente, comprobante y auditoría; verificar con tests: venta en efectivo completa, pago combinado, stock insuficiente con y sin parámetro, caja cerrada rechaza y revierte stock, solo saldo a favor sin caja, saldo a favor insuficiente, cuenta corriente sin crédito, consumidor final con saldo a favor, plan de otro medio, referencia obligatoria, numeración sin huecos tras una venta fallida
- [x] 4.4 Implementar `RegistrarVentaRequest` (vendedor disponible, cliente activo, punto de venta, autorización de descuentos con rate limit) y las rutas `ventas.store` y `ventas.previsualizar`; verificar con tests HTTP: descuento sin autorización, con contraseña del Gerente (auditado), contraseña incorrecta, autorizante sin permiso, Gerente que se autoriza, vendedor de otra sucursal, respuesta JSON 201 y 422, previsualización con recargo y pendiente

## 5. Venta rápida

- [x] 5.1 Implementar la búsqueda JSON de variantes para la venta y la vista `ventas.create` con el componente Alpine `ventaRapida`; verificar con tests: talle oculto con el parámetro deshabilitado e informado con el habilitado, búsqueda exacta por código, foto del color, vendedor por defecto, Depósito 403; `npm run build` sin errores

## 6. Consulta, comprobante y anulación

- [x] 6.1 Implementar `VentaPolicy`, scope `visiblesPara`, listado con filtros y detalle; verificar con tests: Vendedor ve solo sus ventas y 403 en el detalle ajeno, Cajero filtra por vendedor y estado, otra sucursal 404
- [x] 6.2 Implementar la impresión del comprobante en ticket y A4 desde `datos_json`; verificar con tests: leyenda, número formateado, formato ticket y A4, reimpresión con datos originales tras cambiar el cliente, comprobante ajeno 403
- [x] 6.3 Implementar `Ventas::anular()` y la ruta de anulación; verificar con tests: reversión de stock, caja y cuenta corriente con comprobante de anulación y auditoría, segunda anulación rechazada, caja cerrada rechazada sin movimientos, motivo obligatorio, Vendedor 403
- [x] 6.4 Completar el historial de compras en la ficha del cliente; verificar con tests: Cajero ve las ventas del cliente, Vendedor solo las suyas (actualizando el test de la ficha que esperaba el aviso provisorio)

## 7. Navegación e integración

- [x] 7.1 Agregar "Vender", "Ventas" y "Puntos de venta" al grupo "Ventas y caja"; verificar con `NavegacionTest` por rol
- [x] 7.2 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff
- [x] 7.3 Correr `php artisan migrate`, `RolesYPermisosSeeder` y `PuntoVentaSeeder` sobre `comercio`; verificar con `migrate:status` y consultando permisos y puntos de venta

## Workflow follow-up

- Recorrido manual en ancho móvil: buscar por nombre, escanear con lector, elegir talle y color con foto, aplicar descuento con autorización del Gerente, pagar combinado con tarjeta en cuotas y efectivo, imprimir ticket y A4 y guardar como PDF.
- Deshabilitar el parámetro de talles y verificar que la venta rápida pide solo color.
- Anular una venta como Gerente con la caja abierta y revisar el kardex, la caja y la cuenta corriente.
- Confirmar con el usuario si conviene un tope de descuento sin autorización o un PIN de autorización en lugar de la contraseña.
- Archivar el cambio cuando se complete el recorrido manual.
