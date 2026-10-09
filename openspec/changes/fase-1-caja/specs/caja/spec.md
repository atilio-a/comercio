# Spec Delta

## Purpose

Registra el dinero que entra y sale de cada caja de la sucursal: apertura con fondo inicial, ingresos y egresos, arqueo y cierre por usuario, movimientos inmutables corregidos con contraasiento y la puerta única que usarán ventas, cobranzas y compras.

## ADDED Requirements

### Requirement: Permisos de caja
El sistema SHALL controlar la caja con `caja.operar` (abrir, operar y cerrar la propia caja), `caja.supervisar` (ver todas las sesiones de la sucursal y registrar contraasientos) y `caja.configurar`. Cajero, Vendedor y Gerente SHALL tener `caja.operar`; solo el Gerente SHALL tener además `caja.supervisar`; Depósito SHALL no tener permisos de caja; el Administrador SHALL tener todos.

#### Scenario: Depósito intenta abrir caja
- **WHEN** un usuario con rol Depósito intenta abrir una caja
- **THEN** el sistema responde con un error de autorización y no se crea ninguna sesión

#### Scenario: Cajero intenta ver las sesiones de la sucursal
- **WHEN** un Cajero abre el listado de sesiones de la sucursal
- **THEN** el sistema responde con un error de autorización

### Requirement: Cajas por sucursal
Cada sucursal SHALL poder tener una o más cajas con nombre único dentro de la sucursal y estado activo. Los datos iniciales SHALL crear "Caja principal" en cada sucursal activa, sin duplicarla. Solo las cajas activas de la sucursal activa SHALL poder abrirse.

#### Scenario: Datos iniciales
- **WHEN** se ejecutan los datos iniciales dos veces con dos sucursales activas
- **THEN** cada sucursal tiene una sola "Caja principal"

### Requirement: Apertura de caja
Un usuario con `caja.operar` SHALL poder abrir una caja activa de la sucursal activa con un fondo inicial mayor o igual a 0. La sesión SHALL guardar caja, sucursal, usuario, fecha de negocio, momento de apertura, fondo inicial y dispositivo. Un fondo mayor a 0 SHALL registrarse como movimiento de apertura en efectivo. Una caja SHALL tener como máximo una sesión abierta, y un usuario como máximo una por sucursal.

#### Scenario: Apertura con fondo
- **WHEN** un Cajero abre "Caja principal" con un fondo de 5.000,00
- **THEN** queda una sesión abierta a su nombre y un movimiento de apertura de +5.000,00 en efectivo

#### Scenario: Caja ya abierta
- **WHEN** otro usuario intenta abrir una caja que ya tiene una sesión abierta
- **THEN** el sistema lo rechaza y la caja sigue con una sola sesión abierta

#### Scenario: Usuario con otra caja abierta
- **WHEN** un usuario con una sesión abierta en la sucursal intenta abrir otra caja de la misma sucursal
- **THEN** el sistema lo rechaza

### Requirement: Ingresos y egresos varios
Un usuario con su sesión abierta SHALL poder registrar ingresos y egresos varios con un medio de pago activo (que no sea cuenta corriente ni saldo a favor), un importe mayor a 0 con hasta dos decimales y un motivo obligatorio. Si el medio requiere referencia, la referencia SHALL ser obligatoria. El egreso SHALL guardarse con importe negativo.

#### Scenario: Egreso con motivo
- **WHEN** el Cajero registra un egreso de 1.200,50 en efectivo con motivo "Compra de bolsas"
- **THEN** se guarda un movimiento de egreso de -1.200,50 con ese motivo, su usuario y su dispositivo

#### Scenario: Egreso sin motivo
- **WHEN** el Cajero registra un egreso sin motivo
- **THEN** el sistema lo rechaza con un error de validación y no se registra ningún movimiento

#### Scenario: Sin caja abierta
- **WHEN** un usuario sin sesión abierta intenta registrar un ingreso
- **THEN** el sistema lo rechaza y no se registra ningún movimiento

### Requirement: Movimientos de caja inmutables
Cada movimiento de dinero SHALL guardar sesión, sucursal, fecha de negocio, momento de registro, tipo, medio de pago, importe con signo, motivo, referencia, origen opcional, usuario y dispositivo. Los movimientos SHALL no poder editarse ni borrarse, y una sesión cerrada SHALL no aceptar movimientos ni cambios.

