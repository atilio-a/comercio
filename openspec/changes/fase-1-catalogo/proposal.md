# Proposal

## Why

La Fase 1 ("Vender en el mostrador") necesita un catálogo antes de poder manejar stock, compras o ventas: en `documentacion/05-modelo-de-datos.md`, la variante es la unidad de stock y de venta. `fase-1-base` ya dejó usuarios, permisos por sucursal, parámetros (incluido "Usar talles en productos") y auditoría. Este cambio construye el catálogo (CAT-01, CAT-02, CAT-03) sobre esa base, para que los cambios siguientes de stock, compras y ventas puedan referenciar variantes.

## What Changes

- **Tablas maestras** con alta, edición y activación/desactivación: categorías, marcas, temporadas, colores y alícuotas de IVA. Nada se borra: se desactiva. Datos iniciales: alícuotas 21 %, 10,5 %, 27 % y 0 %, y el color "Único". Las categorías de calzado (botas, sandalias, zapatos, zapatillas) quedan como datos de ejemplo opcionales, no como lista fija.
- **Productos (CAT-01):** nombre, categoría obligatoria, marca y temporada opcionales, alícuota de IVA y estado activo.
- **Variantes (CAT-02):**
  - Se generan con una **grilla color × talle** al crear el producto, con costo y precio comunes que después se editan una por una.
  - Se pueden agregar variantes después.
  - Con "Usar talles en productos" deshabilitado, la grilla es solo de colores. Se aplica el comportamiento definido en `talles-configurables` (capacidad `catalogo-talles`).
  - Color y talle identifican a la variante y no se modifican una vez creada.
- **Códigos:**
  - Cada variante tiene SKU y código de barras únicos.
  - Si se dejan vacíos, el sistema genera un SKU legible y un EAN-13 interno (prefijo 2).
  - Si el producto trae código de fábrica, se escribe o se escanea con un lector que funcione como teclado.
- **Precios (CAT-03):**
  - Costo y precio final con IVA por variante.
  - Neto e IVA se calculan desde el precio final según la alícuota del producto y se muestran en pantalla.
  - Importes en `decimal(14,2)`.
- **Búsqueda** de productos por nombre, SKU o código de barras, con filtros por categoría, marca y estado.
- **Permisos:** `catalogo.ver` y `catalogo.gestionar`. Todo alta o modificación de productos y variantes se audita, incluidos los cambios de costo y de precio.

## Capabilities

### New Capabilities
- `catalogo-maestros`: tablas maestras del catálogo (categorías, marcas, temporadas, colores, alícuotas de IVA) y su mantenimiento.
- `catalogo-productos`: productos y variantes, generación por grilla, códigos SKU y de barras, precios con IVA, búsqueda y permisos del catálogo.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. El comportamiento de talles ya está especificado en el cambio talles-configurables (capacidad catalogo-talles) y este cambio lo implementa sin modificarlo. -->

## Impact

- **Código nuevo:**
  - migraciones de `categorias`, `marcas`, `temporadas`, `colores`, `alicuotas_iva`, `productos` y `variantes`;
  - modelos, factories, Form Requests, policies, controladores y vistas Blade;
  - servicios de cálculo de IVA y de generación de códigos;
  - seeders de alícuotas y del color "Único", y un seeder opcional de categorías de calzado.
- **Archivos existentes que cambian:**
  - `app/Enums/Permiso.php` y `app/Enums/Rol.php`: permisos nuevos y matriz de roles;
  - `routes/web.php`;
  - `resources/views/layouts/navigation.blade.php`: entrada "Catálogo";
  - `database/seeders/DatabaseSeeder.php`.
- **Base de datos:** nuevas migraciones sobre `comercio`, que requieren autorización al implementar.
- **Dependencias:** ninguna nueva.
- **Relación con otros cambios:**
  - Depende de `fase-1-base` (le faltan la tarea 1.2 y el recorrido manual 8.2).
  - Implementa las partes de catálogo de `talles-configurables`. La venta rápida, la ficha de cliente y los reportes de esa spec quedan para sus cambios.
- **Fuera de alcance:**
  - stock y movimientos;
  - listas de precios (CAT-05, Fase 2) y fotos de producto (CAT-04, Fase 3);
  - escaneo con la cámara del celular (lo usa la venta rápida);
  - impresión de etiquetas;
  - importación masiva desde planillas.
