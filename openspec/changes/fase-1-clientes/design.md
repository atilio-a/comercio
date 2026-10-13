# Design

## Context

- **Base existente:**
  - `fase-1-caja`: `MovimientosCaja` es la única puerta del dinero en caja (transacción obligatoria, `lockForUpdate`, inmutabilidad con contraasiento, `Importes` en centavos, usuario y dispositivo). La cuenta corriente sigue el mismo contrato.
  - `fase-1-stock`: `MovimientosStock` guarda el saldo resultante en cada movimiento y `stock:recalcular` compara el saldo cacheado con el kardex.
  - `fase-1-compras`: `Cuit` y `CuitValido`, ABM auditado con `getDirty()`, búsqueda JSON con `can:` en la ruta, navegación agrupada.
  - `talles-configurables`: parámetro `usar_talles`; ocultar no borra datos.
- Ver proposal.md para el alcance y `specs/` para el comportamiento.

## Goals / Non-Goals

**Goals:**
- Dejar listo el contrato de cuenta corriente que usarán devoluciones (saldo a favor) y ventas (pago con saldo a favor) sin que esos módulos tengan que conocer la regla de crédito.
- Alta de cliente en segundos desde el celular, y un endpoint JSON que la venta pueda reutilizar.

**Non-Goals:**
- Ventas a cuenta, cobranzas, imputación a comprobantes, morosidad y la pantalla para habilitar crédito o fijar límites (Fase 2). Las columnas existen y la regla de crédito ya las respeta.
- Ajustes manuales de cuenta corriente: solo los módulos registran movimientos.
- Historial de ventas real: la ficha deja un parcial reservado sin consultar tablas que no existen.

## Decisions

### D1. Esquema
- `clientes`: `nombre varchar(150)` (índice), `dni_cuit varchar(11) null unique` (solo dígitos; un único admite varios NULL), `telefono varchar(50) null` (índice), `email varchar(150) null`, `talle_habitual varchar(10) null` (mismo largo que `variantes.talle`), `condicion_iva varchar(25) default 'consumidor_final'`, `observaciones varchar(500) null`, `activo bool` (índice), `timestamps`. Sin `sucursal_id`: los clientes son globales.
- `vendedores`: `user_id unique` (FK a `users`), `activo bool default true`, `timestamps`. Las ventas guardarán `vendedor_id`.
- `cuentas_corrientes`: `cliente_id unique`, `admite_credito bool default false`, `limite_credito decimal(14,2) default 0`, `bloqueada bool default false`, `saldo decimal(14,2) default 0`, `timestamps`.
- `cc_movimientos`: `cuenta_id` (FK a `cuentas_corrientes`, nombre del doc 05), `sucursal_id`, `fecha date`, `tipo varchar(5)` (`debe`/`haber`), `importe decimal(14,2)` siempre positivo, `saldo decimal(14,2)` (saldo resultante, como el kardex de stock, para mostrar el estado de cuenta sin recalcular), `concepto varchar(255)`, `origen` morph nullable, `reversa_de_id null unique`, `vencimiento date null`, `user_id null`, `dispositivo varchar(36) null`, `created_at`. Índices `(sucursal_id, fecha)` y `(cuenta_id, id)`.
- Todas las FK `restrictOnDelete`.

### D2. Convención de saldo
- `saldo = Σ debe − Σ haber`. **Positivo = el cliente debe; negativo = saldo a favor.** Es la convención contable de una cuenta deudora y coincide con la de Fase 2 (una venta a cuenta suma). Está en el PHPDoc de `CuentaCorriente` y de `CuentasCorrientes`, y los modelos exponen `saldoAFavor()` y `deuda()` para no repetir el signo en las vistas.
- *Alternativa descartada:* saldo positivo a favor del cliente. Haría que todo el módulo de crédito (Fase 2), que es el uso principal, trabaje con signos negativos.

### D3. Servicio `CuentasCorrientes`
- Contrato como `MovimientosCaja`: transacción obligatoria (`LogicException` fuera de ella), `lockForUpdate` de la fila de la cuenta en cada operación, importes en string con hasta dos decimales calculados en centavos enteros (`Importes`).
- `cuentaDe(Cliente)`: devuelve la cuenta bloqueada y, si no existe, la crea con `admite_credito = false` (regla del doc 05). La creación usa `firstOrCreate`, que en Laravel 12 recurre a `createOrFirst` ante el único `cliente_id`, así dos operaciones simultáneas no crean dos cuentas.
- `acreditar(cliente, importe, concepto, origen?, fecha?, sucursal?)` registra un haber (saldo a favor, devolución, futura cobranza).
- `debitar(cliente, importe, concepto, origen?, fecha?, vencimiento?, sucursal?)` registra un debe. Si el saldo resultante es positivo y mayor que la deuda anterior (o sea, si genera o aumenta deuda), exige `admite_credito`, que la cuenta no esté bloqueada y que el saldo no supere `limite_credito`; si no, lanza `CreditoNoDisponible` con el saldo a favor disponible. Un débito que solo reduce saldo a favor siempre se permite.
- `contraasentar(movimiento, motivo)`: tipo opuesto, mismo importe, misma sucursal, `reversa_de_id`, auditado como `cuenta_corriente.contraasiento`. Una sola vez (único en `reversa_de_id` y verificación con la cuenta bloqueada), nunca un contraasiento. Revertir un haber es un débito y aplica la regla de crédito: no se puede deshacer un saldo a favor que ya se usó en una cuenta sin crédito.
- `saldoSegunMovimientos(cuenta)` y el comando `cuentas-corrientes:recalcular {--corregir}` (mismo patrón que `stock:recalcular`): compara con `SUM(CASE tipo ...)`, corrige bajo `lockForUpdate` y audita `cuenta_corriente.recalculada`.
- La sucursal del movimiento es la indicada o la activa; sin ninguna, `LogicException`.
- Acreditar y debitar no se auditan (el movimiento ya guarda usuario, dispositivo y origen), igual que los movimientos de caja; sí el contraasiento y el recálculo.
- *Alternativa descartada:* que el límite de crédito quede fuera hasta Fase 2. Verificarlo ya es una línea y evita que un módulo de Fase 2 olvide hacerlo; mientras no haya pantalla para habilitar crédito, toda cuenta es sin crédito.

