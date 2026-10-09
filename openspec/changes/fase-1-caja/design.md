# Design

## Context

- **Base existente:**
  - `fase-1-base`: `SucursalActiva`, `RegistradorAuditoria` (dentro de la misma `DB::transaction`), `IdentificarDispositivo` (UUID del navegador en la solicitud), permisos con `Permiso` + `Rol::permisos()` y `Gate::before` para el Administrador.
  - `fase-1-stock`: `MovimientosStock` fija el patrón de "puerta única" (transacción obligatoria, `lockForUpdate`, movimientos inmutables con `reversa_de_id`, origen morph). Este cambio lo replica para el dinero.
  - `CalculadoraIva` trabaja en centavos enteros; `Formato::importe()` para mostrar.
- **Doc 05:** `medios_pago`, `planes_cuota`, `cajas`, `caja_sesiones (caja_id, user_id, apertura, fondo_inicial, cierre, esperado, contado, diferencia, justificacion)` y `caja_movimientos (caja_sesion_id, fecha, tipo, medio_pago_id, importe, motivo, origen, reversa_de_id, user_id, dispositivo)`.
- Ver proposal.md para el alcance y `specs/` para el comportamiento.

## Goals / Non-Goals

**Goals:**
- Una sola puerta para registrar dinero (`MovimientosCaja`), que Ventas, Cobranzas y Compras llamen dentro de su transacción, igual que `MovimientosStock`.
- Arqueo por medio de pago guardado al cerrar, listo para el reporte de cierre.
- Uso cómodo desde el celular: "Mi caja" en una sola pantalla.

**Non-Goals:**
- Conciliación de acreditaciones (CAJ-04), cheques, cobranzas.
- Turnos con horario predefinido: un "turno" es una sesión (apertura → cierre).
- Aprobación del Gerente para aceptar diferencias.
- Cierre de la sesión de otro usuario (ver Riesgos).

## Decisions

### D1. Esquema
- `medios_pago`: `nombre varchar(50) unique`, `tipo varchar(30)` (enum D2), `impacta_caja bool`, `dias_acreditacion smallint unsigned default 0`, `requiere_referencia bool`, `activo bool` (índice), `timestamps`.
- `planes_cuota`: `medio_pago_id` FK, `cuotas tinyint unsigned`, `recargo_pct decimal(5,2)`, `activo`, `timestamps`; único `(medio_pago_id, cuotas)`.
- `cajas`: `sucursal_id` FK, `nombre varchar(50)`, `activa bool`, `timestamps`; único `(sucursal_id, nombre)`.
- `caja_sesiones`: `caja_id`, `sucursal_id`, `user_id` FK; `fecha date` (de negocio, la de apertura); `apertura timestamp`, `cierre timestamp null`; `fondo_inicial decimal(14,2)`; `estado varchar(10)` (`abierta`/`cerrada`); `justificacion varchar(500) null`; `dispositivo varchar(36) null`; `timestamps`; índice `(sucursal_id, fecha)`.
  - **Una sesión abierta por caja a nivel base:** columna generada almacenada `caja_abierta_id = IF(estado = 'abierta', caja_id, NULL)` con índice único (un único admite varios NULL). Mismo truco que `variantes.talle_clave`.
  - **Una sesión abierta por usuario y sucursal:** `usuario_abierto_id = IF(estado = 'abierta', user_id, NULL)` con único `(sucursal_id, usuario_abierto_id)`.
  - El servicio además bloquea la fila de la caja (`lockForUpdate`) y verifica antes de insertar, para dar un error claro en vez de una violación de índice.
- `caja_sesion_arqueos` (tabla hija, no JSON): `caja_sesion_id`, `medio_pago_id`, `esperado decimal(14,2)`, `contado decimal(14,2) null`, `diferencia decimal(14,2) null`, `created_at`; único `(caja_sesion_id, medio_pago_id)`. `contado` y `diferencia` son NULL para los medios que no se cuentan (tarjetas, transferencias): igual se guarda su esperado, así el reporte de cierre tiene el total por medio aunque luego cambie `impacta_caja`.
  - *Alternativa descartada:* JSON en `caja_sesiones`. No se puede sumar ni filtrar por medio en SQL y no tiene FK.
