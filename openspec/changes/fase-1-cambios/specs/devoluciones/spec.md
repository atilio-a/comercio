# Spec Delta

## Purpose

Permite revertir total o parcialmente una venta confirmada en cualquier momento mediante devoluciones y cambios, con reingreso de stock, nota de crédito interna y la devolución del dinero como saldo a favor en la cuenta corriente del cliente o como reembolso desde la caja.

## ADDED Requirements

### Requirement: Devolución de ítems de una venta
Un usuario con permiso de devolución SHALL poder registrar la devolución de una o varias unidades de los ítems de una venta confirmada de la sucursal activa, indicando las cantidades por ítem, un motivo obligatorio y la resolución (saldo a favor o reembolso). Una venta SHALL admitir varias devoluciones sucesivas.

#### Scenario: Devolución parcial
- **WHEN** de una venta con 2 pares de botas y 1 media se devuelve 1 par con el motivo "Talle chico" y resolución saldo a favor
- **THEN** se registra la devolución con ese ítem, cantidad 1, el motivo, la resolución, el usuario, el dispositivo y la fecha

#### Scenario: Devolución total
- **WHEN** se devuelven todas las unidades de todos los ítems de una venta
- **THEN** el importe devuelto es igual al total de la venta

#### Scenario: Sin cantidades
- **WHEN** se envía la devolución sin ninguna cantidad mayor que 0
- **THEN** el sistema la rechaza indicando que se elija al menos un producto

### Requirement: Cantidad devolvible
La cantidad devuelta de cada ítem SHALL no superar la cantidad vendida menos la ya devuelta en devoluciones anteriores. Una devolución que lo supere SHALL rechazarse sin grabar nada.

#### Scenario: Más de lo vendido
- **WHEN** de un ítem con 2 unidades vendidas se intenta devolver 3
- **THEN** el sistema rechaza la devolución indicando que se pueden devolver como máximo 2

#### Scenario: Devoluciones sucesivas
- **WHEN** de un ítem con 2 unidades se devolvió 1 y después se intenta devolver 2
- **THEN** el sistema rechaza la segunda devolución indicando que queda 1 por devolver

### Requirement: Solo ventas confirmadas
Una venta anulada SHALL no admitir devoluciones, y una venta con devoluciones SHALL no poder anularse.

#### Scenario: Venta anulada
- **WHEN** se intenta devolver un ítem de una venta anulada
- **THEN** el sistema rechaza la devolución y no graba nada

#### Scenario: Anular una venta con devoluciones
- **WHEN** el Gerente intenta anular una venta que tiene una devolución
- **THEN** el sistema rechaza la anulación indicando que la venta tiene devoluciones

### Requirement: Importe devuelto proporcional a lo cobrado
Cada ítem devuelto SHALL devolver la parte proporcional, por cantidad, de lo efectivamente cobrado por ese ítem (con sus descuentos y su parte del recargo por cuotas), con su neto e IVA. La suma de lo devuelto por un ítem SHALL no superar lo cobrado por él, y al devolver sus últimas unidades SHALL ser exactamente lo cobrado.

#### Scenario: Ítem con descuento y recargo
- **WHEN** se devuelve 1 de 3 unidades de un ítem cobrado $ 1000,00 en total con descuento general y recargo por cuotas incluidos
- **THEN** se devuelven $ 333,33 con su neto e IVA, y neto + IVA = importe

#### Scenario: Centavos cerrados
- **WHEN** después se devuelven las 2 unidades restantes en dos devoluciones
- **THEN** las tres devoluciones suman exactamente $ 1000,00

### Requirement: Reingreso de stock
Cada devolución SHALL reingresar al stock de la sucursal de la venta las cantidades devueltas de cada variante, con la devolución como origen del movimiento en el kardex.

#### Scenario: Reingreso
- **WHEN** se devuelve 1 par de botas negras talle 37
- **THEN** el stock de esa variante aumenta en 1 y el kardex muestra un ingreso cuyo origen es la devolución

### Requirement: Nota de crédito interna
Cada devolución SHALL emitir en la misma operación una nota de crédito interna vinculada a la devolución y a la venta, con numeración correlativa propia en el punto de venta de la venta, la leyenda "Documento no válido como factura", los ítems devueltos, el importe con IVA discriminado, la referencia al comprobante de la venta, el motivo y la resolución. SHALL poder imprimirse en ticket y A4.

#### Scenario: Numeración correlativa
- **WHEN** se registran dos devoluciones en el punto de venta 1
- **THEN** se emiten las notas de crédito 00001-00000001 y 00001-00000002, cada una vinculada a su devolución

#### Scenario: Impresión
- **WHEN** se abre la nota de crédito de una devolución
- **THEN** el documento muestra "Nota de crédito interna", "Documento no válido como factura", la referencia al comprobante de la venta, los ítems devueltos y el total

### Requirement: Saldo a favor en la cuenta corriente
Con la resolución saldo a favor, el importe devuelto SHALL acreditarse como haber en la cuenta corriente del cliente, que SHALL crearse sin crédito habilitado si no existía. Si el cliente tenía deuda, el haber SHALL reducirla y solo el excedente queda como saldo a favor.

