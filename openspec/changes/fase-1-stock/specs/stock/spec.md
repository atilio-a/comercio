# Spec Delta

## Purpose

Lleva las existencias de cada variante en cada sucursal y la historia de cada cambio (kardex), permite cargarlas y corregirlas por conteo, y aplica la regla de stock negativo configurable que usarán ventas, compras y cambios.

## ADDED Requirements

### Requirement: Permisos de stock
El sistema SHALL controlar el stock con los permisos `stock.ver` (consultar stock y kardex) y `stock.ajustar` (ajustar por conteo y editar mínimos). Gerente y Depósito SHALL tener ambos; Vendedor y Cajero SHALL tener solo `stock.ver`; el Administrador SHALL tener todos.

#### Scenario: Cajero intenta ajustar
- **WHEN** un usuario con rol Cajero envía un ajuste por conteo
- **THEN** el sistema responde con un error de autorización y el stock no cambia

#### Scenario: Vendedor consulta el stock
- **WHEN** un usuario con rol Vendedor abre la pantalla de stock
- **THEN** ve las cantidades de la sucursal activa, sin la opción de ajustar

### Requirement: Stock por variante y sucursal
El sistema SHALL llevar, para cada variante y sucursal, una cantidad actual y un stock mínimo. Una variante sin movimientos en una sucursal SHALL figurar con cantidad 0 y mínimo 0. Toda consulta y operación de stock SHALL referirse a la sucursal activa del usuario.

#### Scenario: Variante nueva
- **WHEN** se crea una variante y nadie registró movimientos
- **THEN** su stock en la sucursal activa figura en 0

#### Scenario: Aislamiento por sucursal
- **WHEN** una variante tiene 5 unidades en "Centro" y el usuario opera en "Norte"
- **THEN** ve el stock de esa variante en "Norte", no el de "Centro"

### Requirement: Movimientos de stock inmutables
Cada cambio de cantidad SHALL registrarse como un movimiento con variante, sucursal, tipo (ingreso, egreso, ajuste o transferencia), cantidad con signo, saldo resultante, fecha de negocio, momento de registro, motivo, origen opcional, usuario y dispositivo. La cantidad actual SHALL ser siempre el saldo del último movimiento. Los movimientos SHALL no poder editarse ni borrarse.

#### Scenario: Saldo encadenado
- **WHEN** una variante tiene stock 3 y se registra un egreso de 1 y luego un ajuste de +4
- **THEN** los movimientos guardan saldos 2 y 6, y el stock actual es 6

#### Scenario: Intento de modificar un movimiento
- **WHEN** cualquier usuario intenta modificar o borrar un movimiento de stock
- **THEN** el sistema no lo permite

### Requirement: Ajuste por conteo
Un usuario con `stock.ajustar` SHALL poder ingresar la cantidad contada de una o más variantes de un producto, con un motivo obligatorio y una fecha de negocio que no sea futura (hoy por defecto). Por cada variante cuya cantidad contada difiera de la actual, el sistema SHALL registrar un movimiento de ajuste por la diferencia y SHALL auditarlo. Las variantes sin cantidad ingresada o sin diferencia SHALL no generar movimientos. La cantidad contada SHALL ser un entero mayor o igual a 0.

#### Scenario: Carga de stock inicial
- **WHEN** con stock 0 se cuentan 4 unidades de negro / 37 y 2 de negro / 38 con motivo "Stock inicial"
- **THEN** se registran dos ajustes de +4 y +2 con ese motivo, y el stock queda en 4 y 2

#### Scenario: Corrección a la baja
- **WHEN** negro / 37 tiene stock 4 y se cuentan 3
- **THEN** se registra un ajuste de -1 y el stock queda en 3

#### Scenario: Sin diferencias
- **WHEN** se cuenta la misma cantidad que el stock actual
- **THEN** no se registra ningún movimiento ni auditoría

#### Scenario: Motivo faltante o cantidad inválida
- **WHEN** se envía un ajuste sin motivo, con una cantidad negativa o con fecha futura
- **THEN** el sistema rechaza el ajuste con un error de validación y el stock no cambia