- `caja_movimientos`: `caja_sesion_id`, `sucursal_id`, `fecha date`, `tipo varchar(20)`, `medio_pago_id`, `importe decimal(14,2)` con signo y distinto de 0, `motivo varchar(255) null`, `referencia varchar(100) null`, `origen` morph nullable, `reversa_de_id` FK a sí misma **única** (un movimiento se revierte una sola vez), `user_id` FK nullable, `dispositivo varchar(36) null`, solo `created_at`; índices `(sucursal_id, fecha)` y `(caja_sesion_id, medio_pago_id)`.
- Todas las FK son `restrictOnDelete`.

### D2. Enums
- `TipoMedioPago`: `Efectivo`, `TarjetaDebito`, `TarjetaCredito`, `Transferencia`, `Qr`, `CuentaCorriente`, `SaldoAFavor`, con `etiqueta()`, `admitePlanes()` (solo crédito) y `admiteMovimientoManual()` (todos salvo cuenta corriente y saldo a favor, que viven en la cuenta del cliente).
- `TipoMovimientoCaja`: `Apertura` (+), `Ingreso` (+), `Egreso` (−), `Venta` (+), `Devolucion` (−), `Cobranza` (+), `PagoProveedor` (−). `signo()` como en stock y `exigeMotivo()` (ingreso y egreso). Los tipos de Ventas/Cobranzas/Compras quedan definidos para que esos módulos no tengan que modificar el enum.

### D3. Contraasiento
- Un contraasiento conserva el **tipo** y el **medio** del original, con importe opuesto y `reversa_de_id`. Así los totales por tipo y por medio se netean solos en los reportes, sin un tipo "contraasiento" que haya que restar.
- La regla de signo de D2 no aplica a los contraasientos; en su lugar el importe debe ser exactamente el opuesto del original.
- Solo se contraasientan movimientos de **sesiones abiertas**, y el contraasiento va a la misma sesión. *Alternativa descartada:* registrarlo en la sesión abierta actual cuando la original está cerrada. Movería el esperado del efectivo de hoy por un error de ayer, que ya se reflejó (y justificó) en la diferencia del arqueo de ayer.
- La pantalla solo ofrece contraasentar movimientos manuales (apertura, ingreso, egreso). Los de venta o cobranza los revertirán sus módulos al anular, con el mismo método del servicio.
- Se audita `caja.contraasiento` con original, importe y motivo.

### D4. Servicio `MovimientosCaja`, la API para los módulos
```
registrar(TipoMovimientoCaja, MedioPago, string $importe, ?string $motivo = null, ?Model $origen = null,
          ?CajaSesion $sesion = null, ?string $referencia = null, ?CarbonInterface $fecha = null): CajaMovimiento
contraasentar(CajaMovimiento $original, string $motivo): CajaMovimiento
sesionAbierta(?User $user = null, ?Sucursal $sucursal = null): ?CajaSesion
```
1. Exige transacción abierta (`LogicException` si no la hay).
2. Valida importe (decimal con hasta dos decimales, distinto de 0, signo por tipo), motivo cuando el tipo lo exige, medio activo y referencia cuando el medio la requiere (`InvalidArgumentException`).
3. Resuelve la sesión: la indicada, o la abierta del usuario autenticado en la sucursal activa. La bloquea con `lockForUpdate` y verifica que siga abierta (`CajaNoAbierta` si no hay o está cerrada).
4. Inserta el movimiento con la sucursal de la sesión, `user_id = Auth::id()` y el dispositivo de la solicitud.
- El cierre también bloquea la sesión, así ningún movimiento se cuela entre el cálculo del esperado y el cambio de estado.
- *Nombre:* `MovimientosCaja` y no `Caja`, para no chocar con el modelo `Caja` y mantener la simetría con `MovimientosStock`.

