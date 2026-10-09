# Proposal

## Why

La venta rápida de la Fase 1 necesita saber con qué se cobra y adónde va el dinero. Hoy no existen medios de pago, cajas ni movimientos de dinero, así que Ventas no tiene dónde registrar un cobro. Este cambio crea el catálogo de medios de pago (CAJ-01), la apertura, el arqueo y el cierre de caja por usuario (CAJ-02), los ingresos y egresos varios con motivo (CAJ-03) y los movimientos de dinero inmutables con contraasiento (CAJ-05). También deja el servicio que Ventas, Cobranzas y Compras van a usar para mover dinero.

## What Changes

- **Medios de pago configurables (CAJ-01):**
  - catálogo con nombre, tipo (efectivo, tarjeta de débito, tarjeta de crédito, transferencia, QR, cuenta corriente, saldo a favor), si impacta en caja física, días de acreditación, si requiere referencia y estado activo;
  - planes de cuotas por medio de tarjeta de crédito, con recargo porcentual;
  - ABM para quien tenga `caja.configurar` (el Administrador), auditado;
  - datos iniciales idempotentes con los medios básicos.
- **Cajas por sucursal:** cada sucursal tiene una o más cajas. Los datos iniciales crean "Caja principal" en cada sucursal activa y el Administrador puede agregar o desactivar cajas.
- **Sesiones de caja (CAJ-02):**
  - el usuario abre una caja de la sucursal activa con un fondo inicial;
  - una caja tiene como máximo una sesión abierta, y un usuario como máximo una sesión abierta por sucursal;
  - al cerrar, el usuario cuenta cada medio que impacta en caja; el sistema calcula esperado, contado y diferencia por medio;
  - una diferencia distinta de 0 exige justificación y queda auditada.
- **Movimientos de caja inmutables (CAJ-03, CAJ-05):**
  - cada movimiento guarda sesión, sucursal, fecha de negocio, tipo, medio, importe con signo, motivo, referencia, origen, usuario y dispositivo;
  - ingresos y egresos varios con motivo obligatorio;
  - no se editan ni se borran: un error se corrige con un contraasiento, que solo puede registrar quien supervisa y queda auditado.
- **Servicio de caja:** `MovimientosCaja` es la única puerta para registrar dinero. Exige una transacción abierta, una sesión abierta (la del usuario en la sucursal activa o una explícita) y bloquea la sesión con `lockForUpdate`.
- **Pantallas mobile-first:**
  - "Mi caja": abrir, ver saldo esperado por medio y movimientos, registrar ingresos y egresos, cerrar con arqueo;
  - sesiones de la sucursal, con detalle y contraasiento, para quien supervisa;
  - configuración de medios de pago, planes de cuotas y cajas.
- **Permisos:** `caja.operar` (Cajero, Vendedor y Gerente), `caja.supervisar` (Gerente) y `caja.configurar` (solo el Administrador).

## Capabilities

### New Capabilities
- `medios-pago`: catálogo configurable de medios de pago y planes de cuotas.
- `caja`: cajas por sucursal, sesiones con apertura, arqueo y cierre, movimientos de dinero inmutables con contraasiento, el servicio para otros módulos y los permisos de caja.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. -->

## Impact

- **Código nuevo:**
  - migraciones de `medios_pago`, `planes_cuota`, `cajas`, `caja_sesiones`, `caja_sesion_arqueos` y `caja_movimientos`;
  - modelos, factories, enums `TipoMedioPago` y `TipoMovimientoCaja`, excepciones de caja;
  - servicios `MovimientosCaja` y `SesionesCaja`, más el auxiliar `Importes` para aritmética en centavos;
  - Form Requests, policies, controladores y vistas;
  - seeders `MedioPagoSeeder` y `CajaSeeder`.
- **Archivos existentes que cambian:**
  - `app/Enums/Permiso.php` y `app/Enums/Rol.php`: permisos y matriz;
  - `database/seeders/DatabaseSeeder.php`;
  - `routes/web.php` y `resources/views/layouts/navigation.blade.php`;
  - `tests/Feature/Seeders/DatosInicialesTest.php` y `tests/Feature/NavegacionTest.php`.
- **Base de datos:** seis tablas nuevas en `comercio` (migración autorizada para este cambio).
- **Dependencias:** ninguna nueva.
- **Relación con otros cambios:**
  - depende de `fase-1-base` (sucursal activa, auditoría, dispositivo, permisos);
  - Ventas (VEN-01), Cobranzas (Fase 2) y Compras van a registrar dinero con `MovimientosCaja`;
  - el reporte de cierre de caja va en el cambio de reportes: los arqueos quedan guardados por medio de pago.
- **Fuera de alcance:**
  - conciliación de tarjetas y QR (CAJ-04, Fase 3) y la tabla `acreditaciones`;
  - cobranzas de cuenta corriente (Fase 2);
  - reporte de cierre de caja;
  - autorización del Gerente para aceptar diferencias (por ahora alcanza con la justificación auditada).
