# Spec Delta

## Purpose

Registra las ventas del mostrador con sus ítems, descuentos, recargos y pagos combinados, moviendo stock, caja y cuenta corriente en una sola operación consistente, y permite consultarlas por vendedor y anularlas por reversión sin borrar nada.

## ADDED Requirements

### Requirement: Registro atómico de la venta
Una venta SHALL registrarse en una sola operación que graba la venta, sus ítems y pagos, el egreso de stock de cada ítem, los movimientos de caja y de cuenta corriente y el comprobante. Si cualquier parte falla, SHALL no quedar grabada ninguna. Cada venta SHALL guardar sucursal, fecha de negocio, momento de registro, usuario y dispositivo.

#### Scenario: Venta en efectivo
- **WHEN** un Vendedor con la caja abierta vende 2 "Bota Ana Negro / 37" de $ 50.000 en efectivo
- **THEN** la venta queda confirmada por $ 100.000, el stock baja 2 con un egreso cuyo origen es la venta, la caja registra un ingreso de venta de $ 100.000 y se emite el comprobante

#### Scenario: Falla en la caja revierte el stock
- **WHEN** se intenta una venta en efectivo y la caja del usuario está cerrada
- **THEN** el sistema la rechaza y no quedan venta, ítems, pagos, movimientos de stock ni comprobante

### Requirement: Ventas inmutables
Las ventas, sus ítems y sus pagos SHALL no borrarse ni modificarse. La única modificación admitida SHALL ser la anulación de una venta confirmada.

#### Scenario: Intento de modificación
- **WHEN** un proceso intenta cambiar el total de una venta o borrarla
- **THEN** el sistema lo impide

### Requirement: Precio final como dato maestro
El precio de cada ítem SHALL tomarse del precio final con IVA de la variante en el servidor, nunca del navegador. Cada ítem SHALL guardar precio final, neto unitario, alícuota, cantidad, descuentos, recargo, total, neto, IVA y el costo de la variante en ese momento. El neto y el IVA de cada ítem SHALL calcularse a partir de su total final.

#### Scenario: Desglose de un ítem
- **WHEN** se vende 1 unidad de $ 12.100 con alícuota 21 %
- **THEN** el ítem guarda total $ 12.100, neto $ 10.000 e IVA $ 2.100, y el costo de la variante en ese momento

#### Scenario: Cambio posterior de costo
- **WHEN** después de la venta se actualiza el costo de la variante
- **THEN** el ítem conserva el costo del momento de la venta

### Requirement: Importes con aritmética exacta
Los importes SHALL calcularse en centavos enteros. El total de la venta SHALL ser la suma de los totales de sus ítems y SHALL ser igual al neto más el IVA. El total SHALL ser mayor que 0.

#### Scenario: Totales consistentes
- **WHEN** se vende con alícuotas de 21 % y 10,5 %, descuentos y recargo
- **THEN** la suma de netos más la suma de IVA de los ítems es exactamente el total de la venta

### Requirement: Descuentos por ítem y por venta
El vendedor SHALL poder aplicar un descuento en pesos a cada ítem, no mayor que su importe, y un descuento general en pesos, no mayor que el subtotal. El descuento general SHALL distribuirse entre los ítems en proporción a su importe, cerrando los centavos en el último ítem.

#### Scenario: Distribución del descuento general
- **WHEN** una venta tiene ítems de $ 100 y $ 200 y un descuento general de $ 100
- **THEN** el primer ítem recibe $ 33,33 de descuento, el segundo $ 66,67 y el total es $ 200

#### Scenario: Descuento mayor que el ítem
- **WHEN** se aplica a un ítem de $ 5.000 un descuento de $ 6.000
- **THEN** el sistema rechaza la venta con un error en ese ítem

### Requirement: Autorización de descuentos
Todo descuento SHALL requerir autorización de un usuario activo de la sucursal con `ventas.autorizar_descuentos`: el propio usuario si tiene el permiso o, si no, otro usuario identificado con su email y contraseña. La venta y cada ítem con descuento SHALL guardar quién autorizó, y la autorización SHALL quedar auditada. Los intentos fallidos SHALL limitarse.

#### Scenario: Descuento sin autorización
- **WHEN** un Vendedor aplica un descuento sin indicar quién lo autoriza
- **THEN** el sistema rechaza la venta pidiendo la autorización de un usuario con permiso

#### Scenario: Autorización del Gerente
- **WHEN** un Vendedor aplica un descuento y el Gerente ingresa su email y contraseña
- **THEN** la venta se registra con el Gerente como autorizante en la venta y en los ítems con descuento, y queda auditada la autorización

#### Scenario: Contraseña incorrecta o usuario sin permiso
- **WHEN** el autorizante indicado tiene otra contraseña o no tiene el permiso en la sucursal
- **THEN** el sistema rechaza la venta con un error en la autorización