#### Scenario: Ajuste auditado
- **WHEN** un usuario ajusta negro / 37 de 4 a 3
- **THEN** la auditoría registra el usuario, la variante, la sucursal, la cantidad anterior 4, la nueva 3 y el motivo

### Requirement: Stock negativo configurable
Un egreso que deje la cantidad por debajo de 0 SHALL rechazarse si el parámetro "Permitir venta con stock negativo" está deshabilitado. Si está habilitado, el egreso SHALL registrarse y SHALL quedar auditado como stock negativo. Los movimientos de una misma variante y sucursal SHALL procesarse de a uno, de modo que dos egresos simultáneos no dejen un saldo inconsistente.

#### Scenario: Egreso sin stock con el parámetro deshabilitado
- **WHEN** el parámetro está deshabilitado, una variante tiene stock 1 y se intenta un egreso de 2
- **THEN** el egreso se rechaza indicando el stock disponible y no se registra ningún movimiento

#### Scenario: Egreso sin stock con el parámetro habilitado
- **WHEN** el parámetro está habilitado, una variante tiene stock 1 y se registra un egreso de 2
- **THEN** el stock queda en -1
- **AND** la auditoría registra el egreso que dejó stock negativo con usuario, variante, sucursal y saldo resultante

#### Scenario: Egresos simultáneos
- **WHEN** con stock 1 y el parámetro deshabilitado llegan al mismo tiempo dos egresos de 1
- **THEN** uno se registra con saldo 0 y el otro se rechaza

### Requirement: Transferencias deshabilitadas
Los movimientos de tipo transferencia SHALL rechazarse mientras las transferencias entre sucursales no estén habilitadas.

#### Scenario: Intento de transferencia
- **WHEN** se intenta registrar un movimiento de tipo transferencia
- **THEN** el sistema lo rechaza indicando que las transferencias no están habilitadas

### Requirement: Stock mínimo
Un usuario con `stock.ajustar` SHALL poder definir el stock mínimo (entero mayor o igual a 0) de cada variante en la sucursal activa. Cada cambio de mínimo SHALL auditarse. El mínimo SHALL no generar movimientos.

#### Scenario: Definir mínimo
- **WHEN** se define mínimo 2 para negro / 37
- **THEN** el mínimo queda en 2, la auditoría registra el cambio y el kardex no cambia

### Requirement: Consulta de stock actual
Un usuario con `stock.ver` SHALL poder consultar el stock de la sucursal activa por variante, con producto, color, talle si existe, cantidad y mínimo. SHALL poder buscar por nombre de producto, SKU o código de barras exacto, filtrar por categoría y mostrar solo las variantes bajo mínimo (cantidad menor o igual al mínimo, con mínimo mayor a 0). Por defecto SHALL mostrar solo variantes vendibles.

#### Scenario: Filtro bajo mínimo
- **WHEN** negro / 37 tiene stock 1 y mínimo 2, y negro / 38 tiene stock 5 y mínimo 2
- **THEN** el filtro "bajo mínimo" muestra solo negro / 37

#### Scenario: Stock negativo visible
- **WHEN** una variante tiene stock -1
- **THEN** la pantalla muestra -1 destacado como stock negativo

### Requirement: Kardex por variante
Un usuario con `stock.ver` SHALL poder ver el kardex de una variante en la sucursal activa: movimientos del más reciente al más antiguo, con fecha de negocio, momento de registro, tipo, cantidad, saldo, motivo y usuario, paginados y filtrables por período de fecha de negocio.

#### Scenario: Kardex de una variante
- **WHEN** un usuario abre el kardex de negro / 37 después de un ajuste inicial y una corrección
- **THEN** ve los dos movimientos con sus saldos, del más reciente al más antiguo

### Requirement: Recalcular saldos
El sistema SHALL ofrecer un comando de consola que compare la cantidad guardada de cada stock con el saldo de su último movimiento. SHALL informar las diferencias y, si se pide expresamente, SHALL corregirlas y auditar la corrección.

#### Scenario: Saldo desalineado
- **WHEN** un stock guarda 5 pero su último movimiento tiene saldo 4 y se ejecuta el comando con la opción de corregir
- **THEN** el comando informa la diferencia, deja el stock en 4 y la auditoría registra la corrección
