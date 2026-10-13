# Spec Delta

## Purpose

Registra la cuenta corriente de cada cliente con movimientos inmutables de debe y haber, para que el saldo a favor de cambios y devoluciones (VEN-03) viva en ella y la Fase 2 construya sobre la misma base las ventas a cuenta y las cobranzas.

## ADDED Requirements

### Requirement: Cuenta corriente del cliente
Cada cliente SHALL tener a lo sumo una cuenta corriente con crédito habilitado o no, límite de crédito, marca de bloqueo y saldo. El saldo SHALL ser la suma de los débitos menos la suma de los créditos: positivo significa que el cliente debe y negativo que tiene saldo a favor.

#### Scenario: Saldo a favor
- **WHEN** una cuenta tiene un haber de 5000 y un debe de 2000
- **THEN** su saldo es -3000, es decir 3000 de saldo a favor

### Requirement: Creación automática sin crédito
Cuando un módulo registra un movimiento para un cliente sin cuenta corriente, el sistema SHALL crearle la cuenta con el crédito no habilitado, límite 0 y sin bloqueo. Consultar la ficha del cliente SHALL no crear la cuenta.

#### Scenario: Primer saldo a favor
- **WHEN** se acredita un saldo a favor de 8000 a un cliente sin cuenta corriente
- **THEN** el cliente queda con una cuenta sin crédito habilitado y saldo -8000

### Requirement: Movimientos de cuenta corriente
Cada movimiento SHALL guardar la cuenta, la sucursal, la fecha de negocio, el tipo (debe o haber), un importe mayor que cero, el concepto, la operación de origen, el vencimiento opcional, el saldo resultante, el usuario y el dispositivo. Los movimientos y el saldo SHALL registrarse en la misma transacción que la operación que los origina.

#### Scenario: Acreditación registrada
- **WHEN** se acredita 8000 por "Devolución ticket 15" en la sucursal activa
- **THEN** queda un movimiento haber de 8000 con ese concepto, la sucursal, la fecha de hoy, el usuario, el dispositivo y saldo resultante -8000

#### Scenario: Importe inválido
- **WHEN** un módulo intenta registrar un movimiento con importe 0 o negativo
- **THEN** el sistema lo rechaza sin registrar nada

#### Scenario: Fuera de una transacción
- **WHEN** un módulo intenta registrar un movimiento fuera de una transacción
- **THEN** el sistema lo rechaza sin registrar nada

### Requirement: Débitos sin crédito habilitado
Un débito que deje la cuenta con deuda (saldo positivo) SHALL rechazarse si la cuenta no admite crédito, está bloqueada o supera el límite de crédito. Una cuenta sin crédito habilitado SHALL admitir débitos que solo consuman saldo a favor existente.

#### Scenario: Débito que consume saldo a favor
- **WHEN** un cliente sin crédito tiene 8000 de saldo a favor y se debitan 5000
- **THEN** el débito se registra y quedan 3000 de saldo a favor

#### Scenario: Débito que generaría deuda
- **WHEN** un cliente sin crédito tiene 3000 de saldo a favor y se intentan debitar 5000
- **THEN** el sistema rechaza el débito indicando que solo puede usar 3000 de saldo a favor
- **AND** el saldo sigue en -3000

### Requirement: Movimientos inmutables y contraasiento
Los movimientos de cuenta corriente SHALL no modificarse ni borrarse. Un error SHALL corregirse con un contraasiento: un movimiento del tipo opuesto por el mismo importe, vinculado al original, con motivo obligatorio y auditado. Un movimiento SHALL revertirse una sola vez y un contraasiento no SHALL revertirse. Revertir un haber SHALL respetar la regla de débitos sin crédito.

#### Scenario: Contraasiento de un saldo a favor
- **WHEN** se revierte con motivo un haber de 8000 que no se usó
- **THEN** se registra un debe de 8000 vinculado al original, el saldo vuelve a 0 y queda auditado

#### Scenario: Doble reversión
- **WHEN** se intenta revertir un movimiento ya revertido
- **THEN** el sistema lo rechaza

#### Scenario: Intento de modificar un movimiento
- **WHEN** se intenta modificar o borrar un movimiento de cuenta corriente
- **THEN** el sistema lo impide

### Requirement: Recalcular saldos
El sistema SHALL ofrecer un comando que compare el saldo guardado de cada cuenta con el que resulta de sus movimientos, informe las diferencias y, si se pide, las corrija auditando cada corrección. Sin diferencias o corregidas, el comando SHALL terminar sin error.

#### Scenario: Saldo desfasado
- **WHEN** el saldo guardado de una cuenta no coincide con sus movimientos y se ejecuta el comando con la opción de corregir
- **THEN** el saldo se corrige al valor de los movimientos y la corrección queda auditada

### Requirement: Estado de cuenta en la ficha del cliente
La ficha del cliente SHALL mostrar a quien tenga `cuentas_corrientes.ver` (Cajero y Gerente) el saldo de la cuenta y sus movimientos del más reciente al más antiguo, con fecha, concepto, debe o haber, saldo resultante y si es un contraasiento. Sin ese permiso, la ficha SHALL no mostrar la cuenta corriente. No SHALL haber ajustes manuales de cuenta corriente en esta fase.

#### Scenario: Cajero ve el estado de cuenta
- **WHEN** un Cajero abre la ficha de un cliente con 3000 de saldo a favor
- **THEN** ve "Saldo a favor" por 3000 y los movimientos de la cuenta

#### Scenario: Vendedor no ve la cuenta corriente
- **WHEN** un Vendedor abre la ficha del mismo cliente
- **THEN** no ve el saldo ni los movimientos de la cuenta corriente
