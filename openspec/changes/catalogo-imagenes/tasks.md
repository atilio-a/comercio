# Tasks

> Requiere autorización del usuario antes de correr la migración sobre `comercio` (4.3). Sin dependencias nuevas.

## 1. Modelo y almacenamiento

- [x] 1.1 Crear la migración de `producto_fotos` (D1), el modelo `ProductoFoto` con su factory y las relaciones `fotos()`, `fotoPrincipal()` y `fotoPara()` en `Producto` (D6); verificar con tests de relaciones y de `fotoPara()` con y sin foto del color
- [x] 1.2 Crear un helper de tests con imágenes reales mínimas (PNG y JPEG en base64) para `UploadedFile::fake()->createWithContent()`, porque no hay GD; verificar que `getimagesize()` las lee con sus dimensiones

## 2. Servicio, validación y entrega

- [x] 2.1 Implementar `GaleriaProducto::agregar()` (transacción con bloqueo del producto, rutas UUID, `orden`, principal automática, dimensiones y bytes, borrado de archivos ante rollback, auditoría, D5); verificar con tests: 3 fotos en orden, primera principal, archivos existentes en `Storage::fake('local')` y rollback sin archivos huérfanos
- [x] 2.2 Implementar `marcarPrincipal()`, `cambiarColor()` y `eliminar()` (archivos borrados después del commit, nueva principal al eliminar); verificar con tests de los escenarios de principal, color y eliminación con sus auditorías
- [x] 2.3 Crear `SubirFotosRequest` y `ActualizarFotoRequest` (D4: tipos por contenido, 5 MB, 4000 px, cupo de 10, color usado por el producto); verificar con tests: PDF renombrado rechazado, archivo grande rechazado, cupo excedido rechazado y color ajeno rechazado
- [x] 2.4 Implementar `FotoProductoController` (subir, marcar principal, cambiar color, eliminar y entregar original o miniatura con caché privada, D2) y sus rutas; verificar con tests: un visitante es redirigido al login, Vendedor ve pero no sube (403), Gerente sube, y la entrega devuelve el contenido y el tipo correctos

## 3. Interfaz

- [ ] 3.1 Implementar el módulo `achicar-imagenes.js` (`Alpine.data('subirFotos')`, D3) y registrarlo en `app.js`; verificar con `npm run build` y prueba manual en celular: una foto grande sube achicada, con miniatura y la orientación correcta
- [x] 3.2 Crear el componente `x-foto-producto` y la sección "Imágenes" de la ficha (galería, carga con color opcional, acciones por imagen, textos alternativos y confirmación al eliminar); verificar con tests de render: galería con principal destacada, formulario solo para `catalogo.gestionar` y alt con producto y color
- [x] 3.3 Mostrar la miniatura principal en los listados de productos y de stock con `with('fotoPrincipal')`; verificar con tests de render (miniatura o marcador) y de que no hay N+1 (cantidad de consultas constante al agregar productos)

## 4. Documentación e integración

- [x] 4.1 Actualizar `documentacion/06-fases.md` (CAT-04 pasa a la Fase 1) y `documentacion/05-modelo-de-datos.md` (columnas de `producto_fotos`); verificar leyendo los documentos
- [x] 4.2 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores
- [ ] 4.3 Con autorización, correr `php artisan migrate` sobre `comercio`; verificar con la herramienta `database-schema` de Boost y con un recorrido manual desde el celular: subir fotos de un producto, cambiar la principal, asignar un color y eliminar una

## Workflow follow-up

- Commitear antes, en orden, `fase-1-catalogo` y `fase-1-stock`, para que este cambio quede en un diff separado.
- La venta rápida (VEN-01) debe usar `Producto::fotoPara($color)` para mostrar la foto del color elegido.
