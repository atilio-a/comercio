# Tasks

> Migración de `comercio` autorizada para este cambio. Sin dependencias nuevas. No usar `migrate:fresh` ni `db:wipe`.

## 1. Permisos

- [x] 1.1 Agregar `CajaOperar`, `CajaSupervisar` y `CajaConfigurar` a `Permiso` y la matriz de D8 en `Rol::permisos()`; verificar extendiendo `DatosInicialesTest` (Gerente con operar y supervisar, Vendedor y Cajero con operar, Depósito sin caja, nadie con configurar)

## 2. Medios de pago y cajas

- [x] 2.1 Crear migraciones, modelos y factories de `medios_pago`, `planes_cuota` y `cajas`, el enum `TipoMedioPago` y los seeders `MedioPagoSeeder` y `CajaSeeder` (en `DatabaseSeeder`); verificar con tests de datos iniciales: siete medios, plan de 1 cuota en crédito, "Caja principal" por sucursal activa e idempotencia sin pisar un medio desactivado
- [x] 2.2 Implementar el ABM de medios de pago y de planes de cuotas (Form Requests, controladores, vistas, auditoría); verificar con tests: alta y edición auditadas, nombre repetido, plan con recargo 12.50, cuotas repetidas, plan en medio que no es crédito rechazado y 403 para Gerente
- [x] 2.3 Implementar el ABM de cajas de la sucursal activa; verificar con tests: alta, nombre repetido en la misma sucursal, edición de una caja de otra sucursal responde 404 y 403 para Gerente

## 3. Sesiones y movimientos

- [x] 3.1 Crear migraciones, modelos (inmutables según D7) y factories de `caja_sesiones` (con columnas generadas únicas), `caja_sesion_arqueos` y `caja_movimientos`, el enum `TipoMovimientoCaja`, `Importes` y las excepciones `CajaNoAbierta` y `CajaYaAbierta`; verificar con tests: excepción al modificar o borrar un movimiento o un arqueo, sesión cerrada no modificable y segunda sesión abierta de la misma caja rechazada por la base
- [x] 3.2 Implementar `MovimientosCaja` (registrar, contraasentar, sesionAbierta) con el contrato de D4 documentado en PHPDoc; verificar con tests: transacción obligatoria, signo por tipo, importe 0 o con tres decimales rechazado, motivo obligatorio en ingreso/egreso, referencia obligatoria, medio inactivo, sin sesión abierta, sesión cerrada, sesión explícita, origen, usuario y dispositivo, contraasiento (importe opuesto, auditoría, una sola vez, no de un contraasiento, no en sesión cerrada)
- [x] 3.3 Implementar `SesionesCaja` (abrir, cerrar, esperado por medio) según D5; verificar con tests: apertura con y sin fondo, caja ya abierta, usuario con otra caja abierta, caja inactiva o de otra sucursal, esperado 4.099,50, cierre sin diferencia, diferencia sin justificación rechazada, diferencia justificada auditada y arqueo con esperado de medios no contados

## 4. Pantallas

- [x] 4.1 Implementar "Mi caja" (apertura, saldo esperado, ingreso/egreso, movimientos) y el cierre con arqueo; verificar con tests HTTP: apertura por Cajero, 403 para Depósito, egreso sin motivo rechazado, medio de cuenta corriente rechazado, cierre con y sin diferencia y aislamiento por sucursal
- [x] 4.2 Implementar el listado y detalle de sesiones para quien supervisa y el contraasiento desde el detalle; verificar con tests HTTP: Gerente ve sesiones de su sucursal y no las de otra (404), Cajero recibe 403 en el listado pero ve el detalle de su propia sesión, contraasiento por Gerente y 403 para Cajero
- [x] 4.3 Agregar "Caja", "Sesiones de caja" y "Medios de pago" a la navegación (escritorio y responsive) según permisos; verificar extendiendo `NavegacionTest`

## 5. Verificación e integración

- [x] 5.1 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff
- [x] 5.2 Correr `php artisan migrate`, `MedioPagoSeeder`, `CajaSeeder` y `RolesYPermisosSeeder` sobre `comercio`; verificar con `migrate:status` y consultando que existan los siete medios y la "Caja principal"

## Workflow follow-up

- Recorrido manual en ancho móvil: abrir caja con fondo, registrar un ingreso y un egreso, cerrar con una diferencia justificada, y como Gerente ver la sesión y contraasentar un movimiento de una sesión abierta.
- Revisar con el usuario si el Gerente debe poder cerrar la sesión de otro usuario y si debe aprobar las diferencias de arqueo.
- Revisar en el navegador el ancho de la barra de navegación de escritorio para el Administrador (ahora tiene diez entradas) y, si se desborda entre 640 y 1024 px, agrupar entradas o pasar el menú completo a `lg`.
- Archivar el cambio cuando se complete el recorrido manual.