#### Scenario: Gerente que vende
- **WHEN** un Gerente aplica un descuento en su propia venta
- **THEN** la venta se registra con el Gerente como autorizante sin pedir contraseña

### Requirement: Recargo por cuotas en el total gravado
Un pago con tarjeta de crédito SHALL indicar un plan de cuotas activo del medio, si el medio tiene planes activos. Su recargo SHALL ser el porcentaje del plan sobre el importe que cubre, redondeado a centavos. El recargo total SHALL sumarse al total de la venta y distribuirse entre los ítems en proporción a su importe, cerrando centavos en el último, con la alícuota de cada ítem.

#### Scenario: Recargo con alícuotas distintas
- **WHEN** una venta de un ítem de $ 12.100 al 21 % y otro de $ 11.050 al 10,5 % se paga con tarjeta de crédito en 3 cuotas con 10 % de recargo
- **THEN** el recargo es $ 2.315, el total $ 25.465, y cada ítem suma su parte del recargo a su total gravado con su propia alícuota

#### Scenario: Plan de otro medio
- **WHEN** el pago con tarjeta indica un plan de cuotas que pertenece a otro medio o está inactivo
- **THEN** el sistema rechaza la venta con un error en ese pago

### Requirement: Pagos combinados que igualan el total
Una venta SHALL admitir uno o más pagos con medios activos. Cada pago SHALL indicar el importe de la venta que cubre, mayor que 0; la suma de esos importes SHALL ser exactamente el total antes del recargo, y cada pago SHALL guardar además su recargo, referencia, lote y cupón. Un medio que requiere referencia SHALL exigirla.

#### Scenario: Pagos que no suman el total
- **WHEN** una venta de $ 30.000 se intenta pagar con $ 10.000 en efectivo y $ 15.000 con débito
- **THEN** el sistema rechaza la venta indicando que faltan $ 5.000

#### Scenario: Pago combinado
- **WHEN** una venta de $ 30.000 se paga con $ 10.000 en efectivo y $ 20.000 con débito con referencia
- **THEN** la venta registra los dos pagos y la caja dos ingresos de venta, uno por medio

### Requirement: Caja abierta para los medios que pasan por caja
Los pagos con medios distintos de cuenta corriente y saldo a favor SHALL registrarse como ingresos de venta en la sesión de caja abierta del usuario en la sucursal, y la venta SHALL guardar esa sesión. Sin sesión abierta, una venta con esos medios SHALL rechazarse.

#### Scenario: Caja cerrada
- **WHEN** un Cajero sin caja abierta intenta cobrar en efectivo
- **THEN** el sistema rechaza la venta indicando que abra la caja

#### Scenario: Solo saldo a favor
- **WHEN** un usuario sin caja abierta registra una venta pagada por completo con saldo a favor
- **THEN** la venta se registra sin sesión de caja

### Requirement: Pagos con cuenta corriente y saldo a favor
Los pagos con cuenta corriente o saldo a favor SHALL exigir cliente y SHALL registrarse como débito en su cuenta corriente. El saldo a favor SHALL cubrir como máximo el saldo a favor disponible. La cuenta corriente SHALL rechazarse si generaría deuda en una cuenta sin crédito habilitado, bloqueada o sobre su límite.

#### Scenario: Pago con saldo a favor
- **WHEN** un cliente con $ 8.000 de saldo a favor paga $ 5.000 con saldo a favor y el resto en efectivo
- **THEN** su cuenta registra un débito de $ 5.000 con origen la venta y le quedan $ 3.000 a favor

#### Scenario: Saldo a favor insuficiente
- **WHEN** un cliente con $ 3.000 de saldo a favor intenta pagar $ 5.000 con saldo a favor
- **THEN** el sistema rechaza la venta indicando el saldo disponible

#### Scenario: Cuenta corriente sin crédito
- **WHEN** un cliente sin crédito habilitado intenta pagar con cuenta corriente
- **THEN** el sistema rechaza la venta y no genera deuda

#### Scenario: Consumidor final
- **WHEN** una venta sin cliente intenta pagar con saldo a favor
- **THEN** el sistema rechaza el pago pidiendo elegir el cliente

### Requirement: Stock en la venta
Cada ítem SHALL descontar su cantidad del stock de la sucursal. Si el stock no alcanza y el parámetro "Permitir venta con stock negativo" está deshabilitado, la venta SHALL rechazarse; si está habilitado, SHALL registrarse y quedar auditado el stock negativo. Solo SHALL venderse variantes activas de productos activos.

#### Scenario: Stock insuficiente
- **WHEN** hay 1 unidad y se intentan vender 2 con el parámetro deshabilitado
- **THEN** el sistema rechaza la venta indicando el stock disponible y no graba nada

#### Scenario: Stock negativo permitido
- **WHEN** hay 1 unidad, se venden 2 y el parámetro está habilitado
- **THEN** la venta se registra, el stock queda en -1 y se audita el stock negativo

