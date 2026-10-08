# 06 — Plan por fases

El MVP original concentra casi todos los requerimientos con prioridad A. Se parte en fases para que cada una entregue algo usable en el local. Los IDs remiten al documento 02.

Todas las fases respetan desde el inicio las decisiones de base: modelo multi-sucursal, permisos por sucursal (modo teams), venta separada de comprobante, `fecha` + `created_at`, dispositivo en operaciones de dinero y contraasientos en lugar de ediciones.

## Fase 1 — Vender en el mostrador
Objetivo: reemplazar la operación diaria de venta y caja.

| Módulo | Requerimientos |
|--------|----------------|
| Base | Sucursal única, usuarios, roles y permisos por sucursal, parámetros, auditoría (USR-01, USR-02, USR-03, RNF-01 a RNF-06, RNF-09) |
| Catálogo | Productos, variantes, códigos, precio final con IVA (CAT-01, CAT-02, CAT-03) |
| Stock | Stock por variante, kardex, ajustes, venta con stock negativo configurable (STK-01, STK-02, VEN-09) |
| Compras | Proveedores y recepción de mercadería para cargar stock (COM-01, COM-02) |
| Ventas | Venta rápida con escaneo, pagos combinados, descuentos autorizados, comprobante interno, puntos de venta, recargo de tarjeta, anulación por reversión (VEN-01, VEN-02 sin promociones, VEN-04, VEN-05, VEN-07, VEN-08) |
| Cambios | Cambios y devoluciones con saldo a favor en una cuenta corriente sin crédito (VEN-03) |
| Clientes y vendedores | Ficha básica de cliente y ventas por vendedor (CLI-01, VND-01) |
| Caja | Medios de pago, apertura, arqueo, cierre, egresos e ingresos, contraasientos (CAJ-01, CAJ-02, CAJ-03, CAJ-05) |
| Reportes | Cierre de caja, ventas por vendedor y período, stock actual y kardex (parte de REP-01, REP-02, REP-03) |

Los cambios y devoluciones entran en esta fase porque los cambios de talle son frecuentes en calzado. VEN-05 entra acá aunque su prioridad sigue en revisión (ver temas abiertos).

## Fase 2 — Crédito a clientes e inventario
| Módulo | Requerimientos |
|--------|----------------|
| Cuentas corrientes | Venta a cuenta, límite de crédito, cobranzas e imputación (CLI-02) |
| Stock | Inventario físico desde el celular (STK-03) |
| Ventas | Promociones (resto de VEN-02) |
| Catálogo | Listas de precios (CAT-05) |
| Reportes | Resto de REP-01, REP-02 y REP-03, morosidad por antigüedad, tablero de inicio por rol |

## Fase 3 — Gestión comercial
| Módulo | Requerimientos |
|--------|----------------|
| Cuentas corrientes | Cuotas propias, interés, punitorios, estado de cuenta por WhatsApp o PDF (CLI-03, CLI-04) |
| Vendedores | Metas y comisiones (VND-02) |
| Ventas | Reservas y señas (VEN-06) |
| Stock | Alertas de stock mínimo y reposición (STK-04) |
| Compras | Cuenta corriente de proveedores (COM-03) |
| Caja | Conciliación de tarjetas y QR (CAJ-04) |
| Catálogo | Fotos por producto (CAT-04) |
| Reportes | Rotación, curva de talles, márgenes, exportación a PDF, Excel y CSV (REP-04, REP-05) |

## Fase 4 — Integraciones y crecimiento
- Cheques propios y de terceros (COM-04).
- Facturación electrónica ARCA (RNF-07).
- Integración con la API de Mercado Pago (RNF-08).
- Segunda sucursal y transferencias entre sucursales.

## Temas abiertos
| Tema | Estado |
|------|--------|
| Proceso automático de bloqueo por mora y de recordatorios de vencimiento | Pendiente; no se resuelve por ahora. |
| Prioridad de VEN-05 (anulación por reversión): hoy M, se propuso subirla a A | A revisar. |