#### Scenario: Intento de modificar un movimiento
- **WHEN** cualquier usuario intenta modificar o borrar un movimiento de caja
- **THEN** el sistema no lo permite

#### Scenario: Sesión cerrada
- **WHEN** se intenta registrar un movimiento en una sesión cerrada
- **THEN** el sistema lo rechaza

### Requirement: Contraasiento
Un usuario con `caja.supervisar` SHALL poder corregir un movimiento de una sesión abierta de su sucursal registrando un contraasiento: un movimiento del mismo tipo y medio, con el importe opuesto, motivo obligatorio y referencia al original. Un movimiento SHALL poder revertirse una sola vez, un contraasiento SHALL no revertirse y cada contraasiento SHALL quedar auditado. Los movimientos de sesiones cerradas SHALL corregirse con la diferencia justificada del arqueo.

#### Scenario: Corrección de un egreso duplicado
- **WHEN** el Gerente contraasienta un egreso de -1.000,00 con motivo "Cargado dos veces"
- **THEN** se registra un egreso de +1.000,00 que referencia al original, el saldo esperado vuelve al valor previo y queda la auditoría

#### Scenario: Segundo contraasiento
- **WHEN** se intenta contraasentar un movimiento que ya fue revertido
- **THEN** el sistema lo rechaza

### Requirement: Saldo esperado por medio
Mientras la sesión está abierta, el sistema SHALL mostrar al usuario el saldo esperado por medio de pago (la suma de los importes de la sesión) y sus movimientos, del más reciente al más antiguo.

#### Scenario: Saldo esperado
- **WHEN** una sesión tiene apertura de 5.000,00, un ingreso de 300,00 y un egreso de 1.200,50 en efectivo
- **THEN** el esperado en efectivo es 4.099,50

### Requirement: Arqueo y cierre
El usuario SHALL cerrar su propia sesión informando lo contado de cada medio que impacta en caja. El sistema SHALL guardar por medio el esperado, el contado y la diferencia (contado menos esperado), además del total esperado de los medios que no se cuentan. Si alguna diferencia es distinta de 0, la justificación SHALL ser obligatoria y el cierre con diferencia SHALL quedar auditado.

#### Scenario: Cierre sin diferencias
- **WHEN** el esperado en efectivo es 4.099,50 y el Cajero cuenta 4.099,50
- **THEN** la sesión queda cerrada con diferencia 0 y sin justificación obligatoria

#### Scenario: Diferencia sin justificación
- **WHEN** el Cajero cuenta 4.000,00 contra 4.099,50 esperados y no escribe justificación
- **THEN** el sistema rechaza el cierre y la sesión sigue abierta

#### Scenario: Diferencia justificada
- **WHEN** el Cajero cuenta 4.000,00 contra 4.099,50 esperados y justifica "Faltante de cambio"
- **THEN** la sesión queda cerrada con diferencia -99,50, la justificación y una auditoría del cierre con diferencia

### Requirement: Aislamiento por sucursal
Toda consulta y operación de caja SHALL referirse a la sucursal activa del usuario. Un usuario SHALL no poder ver ni operar sesiones, cajas o movimientos de otra sucursal.

#### Scenario: Sesión de otra sucursal
- **WHEN** un Gerente que opera en "Norte" pide el detalle de una sesión de "Centro"
- **THEN** el sistema responde que no la encuentra

### Requirement: Registro de dinero para otros módulos
El sistema SHALL ofrecer una única forma de registrar movimientos de dinero para ventas, cobranzas y compras: dentro de una transacción de base de datos, sobre la sesión abierta del usuario en la sucursal activa o una sesión abierta indicada, bloqueando la sesión hasta el final de la transacción. Fuera de una transacción el registro SHALL rechazarse.

#### Scenario: Registro fuera de una transacción
- **WHEN** un módulo intenta registrar un movimiento sin una transacción abierta
- **THEN** el sistema lo rechaza y no guarda nada

#### Scenario: Venta con origen
- **WHEN** un módulo registra una venta de 2.500,00 con tarjeta de débito indicando la venta como origen
- **THEN** el movimiento queda en la sesión abierta del usuario con ese origen y suma al esperado de tarjeta de débito
