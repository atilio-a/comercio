# 02 — Requerimientos

Prioridad: **A** = alta (MVP) · **M** = media · **B** = baja (fase posterior)

## Requerimientos funcionales

### Catálogo (CAT)
| ID | Prio | Descripción |
|----|------|-------------|
| CAT-01 | A | Productos por categoría (botas, sandalias, zapatos, zapatillas), marca y temporada. |
| CAT-02 | A | Variantes talle + color, cada una con SKU y código de barras/QR propio. |
| CAT-03 | A | Costo, precio final con IVA y alícuota; neto e IVA discriminados se calculan desde el precio final. |
| CAT-04 | M | Fotos por producto. |
| CAT-05 | M | Listas de precios con precio final por variante; la variante que no figura en la lista toma el precio base. |

### Compras (COM)
| ID | Prio | Descripción |
|----|------|-------------|
| COM-01 | A | ABM de proveedores y órdenes de compra. |
| COM-02 | A | Recepción total o parcial con alta automática de stock. |
| COM-03 | M | Cuenta corriente de proveedores, pagos e imputación. |
| COM-04 | B | Cheques propios y de terceros. |

### Ventas (VEN)
| ID | Prio | Descripción |
|----|------|-------------|
| VEN-01 | A | Venta rápida desde el celular (búsqueda o escaneo, talle y color). |
| VEN-02 | A | Pagos combinados, descuentos con autorización (se registra quién autorizó, por venta y por item) y promociones (se registra qué promoción se aplicó a cada item). |
| VEN-03 | A | Cambios y devoluciones con nota de crédito o saldo a favor. El saldo a favor se acredita en la cuenta corriente del cliente, que se crea sin crédito habilitado si no existía. |
| VEN-04 | A | Comprobante interno (PDF/ticket) rotulado "Documento no válido como factura". |
| VEN-05 | M | Anulación por reversión, sin borrar el registro. |
| VEN-06 | M | Reservas y señas; la seña ingresa por caja. |
| VEN-07 | A | Puntos de venta configurables con numeración correlativa única por tipo de comprobante y punto de venta. |
| VEN-08 | A | Recargo por cuotas de tarjeta incluido en el total gravado, con su IVA. |
| VEN-09 | A | Venta con stock negativo permitida cuando el parámetro está habilitado, con registro en auditoría. |

### Stock (STK)
| ID | Prio | Descripción |
|----|------|-------------|
| STK-01 | A | Stock por variante y sucursal. |
| STK-02 | A | Movimientos (ingreso, egreso, ajuste, transferencia) con kardex. |
| STK-03 | A | Inventario físico con conteo desde el celular y diferencias. |
| STK-04 | M | Alertas de stock mínimo y sugerencia de reposición. |

### Clientes y vendedores (CLI / VND)
| ID | Prio | Descripción |
|----|------|-------------|
| CLI-01 | A | Ficha con contacto, talle habitual e historial. |
| CLI-02 | A | Cuenta corriente con límite de crédito y bloqueo por mora. |
| CLI-03 | M | Cuotas propias con cronograma, interés y punitorios. |
| CLI-04 | M | Estado de cuenta por WhatsApp o PDF y recordatorios. |
| VND-01 | A | Ventas por vendedor. |
| VND-02 | M | Metas y comisiones configurables. |

### Caja y pagos (CAJ)
| ID | Prio | Descripción |
|----|------|-------------|
| CAJ-01 | A | Catálogo configurable de medios de pago (efectivo, tarjeta, transferencia, QR, cuenta corriente). |
| CAJ-02 | A | Apertura, arqueo y cierre por usuario y turno. |
| CAJ-03 | A | Egresos e ingresos varios con motivo. |
| CAJ-04 | M | Conciliación de acreditaciones de tarjeta y QR. |
| CAJ-05 | A | Movimientos de dinero inmutables, corregidos con contraasiento. |

### Usuarios y auditoría (USR)
| ID | Prio | Descripción |
|----|------|-------------|
| USR-01 | A | Roles Administrador, Gerente, Vendedor, Cajero y Depósito. |
| USR-02 | A | Permisos por módulo y acción, asignados por sucursal (un usuario puede tener un rol distinto en cada una). |
| USR-03 | A | Auditoría de anulaciones, ajustes, descuentos y quitas. |

### Reportes (REP)
| ID | Prio | Descripción |
|----|------|-------------|
| REP-01 | A | Ventas por vendedor, período, producto, categoría y medio de pago. |
| REP-02 | A | Kardex, stock valorizado y stock bajo mínimo. |
| REP-03 | A | Cierre de caja, cuentas corrientes y morosidad por antigüedad. |
| REP-04 | M | Rotación, curva de talles, márgenes y diferencias de inventario. |
| REP-05 | M | Exportación a PDF, Excel y CSV, y tablero de inicio por rol. |

## Requerimientos no funcionales
| ID | Prio | Descripción |
|----|------|-------------|
| RNF-01 | A | Laravel 12, LAMP (MySQL/MariaDB), Tailwind CSS. |
| RNF-02 | A | Mobile-first: interfaz táctil, formularios cortos y cámara para escanear. En desarrollo se usa la cámara del navegador; en producción, HTTPS con certificado. |
| RNF-03 | A | Importes en `DECIMAL`, con transacciones en ventas, cobros y stock. |
| RNF-09 | A | Toda operación guarda fecha de negocio (`fecha`) y momento de registro (`created_at`); las de dinero, además, usuario y dispositivo. |
| RNF-04 | A | Seguridad: hash de contraseñas, CSRF/XSS, HTTPS y backups automáticos. |
| RNF-05 | A | Venta en menos de 2 s, con índices en las consultas de reportes. |
| RNF-06 | A | Modelo multi-sucursal desde el inicio; sucursales múltiples, facturación y stock negativo configurables. |
| RNF-07 | M | Facturación ARCA desacoplada mediante la interfaz `EmisorComprobante`. |
| RNF-08 | M | Integración posterior con API de Mercado Pago. |

## Roles base
| Rol | Alcance |
|-----|---------|
| Administrador | Configuración total: medios de pago, planes de cuotas, intereses, límites, usuarios. |
| Gerente | Ve todo; autoriza descuentos, quitas, anulaciones y diferencias de caja. |
| Vendedor | Vende; solo ve sus propias ventas y comisiones. |
| Cajero | Cobra y cierra su caja. |
| Depósito | Recepciones, movimientos e inventario de stock. |

## Fuera del alcance inicial
- Facturación electrónica ARCA.
- Tienda online.
- Integración de pagos por API.
- Transferencias entre sucursales (modeladas pero desactivadas).
