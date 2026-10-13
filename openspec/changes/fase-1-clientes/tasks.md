# Tasks

> Migración de `comercio` autorizada para este cambio. Sin dependencias nuevas. No usar `migrate:fresh` ni `db:wipe`.

## 1. Permisos

- [x] 1.1 Agregar `ClientesVer`, `ClientesGestionar` y `CuentasCorrientesVer` a `Permiso` y a la matriz de `Rol::permisos()` (Vendedor y Cajero clientes; Cajero además cuentas corrientes; Gerente los tres); verificar actualizando `DatosInicialesTest`

## 2. Clientes

- [x] 2.1 Crear el enum `CondicionIva`, la regla `DocumentoClienteValido`, migración, modelo y factory de `clientes`; verificar con tests unitarios de la regla (DNI de 7 y 8 dígitos, CUIT válido, dígito verificador incorrecto, largo inválido, texto) y del formato del documento
- [x] 2.2 Implementar el ABM (`GuardarClienteRequest`, `ClienteController`, vistas de listado, formulario y ficha) con auditoría y búsqueda; verificar con tests HTTP: alta auditada con documento normalizado, documentos inválidos, duplicado con o sin puntos, dos clientes sin documento, sin teléfono ni documento, responsable inscripto con DNI, edición auditada, búsqueda por nombre, documento y teléfono
- [x] 2.3 Implementar la búsqueda JSON y el alta rápida JSON; verificar con tests: resultados activos con formato esperado, alta rápida 201, alta rápida inválida 422
- [x] 2.4 Aplicar el parámetro de talles al formulario, la ficha y el JSON; verificar con tests: talle oculto y conservado al guardar con el parámetro deshabilitado, editable con el parámetro habilitado
- [x] 2.5 Agregar la entrada "Clientes" al grupo "Ventas y caja" y los permisos por rol; verificar con tests: Depósito 403 en listado, ficha y JSON; Cajero y Vendedor gestionan; menú por rol en `NavegacionTest`

## 3. Cuenta corriente

- [x] 3.1 Crear enum `TipoMovimientoCuenta`, migraciones, modelos (`CuentaCorriente`, `CcMovimiento` inmutable) y factory de cuentas; verificar con tests: excepción al modificar o borrar un movimiento, `saldoAFavor()` y `deuda()` según la convención
- [x] 3.2 Implementar `CuentasCorrientes` (`cuentaDe`, `acreditar`, `debitar`, `contraasentar`, `saldoSegunMovimientos`) y la excepción `CreditoNoDisponible`; verificar con tests: cuenta creada sin crédito, acreditación con usuario, dispositivo, sucursal y saldo resultante, débito que consume saldo a favor, débito que generaría deuda rechazado, débito con crédito dentro y fuera del límite y con cuenta bloqueada, importe inválido, contraasiento auditado, doble reversión, contraasiento de un saldo a favor ya usado rechazado, servicio fuera de transacción
- [x] 3.3 Crear el comando `cuentas-corrientes:recalcular {--corregir}`; verificar con tests: sin diferencias termina bien, con diferencias falla sin corregir, con `--corregir` corrige y audita
- [x] 3.4 Mostrar el estado de cuenta en la ficha del cliente; verificar con tests: Cajero ve saldo a favor y movimientos, Vendedor no los ve, la ficha no crea la cuenta y muestra el aviso del historial de ventas

## 4. Vendedores

- [x] 4.1 Crear migración, modelo y factory de `vendedores`, la marca "Es vendedor" en el formulario y listado de usuarios y en `GestionUsuarios` (auditada); verificar con tests: alta con marca, desmarcar desactiva, edición sin el campo no lo toca, etiqueta en el listado
- [x] 4.2 Implementar `Vendedores::disponibles()`; verificar con test: solo vendedores activos, con usuario activo y asignado a la sucursal

## 5. Verificación e integración

- [x] 5.1 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff
- [x] 5.2 Correr `php artisan migrate` y `RolesYPermisosSeeder` sobre `comercio`; verificar con `migrate:status` y consultando los permisos nuevos

## Workflow follow-up

- Recorrido manual en ancho móvil: crear un cliente con alta completa, buscarlo por DNI con puntos y por teléfono, editarlo, deshabilitar el parámetro de talles y verificar que el talle se oculta y se conserva.
- Marcar un usuario como vendedor y revisar la etiqueta en el listado.
- Revisar la ficha como Cajero y como Vendedor cuando el cambio de devoluciones genere el primer saldo a favor.
- Confirmar con el usuario si un Vendedor debe ver el saldo a favor del cliente (hoy solo Cajero y Gerente).
- Archivar el cambio cuando se complete el recorrido manual.