### D5. Servicio `SesionesCaja` (apertura y cierre)
- `abrir(Caja, string $fondo): CajaSesion`: transacción propia; bloquea la caja; rechaza con `CajaYaAbierta` si la caja tiene sesión abierta o el usuario ya tiene una en la sucursal; crea la sesión y, si el fondo es > 0, registra el movimiento `Apertura` con el primer medio activo de tipo efectivo (si no hay, rechaza). Audita `caja.apertura`.
- `cerrar(CajaSesion, array $contado, ?string $justificacion): CajaSesion`: transacción propia; bloquea la sesión; calcula el esperado por medio (`SUM(importe)` agrupado); exige contado para cada medio que impacta en caja (activos o con movimientos); calcula diferencias en centavos; si hay alguna ≠ 0 exige justificación (`InvalidArgumentException`); guarda arqueos y estado. Audita `caja.cierre` siempre y `caja.diferencia` cuando hay diferencia.
- Las reglas de negocio se validan también en los Form Requests (`after()`) para mostrar errores en el formulario; el servicio las repite porque es la fuente de verdad.

### D6. Aritmética
- Nuevo auxiliar `App\Services\Importes` con `aCentavos(string): int` y `aDecimal(int): string` (mismo algoritmo que los privados de `CalculadoraIva`, que no se toca). Esperado, contado y diferencia se calculan en centavos enteros; nunca floats.

### D7. Inmutabilidad
- `CajaMovimiento` y `CajaSesionArqueo`: `LogicException` en `updating` y `deleting`.
- `CajaSesion`: `LogicException` en `deleting` y en `updating` si ya estaba cerrada.

### D8. Permisos y autorización
- `caja.operar`: Cajero, Vendedor, Gerente. `caja.supervisar`: Gerente. `caja.configurar`: nadie en la matriz; el Administrador lo tiene por `Gate::before`. Configurar medios es una decisión del dueño (doc 04: "Administrador configura medios de pago, planes de cuotas…").
- `CajaSesionPolicy::view`: el dueño de la sesión o `caja.supervisar`, siempre que la sesión sea de la sucursal activa. Las sesiones y movimientos de otra sucursal responden 404 (no se revela su existencia).
- Rutas con middleware `can:` por grupo y Form Requests con `authorize()`.

### D9. Pantallas y rutas (bajo `/caja`, sucursal activa)
- `GET /caja` "Mi caja": si no hay sesión, formulario de apertura (caja si hay más de una activa libre, fondo); si hay, esperado por medio, formulario de ingreso/egreso, movimientos y enlace a cerrar.
- `POST /caja/abrir`, `POST /caja/movimientos`, `GET|PUT /caja/cierre`.
- `GET /caja/sesiones` (supervisar; filtros fecha y estado), `GET /caja/sesiones/{sesion}` (dueño o supervisar), `POST /caja/movimientos/{movimiento}/contraasiento` (supervisar).
- Configuración: `/caja/medios-pago` (index, create, store, edit, update), planes anidados en la edición del medio (`/caja/medios-pago/{medio}/planes`), `/caja/cajas` (de la sucursal activa).
- Vistas con tarjetas, `x-text-input`, `inputmode="decimal"`, sin JS nuevo: el formulario de ingreso/egreso usa radios para el tipo.

### D10. Seeders
- `MedioPagoSeeder` (`firstOrCreate` por nombre) y `CajaSeeder` ("Caja principal" por sucursal activa), agregados a `DatabaseSeeder` después de `SucursalSeeder`.

## Risks / Trade-offs

- [Un cajero se va sin cerrar] → la caja queda bloqueada para otros. Mitigación: el dueño la cierra al volver; el cierre por un supervisor queda como mejora (requiere definir quién cuenta).
- [Columnas generadas] → dependen de MariaDB/MySQL; el proyecto ya usa una (`talle_clave`) y los tests corren sobre MariaDB.
- [Contraasiento solo en sesiones abiertas] → un error detectado después del cierre no se corrige con movimiento: queda en la diferencia justificada del arqueo, que es lo que físicamente pasó.
- [Egreso mayor al efectivo disponible] → no se bloquea: el arqueo lo pone en evidencia. Ventas podría querer bloquearlo con el vuelto; se decide en ese cambio.

## Migration Plan

- `php artisan migrate` crea las seis tablas; `MedioPagoSeeder`, `CajaSeeder` y `RolesYPermisosSeeder` cargan datos y permisos. Son idempotentes.
- Rollback: `migrate:rollback` del lote borra las tablas nuevas (solo si no hay datos reales de caja).