### D4. Inmutabilidad
- `CcMovimiento` lanza `LogicException` en `updating` y `deleting`. `CuentaCorriente` no es inmutable (cambia el saldo), pero solo el servicio la modifica y no se borra.

### D5. Documento del cliente
- Auxiliar `DocumentoCliente` (normalizar, esValido, esCuit, formatear) y regla `DocumentoClienteValido`: tras normalizar (quitar puntos, guiones y espacios), 7 u 8 dígitos (DNI) o un CUIT válido según `Cuit::esValido`. El Form Request normaliza en `prepareForValidation` para que el único funcione con cualquier formato.
- Un DNI y el CUIT de la misma persona son valores distintos y no chocan en el único; se acepta: el comercio usa uno u otro según la condición frente al IVA.
- Condición distinta de consumidor final exige CUIT (11 dígitos): sin CUIT no se le puede facturar como tal.
- Nombre más teléfono o documento obligatorios en el alta completa y en la rápida (mismas reglas, un único Form Request `GuardarClienteRequest`).
- Formato para mostrar: DNI `30.123.456`, CUIT `20-30123456-7`.

### D6. Talle habitual
- El Form Request solo incluye `talle_habitual` en los datos a guardar si el parámetro está habilitado; deshabilitado, el campo no se muestra, no se envía y el valor guardado no se toca. La búsqueda JSON lo devuelve solo con el parámetro habilitado.

### D7. Búsqueda
- Texto libre: `nombre LIKE %q%` o `telefono LIKE %q%`; si el texto normalizado tiene al menos 3 dígitos, además `dni_cuit LIKE %dígitos%` (así un DNI encuentra también el CUIT que lo contiene).
- JSON `GET clientes/buscar?q=` (máx. 20, solo activos, `clientes.ver`) y `POST clientes/rapido` (201 con el cliente, 422 con errores, `clientes.gestionar`). Ambos devuelven el mismo formato: `id`, `nombre`, `documento`, `telefono`, `condicion_iva` y `talle_habitual` si corresponde.

### D8. Vendedores
- La marca se gestiona en el formulario de usuarios (checkbox "Es vendedor") dentro de la transacción de `GestionUsuarios`, que la incluye en la auditoría del usuario como `vendedor: true/false`. Desmarcar desactiva el registro (las ventas futuras lo referencian). En la edición, si el campo no llega, no se toca.
- Servicio `Vendedores::disponibles(?Sucursal)`: vendedores activos con usuario activo y asignado a la sucursal (`sucursal_user`), con el usuario cargado y ordenados por nombre. No se exige un rol concreto: un Cajero o Gerente que vende también es vendedor.
- *Alternativa descartada:* ABM separado de vendedores. Duplica la búsqueda de usuarios y la marca es un dato del usuario.

### D9. Permisos y pantallas
- `clientes.ver` y `clientes.gestionar` (Vendedor, Cajero, Gerente; Administrador por `Gate::before`), `cuentas_corrientes.ver` (Cajero, Gerente). Autorización con `Gate::authorize` en el controlador y `authorize()` en el Form Request, como en compras.
- Rutas `clientes.*` (resource sin destroy), ficha `clientes.show` con el estado de cuenta paginado (25) solo si el usuario tiene `cuentas_corrientes.ver`, y un parcial `clientes.partials.historial-ventas` reservado para el cambio de ventas.
- Navegación: entrada "Clientes" primera del grupo "Ventas y caja" (`clientes.ver`).

## Risks / Trade-offs

- [El saldo cacheado puede desfasarse si alguien escribe por fuera del servicio] → El comando de recálculo lo detecta; los movimientos son la fuente de verdad.
- [Un Vendedor puede ver datos de contacto de todos los clientes] → Es necesario para vender; la cuenta corriente queda reservada a Cajero y Gerente.
- [Búsqueda con `LIKE %...%` sin índice útil] → Volumen esperado de miles de clientes; si crece se agrega un índice FULLTEXT sin cambiar la interfaz.
- [Teléfono como texto libre] → La búsqueda por teléfono depende del formato tipeado; se acepta en esta fase.

## Migration Plan

- `php artisan migrate` crea las cuatro tablas (sin tocar tablas existentes) y `RolesYPermisosSeeder` agrega los permisos. Rollback: `migrate:rollback` del lote, sin datos previos afectados.
