# Proposal

## Why

Hoy el stock solo se carga con ajustes por conteo: no hay forma de registrar qué se le pidió a cada proveedor ni de dar ingreso a la mercadería que llega con trazabilidad hasta el remito. La Fase 1 incluye proveedores y recepción de mercadería para cargar stock (COM-01, COM-02), y el resto del sistema (costos, reportes de compras) depende de tener esos datos.

Además, el menú de escritorio ya tiene diez entradas para el Administrador y se desborda entre 640 y 1024 px; sumar Compras lo empeora, así que este cambio reorganiza la navegación en grupos.

## What Changes

- **Proveedores (COM-01):** ABM con razón social, CUIT (formato y dígito verificador argentino, único si se informa), contacto, teléfono, email, condiciones de pago y estado activo. No se borran: se desactivan. Altas y cambios auditados.
- **Órdenes de compra (COM-01):**
  - por sucursal, con número correlativo por sucursal, proveedor, fecha, observaciones, usuario e ítems (variante, cantidad pedida, cantidad recibida y costo);
  - estados `borrador`, `emitida`, `parcial`, `recibida` y `anulada`;
  - se crean y editan en borrador, se emiten y se anulan solo si no tienen mercadería recibida;
  - pantalla con buscador de variantes por nombre, SKU o código para agregar ítems.
- **Recepciones (COM-02):**
  - recepción total o parcial de una orden emitida, o recepción sin orden (libre) de un proveedor;
  - guardan sucursal, proveedor, orden opcional, fecha, referencia de remito/factura, usuario y dispositivo;
  - cada ítem genera un movimiento de stock de tipo Ingreso con origen la recepción, todo en una sola transacción y en orden ascendente de variante;
  - actualizan la cantidad recibida y el estado de la orden (`parcial` o `recibida`);
  - una recepción no puede superar lo pendiente de la orden: el excedente se registra como recepción sin orden;
  - opción por ítem para actualizar el costo de la variante con el costo recibido, auditada;
  - son inmutables; se corrigen anulándolas, lo que registra egresos de stock que revierten los ingresos (`reversa_de_id`) y descuenta la cantidad recibida de la orden. Si el stock negativo está deshabilitado y no alcanza, la anulación se rechaza con un mensaje claro;
  - pantalla mobile-first con escaneo por teclado (un campo que acepta el código de barras y suma una unidad) y detalle de la recepción.
- **Permisos:** `compras.ver` (Gerente, Depósito), `compras.gestionar` (Gerente: proveedores, órdenes y anulación de recepciones) y `compras.recibir` (Gerente, Depósito).
- **Navegación agrupada:** el menú de escritorio pasa a grupos desplegables accesibles ("Ventas y caja", "Inventario" con Catálogo, Stock y Compras, "Administración" con Usuarios, Parámetros, Auditoría y Tablas) y el menú móvil a secciones con título. Cada entrada conserva su permiso y un grupo sin entradas visibles no se muestra.

## Capabilities

### New Capabilities
- `proveedores`: catálogo de proveedores con validación de CUIT.
- `compras`: órdenes de compra, recepciones de mercadería con ingreso de stock, anulaciones y permisos de compras.
- `navegacion`: organización del menú principal en grupos según permisos, en escritorio y móvil.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. -->

## Impact

- **Código nuevo:**
  - migraciones de `proveedores`, `ordenes_compra`, `orden_compra_items`, `recepciones` y `recepcion_items`;
  - modelos y factories, enum `EstadoOrdenCompra`, auxiliar `Cuit` y regla `CuitValido`;
  - servicios `OrdenesCompra` y `RecepcionesCompra`;
  - Form Requests, controladores, vistas y el componente Alpine `itemsCompra`.
- **Archivos existentes que cambian:**
  - `app/Enums/Permiso.php` y `app/Enums/Rol.php`: permisos y matriz;
  - `routes/web.php`, `resources/js/app.js`;
  - `resources/views/layouts/navigation.blade.php` (reorganización) y `resources/views/components/dropdown.blade.php` (cierre con Escape);
  - `tests/Feature/Seeders/DatosInicialesTest.php` y `tests/Feature/NavegacionTest.php`.
- **Base de datos:** cinco tablas nuevas en `comercio` (migración autorizada).
- **Dependencias:** ninguna nueva.
- **Relación con otros cambios:** usa `MovimientosStock` (fase-1-stock) como única puerta del stock; no toca caja.
- **Fuera de alcance:** facturas de compra, pagos a proveedores y cuenta corriente de proveedores (COM-03, Fase 3), cheques (COM-04), cierre manual de una orden con pendientes, y reportes de compras.
