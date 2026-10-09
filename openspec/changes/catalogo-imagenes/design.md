# Design

## Context

- **Entorno:**
  - PHP 8.2.12 sin `gd` ni `imagick`, con `fileinfo` y `exif`, así que el servidor no puede redimensionar.
  - `upload_max_filesize` y `post_max_size` = 110M.
  - El disco `local` apunta a `storage/app/private`. No existe `public/storage`.
- **Código actual:**
  - `Producto` y `Variante` con `ProductoPolicy` (`view` = `catalogo.ver`, `update` = `catalogo.gestionar`);
  - `RegistradorAuditoria` dentro de `DB::transaction`;
  - vistas Breeze + Tailwind 4 + Alpine, con módulos JS registrados en `resources/js/app.js` (por ejemplo, `grillaVariantes`).
- **Tests:** `UploadedFile::fake()->image()` necesita GD, que no está instalado. Los tests construyen imágenes reales mínimas en memoria (PNG/JPEG en base64) con `UploadedFile::fake()->createWithContent()`.
- Ver proposal.md y `specs/catalogo-imagenes/spec.md`.

## Goals / Non-Goals

**Goals:**
- Subir fotos desde el celular sin esperas largas ni dependencias nuevas.
- Imágenes privadas, servidas con control de acceso.
- API simple para que la venta rápida pida la imagen de un color.

**Non-Goals:**
- Procesamiento de imágenes en el servidor, edición, reordenamiento arrastrando y CDN.

## Decisions

### D1. Tabla `producto_fotos` (nombre del doc 05)
- Columnas:
  - `id`, `producto_id` FK, `color_id` FK nullable;
  - `ruta varchar(255)`, `ruta_miniatura varchar(255)`;
  - `es_principal boolean` default false, `orden unsigned smallint`;
  - `ancho`, `alto` (unsigned smallint), `bytes` (unsigned int);
  - `timestamps`.
- Índices `(producto_id, orden)` y `(producto_id, color_id)`.
- La FK a `productos` es `cascadeOnDelete` (los productos no se borran, pero si algún día se borraran, las fotos no tendrían sentido). La FK a `colores` es `nullOnDelete`.
- *Alternativa descartada:* columna `principal_foto_id` en `productos`. Obliga a mantener dos tablas en sincronía. El flag con su regla en el servicio es suficiente.

### D2. Rutas en disco y entrega
- Archivos en `productos/{producto_id}/{uuid}.{ext}` y `productos/{producto_id}/{uuid}-min.{ext}` dentro del disco `local`. Los nombres no dependen del archivo subido, para evitar rutas inseguras.
- `GET /catalogo/fotos/{foto}/{tamano}`, con `tamano` = `original` o `miniatura`:
  - autoriza con `view` sobre el producto;
  - responde con `Storage::disk('local')->response()`, `Cache-Control: private, max-age=86400` y `ETag` basado en `updated_at`.
- *Alternativa descartada:* el disco `public` con `storage:link`. Deja las fotos accesibles sin sesión y el symlink en Windows/XAMPP puede requerir permisos de administrador.

### D3. Achicado en el navegador
Módulo `achicar-imagenes.js` registrado como `Alpine.data('subirFotos')`:
1. Al elegir archivos, cada uno se decodifica con `createImageBitmap(file, { imageOrientation: 'from-image' })`, que respeta la orientación EXIF.
2. Se dibuja en un `<canvas>` con lado mayor de 1600 px, y otro de 400 px para la miniatura.
3. Se exporta con `canvas.toBlob('image/webp', 0.82)`; si el navegador no genera WebP, se usa `image/jpeg`.
4. Se reemplazan los archivos del `<input name="fotos[]">` y se completa `<input name="miniaturas[]">` con `DataTransfer`, manteniendo la correspondencia por índice.
5. Si algo falla, quedan los archivos originales y no se envía miniatura.

La interfaz muestra la vista previa y el estado ("Procesando…"). El botón queda deshabilitado hasta terminar. El input usa `accept="image/jpeg,image/png,image/webp" multiple`, sin `capture`, para que el celular ofrezca cámara o galería.

