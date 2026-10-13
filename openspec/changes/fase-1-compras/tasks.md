# Tasks

> Migración de `comercio` autorizada para este cambio. Sin dependencias nuevas. No usar `migrate:fresh` ni `db:wipe`.

## 1. Permisos

- [x] 1.1 Agregar `ComprasVer`, `ComprasGestionar` y `ComprasRecibir` a `Permiso` y a la matriz de `Rol::permisos()` (Gerente los tres, Depósito ver y recibir); verificar actualizando `DatosInicialesTest`

## 2. Proveedores

- [x] 2.1 Crear `Cuit` y la regla `CuitValido`; verificar con tests: CUIT válido con y sin guiones, dígito verificador incorrecto, prefijo inválido, largo incorrecto y formateo
- [x] 2.2 Crear migración, modelo y factory de `proveedores` y el ABM (Form Request, controlador, vistas, auditoría); verificar con tests HTTP: alta auditada con CUIT normalizado, CUIT inválido, CUIT repetido con guiones, dos proveedores sin CUIT, edición auditada, 403 para Depósito al crear y para Vendedor al listar

## 3. Órdenes de compra

- [x] 3.1 Crear migraciones, modelos y factories de `ordenes_compra` y `orden_compra_items` y el enum `EstadoOrdenCompra`; verificar con tests de modelo: estado recalculado desde los ítems y pendiente por ítem
- [x] 3.2 Implementar `OrdenesCompra` (crear con número por sucursal, actualizar en borrador, emitir, anular) y el endpoint JSON de búsqueda de variantes; verificar con tests: numeración por sucursal, edición de emitida rechazada, anulación de orden con recepciones rechazada, búsqueda por nombre/SKU/código exacto
- [x] 3.3 Implementar pantallas de órdenes (listado, alta/edición con el componente Alpine `itemsCompra`, detalle con emitir/anular); verificar con tests HTTP: alta con ítems, variante repetida rechazada, proveedor inactivo rechazado, emitir y anular auditados, 403 para Depósito al crear, 404 para orden de otra sucursal

## 4. Recepciones

- [x] 4.1 Crear migraciones, modelos (inmutables según D4) y factories de `recepciones` y `recepcion_items`; verificar con tests: excepción al modificar o borrar ítems y recepciones, salvo la anulación
- [x] 4.2 Implementar `RecepcionesCompra::registrar()`; verificar con tests: ingreso de stock con origen, usuario y dispositivo; recepción parcial y total actualizan estados; excede pendiente rechazado sin stock; variante ajena a la orden; orden borrador o anulada; costo actualizado auditado y costo sin marca
- [x] 4.3 Implementar `RecepcionesCompra::anular()`; verificar con tests: egresos con `reversa_de_id`, stock y cantidad recibida revertidos, orden vuelve a `emitida`, stock insuficiente rechazado sin cambios, stock negativo habilitado registrado y auditado, doble anulación rechazada
- [x] 4.4 Implementar pantallas de recepción (desde orden o libre, con escaneo por teclado), listado y detalle con anulación; verificar con tests HTTP: Depósito recibe una orden, 403 de Depósito al anular, Cajero 403, 404 de recepción y orden de otra sucursal, mensaje de stock insuficiente

## 5. Navegación

- [x] 5.1 Reorganizar `navigation.blade.php` en grupos (dropdowns en escritorio, secciones en móvil) con Compras en "Inventario" y Escape en `x-dropdown`; verificar adaptando `NavegacionTest` (visibilidad por rol de cada entrada y de cada grupo, botones con `aria-expanded`)

## 6. Verificación e integración

- [x] 6.1 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff
- [x] 6.2 Correr `php artisan migrate` y `RolesYPermisosSeeder` sobre `comercio`; verificar con `migrate:status` y consultando los permisos de compras

## Workflow follow-up

- Recorrido manual en ancho móvil: crear un proveedor, una orden con dos variantes buscándolas, emitirla, recibirla en dos partes escaneando con un lector, ver el kardex y anular una recepción.
- Revisar en el navegador los desplegables de escritorio entre 640 y 1024 px y con teclado (Tab, Enter, Escape).
- Revisar con el usuario si hace falta "cerrar orden con pendientes" y si Depósito debe poder anular recepciones.
- Archivar el cambio cuando se complete el recorrido manual.
