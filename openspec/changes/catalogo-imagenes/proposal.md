# Proposal

## Why

El usuario pidió poder cargar imágenes de los productos antes de seguir con los módulos de la Fase 1. CAT-04 ("Fotos por producto", prioridad M) estaba planificado para la Fase 3, pero se adelanta: con fotos es más fácil identificar el producto en el catálogo, en el stock y, más adelante, en la venta rápida desde el celular, donde el vendedor elige color y talle mirando el producto.

## What Changes

- **Galería por producto:** hasta 10 imágenes por producto, con una imagen principal.
  - Cada imagen puede asociarse, de forma opcional, a uno de los colores del producto (por ejemplo, la bota en negro y en suela).
  - Las imágenes se pueden agregar, marcar como principal, cambiar de color y eliminar.
- **Carga desde el celular:**
  - El selector de archivos permite usar la cámara o la galería.
  - Antes de subir, el navegador achica cada foto (lado mayor 1600 px) y genera una miniatura (400 px), porque el servidor no tiene GD ni Imagick.
  - El servidor igual valida tipo, tamaño y dimensiones.
- **Almacenamiento privado:** los archivos se guardan en el disco `local` (`storage/app/private`) y se sirven con una ruta que exige sesión y `catalogo.ver`. No hace falta `storage:link`.
- **Visualización:**
  - galería en la ficha del producto;
  - miniatura principal en el listado de productos y en el stock;
  - un método para obtener la imagen de un color, que usará la venta rápida.
- **Permisos y auditoría:** ver requiere `catalogo.ver` y modificar requiere `catalogo.gestionar`. Cada alta, cambio o baja de imagen se audita.
- **Documentación:** CAT-04 pasa de la Fase 3 a la Fase 1 en `documentacion/06-fases.md`, y `producto_fotos` se amplía en `05-modelo-de-datos.md`.

## Capabilities

### New Capabilities
- `catalogo-imagenes`: imágenes de producto (galería, principal, color opcional), carga optimizada desde el celular, almacenamiento privado, visualización y permisos.

### Modified Capabilities
<!-- No hay specs archivadas en openspec/specs/. El catálogo (fase-1-catalogo) todavía no está archivado; este cambio agrega una capacidad nueva sin modificar sus requisitos. -->

## Impact

- **Código nuevo:**
  - migración y modelo `ProductoFoto` (tabla `producto_fotos`, como el doc 05) con su factory;
  - servicio `GaleriaProducto`, `FotoProductoController`, Form Requests y vistas parciales;
  - módulo JS `resources/js/catalogo/achicar-imagenes.js`.
- **Archivos existentes que cambian:**
  - `app/Models/Producto.php`: relaciones y métodos de imagen;
  - vistas del catálogo (ficha, listado y alta del producto) y el listado de stock;
  - `routes/web.php` y `resources/js/app.js`;
  - documentación 05 y 06.
- **Base de datos:** una tabla nueva. Correr la migración en `comercio` requiere autorización.
- **Dependencias:** ninguna. No se habilita GD: el achicado lo hace el navegador.
- **Relación con otros cambios:**
  - Depende de `fase-1-catalogo`, que no está commiteado ni archivado.
  - Toca la vista de stock de `fase-1-stock`, que tampoco está commiteado.
- **Fuera de alcance:**
  - recorte o edición de imágenes;
  - reordenar la galería arrastrando (el orden es el de carga, más la principal);
  - imágenes por variante individual (talle);
  - CDN o almacenamiento externo;
  - generación de miniaturas en el servidor.