### Requirement: Vendedor y cliente
Cada venta SHALL tener un vendedor disponible en la sucursal (por defecto el usuario, si es vendedor) y SHALL admitir un cliente activo opcional; sin cliente, la venta es a consumidor final.

#### Scenario: Vendedor por defecto
- **WHEN** un usuario marcado como vendedor abre la venta rápida
- **THEN** el vendedor viene elegido con su nombre

#### Scenario: Vendedor de otra sucursal
- **WHEN** se indica un vendedor que no está disponible en la sucursal activa
- **THEN** el sistema rechaza la venta con un error en el vendedor

### Requirement: Venta rápida
La venta rápida SHALL permitir buscar por nombre, SKU o código, y agregar por código exacto con un lector que escribe el código y Enter; elegir color viendo la foto del color (la primera del color o la principal) y talle según el parámetro de talles; elegir cliente con búsqueda o alta rápida; y cargar pagos viendo el recargo. Los totales SHALL calcularlos el servidor.

#### Scenario: Talles deshabilitados
- **WHEN** el parámetro de talles está deshabilitado y se busca un producto en la venta rápida
- **THEN** la búsqueda no informa talles y la pantalla ofrece elegir solo el color

#### Scenario: Talles habilitados
- **WHEN** el parámetro de talles está habilitado y se busca un producto con talles
- **THEN** la búsqueda informa el talle de cada variante para elegir talle y color

#### Scenario: Escaneo
- **WHEN** se escanea el código de barras de una variante activa
- **THEN** la búsqueda exacta devuelve solo esa variante y se agrega al carrito sin pedir talle

#### Scenario: Previsualización de totales
- **WHEN** el carrito cambia
- **THEN** el servidor devuelve los totales, el recargo de cada pago y lo que falta pagar, calculados con las mismas reglas que el registro

### Requirement: Anulación por reversión
Un usuario con `ventas.anular` SHALL poder anular una venta confirmada de la sucursal indicando un motivo. La anulación SHALL marcar la venta como anulada con usuario y momento, reingresar el stock, contraasentar la caja y la cuenta corriente, emitir el comprobante de anulación y quedar auditada. Una venta SHALL anularse una sola vez.

#### Scenario: Anulación completa
- **WHEN** el Gerente anula con motivo una venta pagada en efectivo y con saldo a favor
- **THEN** el stock vuelve a su valor anterior con ingresos que revierten los egresos, la caja tiene el contraasiento del ingreso, la cuenta corriente recupera el saldo a favor y la venta figura anulada con su motivo

#### Scenario: Segunda anulación
- **WHEN** se intenta anular una venta ya anulada
- **THEN** el sistema lo rechaza sin generar movimientos

#### Scenario: Caja de la venta cerrada
- **WHEN** se intenta anular una venta cuya sesión de caja ya se cerró
- **THEN** el sistema la rechaza indicando que corresponde un cambio o devolución, sin generar movimientos

#### Scenario: Sin permiso
- **WHEN** un Vendedor intenta anular una venta
- **THEN** el sistema responde 403

### Requirement: Consulta de ventas
El listado SHALL mostrar las ventas de la sucursal activa, de la más reciente a la más antigua, con filtros por fecha, vendedor y estado, y un detalle con ítems, pagos y comprobantes. Un usuario sin `ventas.ver_todas` SHALL ver solo las ventas en las que es el vendedor o que registró. Las ventas de otra sucursal SHALL responder como inexistentes.

#### Scenario: Vendedor ve solo sus ventas
- **WHEN** un Vendedor abre el listado y el detalle de una venta de otro vendedor
- **THEN** el listado muestra solo sus ventas y el detalle ajeno responde 403

#### Scenario: Cajero ve todas
- **WHEN** un Cajero filtra el listado por vendedor y estado anulada
- **THEN** ve las ventas anuladas de ese vendedor en la sucursal

### Requirement: Historial en la ficha del cliente
La ficha del cliente SHALL mostrar sus últimas ventas en la sucursal activa, con la misma regla de visibilidad del listado.

#### Scenario: Historial del cliente
- **WHEN** un Cajero abre la ficha de un cliente con dos ventas
- **THEN** la ficha lista las dos ventas con fecha, número de comprobante, total y estado

### Requirement: Permisos de ventas
`ventas.vender` SHALL asignarse a Vendedor, Cajero y Gerente; `ventas.ver_todas` a Cajero y Gerente; `ventas.autorizar_descuentos` y `ventas.anular` al Gerente. Depósito SHALL no tener permisos de ventas. La navegación SHALL mostrar las entradas de ventas en el grupo "Ventas y caja" según estos permisos.

#### Scenario: Depósito
- **WHEN** un usuario de Depósito intenta abrir la venta rápida
- **THEN** el sistema responde 403 y el menú no muestra entradas de ventas