### D4. Validación en el servidor (`SubirFotosRequest`)
- `fotos` es obligatorio, es un arreglo de 1 a 10 archivos y no puede superar el cupo restante del producto (validado en `after()`).
- `fotos.*`: `file`, `mimes:jpeg,png,webp` (que verifica el contenido con fileinfo), `max:5120` y `dimensions:max_width=4000,max_height=4000`. `dimensions` usa `getimagesize`, que funciona sin GD.
- `miniaturas.*`: opcional, mismas reglas con `max:512`.
- `color_id`: opcional para todo el lote; debe ser un color usado por alguna variante del producto.

### D5. Servicio `GaleriaProducto`
- Métodos: `agregar(Producto, array $fotos, array $miniaturas, ?Color)`, `marcarPrincipal(ProductoFoto)`, `cambiarColor(ProductoFoto, ?Color)` y `eliminar(ProductoFoto)`.
- Todo ocurre en transacción, con `lockForUpdate` sobre el producto para calcular `orden` y el cupo sin carreras.
- **Archivos:** al agregar, se escriben antes de confirmar. Si la transacción falla, `DB::afterRollBack` borra los archivos escritos. Al eliminar, el borrado de archivos se agenda con `DB::afterCommit`.
- **Regla de principal:** después de agregar o eliminar, si no queda ninguna principal, la de menor `orden` pasa a serlo. `marcarPrincipal` desmarca las demás en la misma transacción.
- `ancho` y `alto` se leen con `getimagesize()`; `bytes` es el tamaño del archivo guardado.
- **Auditoría:** `producto.foto_agregada`, `producto.foto_principal`, `producto.foto_color` y `producto.foto_eliminada`, con `producto_id`, `foto_id` y `color`.

### D6. Lectura para otras pantallas
- `Producto::fotos()` ordenadas por `orden`.
- `Producto::fotoPrincipal()` (`hasOne` con `es_principal`), para cargarla con `with()` en listados sin N+1.
- `Producto::fotoPara(?Color)`: la primera del color o, si no hay, la principal.
- Componente Blade `x-foto-producto` (`:foto`, `tamano`, `alt`) que renderiza `<img loading="lazy" decoding="async">` o un marcador neutro accesible.

### D7. Pantallas
- Ficha del producto: sección "Imágenes" con la galería en grilla responsive y, para `catalogo.gestionar`:
  - formulario de carga con selector de color opcional;
  - acciones por imagen: "Hacer principal", cambiar color y eliminar (con confirmación).
- Listados de productos y de stock: miniatura principal a la izquierda de cada tarjeta.
- No se agrega carga de fotos al alta del producto, para no hacer más pesado ese formulario. Después de crear el producto se llega a la ficha, donde se cargan.

## Risks / Trade-offs

- [Navegadores viejos sin `createImageBitmap` o sin `DataTransfer`] → Se suben los originales hasta 5 MB y se valida en el servidor. La miniatura cae a la imagen completa.
- [Fotos HEIC de iPhone] → Safari las convierte al elegirlas cuando el `accept` no incluye HEIC. Si llegara una HEIC, el servidor la rechaza con el mensaje de formatos admitidos.
- [El disco crece con fotos] → Unos 300 KB por foto y 10 por producto como máximo. Los backups (RNF-04) deberán incluir `storage/app/private`.
- [Archivos huérfanos si el proceso muere entre escribir el archivo y confirmar la transacción] → Riesgo bajo. Se puede agregar más adelante un comando de limpieza; queda fuera de alcance.

## Migration Plan

1. Migración nueva, que requiere autorización para correrla en `comercio`.
2. Sin cambios de configuración: el disco `local` ya existe.
3. Rollback: `migrate:rollback` del lote y borrar `storage/app/private/productos`.

## Open Questions

- Calidad de exportación (0,82) y tamaño (1600 px): se pueden ajustar después de probar en el celular sin cambiar specs ni tareas.
