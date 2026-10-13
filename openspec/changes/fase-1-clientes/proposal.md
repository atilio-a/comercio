# Proposal

## Why

La venta rápida, los cambios con saldo a favor (VEN-03) y el reporte de ventas por vendedor (VND-01) de la Fase 1 necesitan saber a quién se le vende y quién vende. Hoy no existen clientes, vendedores ni cuentas corrientes, y el saldo a favor debe vivir siempre en la cuenta corriente del cliente (doc 04 y 05), así que hace falta la base antes del cambio de ventas.

## What Changes

- **Clientes (CLI-01):**
  - ficha global (común a todas las sucursales) con nombre, documento (DNI de 7 u 8 dígitos o CUIT con dígito verificador), teléfono, email, talle habitual, condición frente al IVA, observaciones y estado activo;
  - documento único, guardado solo con dígitos; nombre y al menos teléfono o documento obligatorios; un cliente responsable inscripto, monotributista o exento se identifica con CUIT;
  - ABM mobile-first con búsqueda por nombre, documento o teléfono, altas y cambios auditados; no se borran, se desactivan;
  - talle habitual visible y editable solo con el parámetro "Usar talles en productos" habilitado; deshabilitado se oculta sin borrarse;
  - búsqueda JSON y alta rápida JSON (nombre más teléfono o documento) para usar desde la venta;
  - ficha con estado de cuenta corriente y un lugar reservado para el historial de ventas, que llega con el cambio de ventas.
- **Vendedores (VND-01):** un usuario se marca como vendedor desde la gestión de usuarios (alta y edición, auditado). Listado de vendedores activos de la sucursal activa para la venta.
- **Cuenta corriente básica:**
  - una cuenta por cliente, con crédito habilitado o no, límite, bloqueo y saldo cacheado (positivo = el cliente debe; negativo = saldo a favor);
  - movimientos inmutables de debe y haber por sucursal, con concepto, origen, vencimiento, usuario y dispositivo, corregidos con contraasiento;
  - la cuenta se crea automáticamente sin crédito al acreditar el primer saldo a favor;
  - una cuenta sin crédito habilitado solo admite débitos que consuman saldo a favor existente;
  - comando para comparar y corregir el saldo cacheado contra los movimientos.
- **Permisos:** `clientes.ver` y `clientes.gestionar` (Vendedor, Cajero, Gerente) y `cuentas_corrientes.ver` (Cajero, Gerente).
- **Navegación:** entrada "Clientes" en el grupo "Ventas y caja".

## Capabilities

### New Capabilities
- `clientes`: ficha de clientes, validación de documento, búsqueda y alta rápida, talle habitual según parámetro y permisos de clientes.
- `vendedores`: marca de vendedor sobre los usuarios y vendedores disponibles por sucursal.
- `cuentas-corrientes`: cuentas corrientes de clientes con saldo a favor, movimientos inmutables, regla de crédito, contraasiento, recálculo del saldo y estado de cuenta.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. -->

## Impact

- **Código nuevo:**
  - migraciones de `clientes`, `vendedores`, `cuentas_corrientes` y `cc_movimientos`;
  - modelos y factories, enums `CondicionIva` y `TipoMovimientoCuenta`, regla `DocumentoClienteValido`, excepción `CreditoNoDisponible`;
  - servicios `CuentasCorrientes` y `Vendedores`, comando `cuentas-corrientes:recalcular`;
  - Form Request, controladores y vistas de clientes.
- **Archivos existentes que cambian:**
  - `app/Enums/Permiso.php` y `app/Enums/Rol.php`;
  - `routes/web.php` y `resources/views/layouts/navigation.blade.php`;
  - gestión de usuarios (`GestionUsuarios`, requests, formulario y listado) para la marca de vendedor;
  - `tests/Feature/Seeders/DatosInicialesTest.php` y `tests/Feature/NavegacionTest.php`.
- **Base de datos:** cuatro tablas nuevas en `comercio` (migración autorizada); ninguna tabla existente cambia.
- **Dependencias:** ninguna nueva.
- **Fuera de alcance:** ventas a cuenta, límite de crédito operativo y cobranzas (Fase 2), cuotas propias (Fase 3), metas y comisiones (Fase 3), ajustes manuales de cuenta corriente y envío del estado de cuenta por WhatsApp o PDF.
