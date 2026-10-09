# 01 — Relevamiento y alcance

## Objetivo
Gestionar el comercio: compras, ventas, stock, control de inventario, clientes y vendedores, con acceso desde el celular y gestión de usuarios y roles.

## Alcance funcional
- Catálogo de productos con variantes por color y, opcionalmente, talle (configurable).
- Compras a proveedores y recepción de mercadería.
- Ventas rápidas desde el celular, cambios y devoluciones.
- Stock por variante, movimientos e inventario físico.
- Clientes con cuenta corriente propia y cuotas.
- Vendedores con metas y comisiones.
- Caja, medios de pago y conciliación.
- Usuarios, roles, permisos y auditoría.
- Reportes y tablero.

## Requerimientos no funcionales de base
- Laravel 12, stack LAMP, Tailwind CSS.
- Mobile-first: interfaz táctil, formularios cortos, cámara para escanear códigos. En desarrollo alcanza con la cámara del navegador; en producción se usará certificado HTTPS (el navegador solo habilita la cámara en contexto seguro).
- Seguridad: autenticación robusta, hash de contraseñas, CSRF/XSS, backups automáticos.
- Rendimiento: respuesta menor a 2 s en operaciones de venta.
- Integridad: transacciones en ventas y movimientos de stock. Se puede vender con stock negativo cuando el parámetro lo habilita; cada venta que deja stock negativo queda auditada.

## Decisión de diseño 1: una sucursal hoy, preparado para crecer
Operación con una sola sucursal, pero **modelo de datos multi-sucursal desde el inicio**.

- Tabla `sucursales` con una única fila inicial ("Local principal").
- Toda tabla operativa lleva `sucursal_id`: stock, caja, ventas, compras, movimientos, cobranzas, cuentas corrientes, devoluciones, reservas y comprobantes.
- Los permisos se asignan por sucursal (spatie/laravel-permission en modo teams).
- La interfaz **oculta** los selectores de sucursal mientras exista una sola.
- Usuarios asignables a una o varias sucursales.
- Transferencias entre sucursales: modeladas, pero deshabilitadas por configuración.

## Decisión de diseño 2: facturación desacoplada y habilitable después
Hoy solo comprobantes internos (ticket/PDF no fiscal). Para habilitar ARCA sin rehacer nada:

- Separar **venta** (operación comercial) de **comprobante** (documento emitido). Una venta tiene 0 o 1 comprobante fiscal.
- Campos previstos y opcionales: condición frente al IVA del cliente, CUIT/DNI, tipo de comprobante, punto de venta, CAE y vencimiento.
- Se fija el **precio final con IVA** y se guardan además el neto y el IVA discriminados (neto + alícuota), calculados a partir del final.
- El recargo por cuotas de tarjeta **integra el total gravado** y lleva IVA.
- Interfaz `EmisorComprobante` con implementación "interno" hoy y "ARCA" mañana (WSFE o proveedor intermediario).
- Interruptor de configuración: *Facturación electrónica: sí/no*.
- Puntos de venta configurables, con numeración correlativa propia por tipo de comprobante y punto de venta.
- Los comprobantes internos se rotulan **"Documento no válido como factura"**.

## Requerimientos no funcionales derivados
- **Extensibilidad:** configuración por parámetros (sucursales múltiples, facturación, stock negativo, talles en productos) sin tocar código.
- **Trazabilidad:** toda venta anulada conserva su registro; no se borra, se revierte. Cada operación guarda su fecha de negocio y su momento de registro, y las de dinero, además, el usuario y el dispositivo.
- **Auditoría fiscal futura:** los datos del comprobante son inmutables una vez emitidos.

## Medios de cobro relevados
El comercio acepta todos los medios de pago (efectivo, tarjeta, transferencia, QR/billeteras) y maneja **cuentas corrientes propias**. Ver documento 04.
