# Spec Delta

## Purpose

Define con qué se puede cobrar o pagar en el comercio: un catálogo de medios de pago configurable sin tocar código y los planes de cuotas con recargo de las tarjetas de crédito.

## ADDED Requirements

### Requirement: Catálogo de medios de pago
El sistema SHALL mantener un catálogo de medios de pago con nombre único, tipo (efectivo, tarjeta de débito, tarjeta de crédito, transferencia, QR, cuenta corriente o saldo a favor), si impacta en la caja física, días de acreditación (entero de 0 a 365), si requiere referencia y estado activo. Los medios SHALL no borrarse: se desactivan, y un medio inactivo SHALL no ofrecerse para registrar movimientos nuevos.

#### Scenario: Alta de un medio
- **WHEN** el Administrador crea "Mercado Pago" de tipo QR, sin impacto en caja, con 1 día de acreditación y referencia obligatoria
- **THEN** el medio queda activo con esos datos y la alta queda auditada

#### Scenario: Nombre repetido
- **WHEN** se intenta crear un medio con el nombre de otro existente
- **THEN** el sistema lo rechaza con un error de validación

#### Scenario: Medio desactivado
- **WHEN** el Administrador desactiva "Transferencia"
- **THEN** deja de ofrecerse para nuevos ingresos y egresos, y los movimientos anteriores lo siguen mostrando

### Requirement: Planes de cuotas
Cada medio de tipo tarjeta de crédito SHALL poder tener planes de cuotas con cantidad de cuotas (1 a 60, única por medio), recargo porcentual con hasta dos decimales entre 0 y 100, y estado activo. Los medios de otro tipo SHALL no admitir planes. Las altas y cambios SHALL quedar auditados.

#### Scenario: Plan con recargo
- **WHEN** el Administrador agrega a "Tarjeta de crédito" un plan de 3 cuotas con 12,5 % de recargo
- **THEN** el plan queda activo con recargo 12.50

#### Scenario: Cuotas repetidas
- **WHEN** se intenta agregar un segundo plan de 3 cuotas al mismo medio
- **THEN** el sistema lo rechaza con un error de validación

#### Scenario: Plan para un medio que no es tarjeta de crédito
- **WHEN** se intenta agregar un plan de cuotas a "Efectivo"
- **THEN** el sistema no lo permite

### Requirement: Configuración restringida
Solo los usuarios con `caja.configurar` SHALL poder ver y modificar la configuración de medios de pago, planes de cuotas y cajas. Ese permiso SHALL corresponder solo al Administrador.

#### Scenario: Gerente intenta configurar
- **WHEN** un Gerente abre la configuración de medios de pago o envía un alta
- **THEN** el sistema responde con un error de autorización

### Requirement: Medios de pago iniciales
Los datos iniciales SHALL crear, sin duplicar ni pisar cambios del Administrador, los medios Efectivo (impacta en caja), Tarjeta de débito, Tarjeta de crédito (con un plan de 1 cuota sin recargo), Transferencia, QR / billetera, Cuenta corriente y Saldo a favor.

#### Scenario: Datos iniciales ejecutados dos veces
- **WHEN** se ejecutan los datos iniciales dos veces y el Administrador había desactivado "QR / billetera" entre medio
- **THEN** hay siete medios, sin duplicados, y "QR / billetera" sigue inactivo
