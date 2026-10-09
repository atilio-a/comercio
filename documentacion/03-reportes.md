# 03 — Módulo de reportes

Todos los reportes tienen filtros por **período, sucursal, vendedor, categoría y marca**, y se exportan a **PDF, Excel y CSV**. En el celular se muestran como tarjetas con totales arriba y detalle debajo.

## Ventas
- **Por vendedor:** cantidad de ventas, unidades, monto, ticket promedio, descuentos otorgados, devoluciones y comisión calculada. Incluye ranking y avance contra meta.
- **Por período:** diario, semanal, mensual, con comparativo contra el período anterior.
- **Por producto, talle y color:** qué se vende más y qué talles se agotan primero. El talle (columna y filtro) aparece solo si el parámetro "Usar talles en productos" está habilitado; si no, se agrupa por producto y color.
- **Por categoría y marca:** según las categorías configuradas (por ejemplo, botas, sandalias, zapatos y zapatillas en un comercio de calzado).
- **Por medio de pago.**
- **Por cliente:** historial y mejores clientes.
- **Cambios y devoluciones:** motivo, producto y vendedor.
- **Ventas anuladas y descuentos:** quién, cuándo y por qué (auditoría).

## Stock y movimientos
- **Movimientos de stock (kardex):** por variante, con ingreso, egreso, ajuste y transferencia, mostrando usuario, fecha, motivo y saldo.
- **Stock actual:** por variante, valorizado a costo y a precio de venta.
- **Stock bajo mínimo** y sugerencia de reposición.
- **Rotación y productos sin movimiento:** días de stock y mercadería inmovilizada.
- **Diferencias de inventario físico:** conteo contra sistema.
- **Ajustes de stock:** quién los hizo y por qué.
- **Curva de talles:** distribución de stock contra distribución de ventas. Solo se ofrece si el parámetro "Usar talles en productos" está habilitado; las variantes sin talle se agrupan como "Sin talle".

## Compras y proveedores
- Compras por proveedor y período.
- Órdenes pendientes y recepciones parciales.
- Cuenta corriente y vencimientos de proveedores.
- Evolución del costo por producto.

## Caja y finanzas
- Cierre de caja diario: ventas por medio de pago, egresos y diferencias.
- Cuentas corrientes de clientes y señas pendientes.
- **Margen de ganancia** por producto, categoría y vendedor.
- Cobranzas por medio de pago, morosidad, cuotas por vencer, recargos e intereses cobrados.
- Comisiones de tarjeta (neto real cobrado).

## Tablero principal (inicio)
Ventas del día y del mes, ticket promedio, top 5 productos, alertas de stock mínimo y ranking de vendedores.

- **Vendedor:** solo ve sus propias ventas y comisiones.
- **Gerente y Administrador:** ven todo.

## Requerimientos asociados
- Los reportes respetan los **permisos por rol**.
- Consultas optimizadas (índices y tablas de resumen si crece el volumen) para no frenar la venta.
- Las **comisiones** se configuran por vendedor, por categoría o por escala de meta.