#### Scenario: Cliente sin cuenta
- **WHEN** se devuelve con saldo a favor un ítem de $ 12.100,00 de un cliente sin cuenta corriente
- **THEN** el cliente tiene una cuenta sin crédito habilitado con $ 12.100,00 de saldo a favor

#### Scenario: Devolución de una venta a cuenta
- **WHEN** se devuelve con saldo a favor un ítem de $ 12.100,00 de una venta cobrada en cuenta corriente a un cliente que debe $ 20.000,00
- **THEN** la deuda del cliente baja a $ 7.900,00

### Requirement: Cliente obligatorio para el saldo a favor
Una devolución con saldo a favor SHALL tener cliente: el de la venta o, si la venta fue a consumidor final, uno elegido o dado de alta en la devolución.

#### Scenario: Consumidor final sin cliente
- **WHEN** se devuelve con saldo a favor un ítem de una venta a consumidor final sin indicar cliente
- **THEN** el sistema rechaza la devolución pidiendo el cliente y no graba nada

#### Scenario: Consumidor final con cliente
- **WHEN** en la misma devolución se elige un cliente
- **THEN** la devolución queda a nombre de ese cliente y el saldo se acredita en su cuenta

### Requirement: Reembolso en dinero
Con la resolución reembolso, el sistema SHALL exigir el permiso de reembolso y la caja abierta del usuario en la sucursal, y SHALL registrar un egreso de devolución por el importe devuelto con el medio elegido (que debe pasar por caja y estar activo), además de emitir la nota de crédito. Una venta cobrada en todo o en parte con cuenta corriente o saldo a favor SHALL no admitir reembolso.

#### Scenario: Reembolso en efectivo
- **WHEN** el Gerente con la caja abierta devuelve un ítem de $ 12.100,00 con reembolso en efectivo
- **THEN** su caja registra un egreso de devolución de $ 12.100,00 y se emite la nota de crédito

#### Scenario: Caja cerrada
- **WHEN** el Gerente sin caja abierta intenta un reembolso
- **THEN** el sistema lo rechaza indicando que abra la caja y no graba nada

#### Scenario: Sin permiso
- **WHEN** un Vendedor intenta una devolución con reembolso
- **THEN** el sistema la rechaza por falta de permiso

#### Scenario: Venta cobrada con saldo a favor
- **WHEN** se intenta reembolsar en efectivo una venta pagada en parte con saldo a favor
- **THEN** el sistema la rechaza indicando que la devolución se acredita en la cuenta del cliente

### Requirement: Atomicidad de la devolución
La devolución, sus ítems, el reingreso de stock, el movimiento de caja o de cuenta corriente, la nota de crédito y la auditoría SHALL grabarse juntos: si algo falla no SHALL quedar nada grabado ni consumirse un número de nota de crédito.

#### Scenario: Falla al acreditar
- **WHEN** falla la acreditación en la cuenta corriente durante una devolución
- **THEN** no queda la devolución, el stock no cambia y no se emite la nota de crédito

### Requirement: Cambio con el saldo a favor
Un cambio SHALL registrarse como una devolución con saldo a favor seguida de una nueva venta en la que el cliente paga con su saldo a favor y la diferencia con otros medios. Desde el detalle de una devolución con saldo a favor, el sistema SHALL ofrecer abrir la venta rápida con el cliente de la devolución ya elegido.

#### Scenario: Usar el saldo en una nueva venta
- **WHEN** después de devolver con saldo a favor se elige "Usar el saldo en una nueva venta"
- **THEN** se abre la venta rápida con el cliente precargado y el pago con saldo a favor propuesto

#### Scenario: Nueva venta pagada con el saldo
- **WHEN** el cliente con $ 12.100,00 a favor compra por $ 23.150,00 y paga $ 12.100,00 con saldo a favor y el resto en efectivo
- **THEN** la venta se registra y su saldo a favor queda en $ 0,00

### Requirement: Consulta de devoluciones
El sistema SHALL listar las devoluciones de la sucursal activa (más recientes primero, con filtro por fechas) y mostrar el detalle de cada una con sus ítems, importes, resolución y nota de crédito. El detalle de la venta SHALL mostrar sus devoluciones y la cantidad devuelta de cada ítem. Las devoluciones de otra sucursal SHALL responder 404.

#### Scenario: Detalle de la venta
- **WHEN** se abre una venta con 1 de 2 pares devueltos
- **THEN** el ítem muestra "Devueltas: 1" y la sección de devoluciones enlaza a la devolución

#### Scenario: Otra sucursal
- **WHEN** un usuario abre una devolución de otra sucursal
- **THEN** el sistema responde 404

### Requirement: Permisos de devolución
Registrar devoluciones con saldo a favor SHALL requerir el permiso de devolución (Vendedor, Cajero y Gerente) y ver la venta; reembolsar en dinero SHALL requerir además el permiso de reembolso (Gerente). El rol Depósito SHALL no acceder. Cada devolución SHALL quedar en la auditoría con su resolución e importe.

#### Scenario: Depósito
- **WHEN** un usuario con rol Depósito abre la pantalla de devolución de una venta
- **THEN** el sistema responde 403

#### Scenario: Auditoría
- **WHEN** se registra una devolución con reembolso
- **THEN** la auditoría registra la acción de reembolso con el usuario, la venta, los ítems y el importe
