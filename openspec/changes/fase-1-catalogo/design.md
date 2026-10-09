# Design

## Context

- Sobre `fase-1-base`, que dejó estos patrones y servicios:
  - **Permisos:** `App\Enums\Permiso` y `Rol::permisos()` con `RolesYPermisosSeeder` idempotente, `Gate::before` para el Administrador y policies con `$user->can(...)`.
  - **Validación:** Form Requests con `authorize()` y reglas de negocio en `after()`.
  - **Servicios** en `app/Services/`: `Parametros` (con caché), `RegistradorAuditoria` (que se llama dentro de `DB::transaction`) y `SucursalActiva`.
  - **Vistas:** Breeze (`x-app-layout`, `x-text-input`, `x-input-label`, `x-input-error`, `x-primary-button`) sobre Tailwind 4 y Alpine, mobile-first, con tarjetas en lugar de tablas.
  - **Tests:** PHPUnit sobre `comercio_testing` (MariaDB). `UserFactory::conSucursal($sucursal, $rol)` y los seeders se invocan desde `setUp`.
- El parámetro `Parametro::UsarTalles` ya existe. Su comportamiento en el catálogo está especificado en `talles-configurables` (`catalogo-talles`), y este diseño lo implementa tal como lo resolvía la decisión D2 de ese cambio.
- El catálogo es global: no lleva `sucursal_id`, porque no es una tabla operativa según el doc 05. El stock por sucursal llega en el próximo cambio.
- PHP 8.2 sin `intl`: los importes se formatean con `number_format` (formato argentino: punto de miles, coma decimal).

## Goals / Non-Goals

**Goals:**
- Que el próximo cambio (stock) pueda referenciar `variantes.id` y que la venta encuentre una variante por código de barras con un índice único.
- Cálculo de neto e IVA exacto, sin flotantes, reutilizable por ventas y comprobantes.
- Cargar un producto de calzado con toda su curva en un solo formulario desde el celular.

**Non-Goals:**
- Stock, mínimos y movimientos.
- Listas de precios, fotos, etiquetas e importación masiva.
- Escaneo con la cámara: en los campos de código se puede tipear o usar un lector de código de barras USB o Bluetooth, que funciona como teclado.

## Decisions

### D1. Esquema
Todas las tablas usan `id` bigint y `timestamps`.
- `categorias`, `marcas`, `temporadas`, `colores`: `nombre varchar(100) unique` y `activa`/`activo` boolean con índice. La colación `utf8mb4_unicode_ci` hace que la unicidad no distinga mayúsculas, como pide la spec.
- `alicuotas_iva`: `nombre varchar(50) unique`, `porcentaje decimal(5,2)` y `activa`.
- `productos`:
  - `nombre varchar(150)` con índice;
  - `categoria_id` FK obligatoria;
  - `marca_id` y `temporada_id` FK opcionales;
  - `alicuota_iva_id` FK;
  - `descripcion text null` y `activo`.
  - Todas las FK son `restrictOnDelete`.
- `variantes`:
  - `producto_id` FK y `color_id` FK;
  - `talle varchar(10) null` y `talle_clave` generada almacenada (`COALESCE(talle, '')`);
  - `sku varchar(50) unique` y `codigo_barras varchar(50) unique`;
  - `costo decimal(14,2)`, `precio_final decimal(14,2)` y `activo`;
  - índice único `(producto_id, color_id, talle_clave)`.
- Modelos con `casts()`: los importes van con `decimal:2` y quedan como string, sin pasar por float.
- *Alternativa descartada:* `talle NOT NULL DEFAULT ''`. Mezcla "sin talle" con un valor vacío (ver D2 de `talles-configurables`).

### D2. Importes en centavos enteros
Servicio `CalculadoraIva`:
- Recibe el precio final como string decimal y la alícuota como string. Los convierte a enteros: centavos y puntos básicos (21,00 % → 2100).
- Calcula `neto = redondeo_mitad_arriba(final × 10000 / (10000 + bps))` y `iva = final − neto`, todo con aritmética entera.
- Devuelve strings con dos decimales.
- Con `decimal(14,2)`, el producto entra en int64.
- *Alternativa descartada:* `bcmath`. No está garantizado en el servidor y no hace falta para estas operaciones.
- *Alternativa descartada:* floats. Dan errores de redondeo en importes.

### D3. Generación de códigos
Servicio `GeneradorCodigos`, invocado al crear variantes cuando el campo queda vacío:
- **SKU:** `{id del producto con 5 dígitos}-{abreviatura del color en 3 letras}[-{talle}]` en mayúsculas y sin acentos (por ejemplo `00012-NEG-37`). Si existe, agrega `-2`, `-3`, etc.
- **EAN-13 interno:** `2` + 11 dígitos tomados de una secuencia + dígito verificador.
  - La secuencia es la fila `parametros` de clave `secuencia_ean_interno`, que se lee con `lockForUpdate` dentro de la transacción. No se expone en la pantalla de parámetros, porque es interna y no es un `App\Enums\Parametro`.
  - Si un código manual ya ocupó el número generado, se salta al siguiente.
- *Alternativa descartada:* derivar el EAN del `id` de la variante. Obliga a insertar y actualizar, y choca con códigos manuales que empiecen con 2.

### D4. Grilla color × talle
- Servicio `GeneradorVariantes::combinaciones(colores, talles)`.
- Parser de talles: separa por coma, expande rangos enteros `a-b` (con a ≤ b y como máximo 30 valores por rango), recorta espacios y elimina repetidos. También acepta talles alfanuméricos ("S", "M", "37.5").
- Con talles deshabilitados, ignora el campo talles y genera una variante por color.
- El límite de 200 combinaciones se valida en el Form Request con `after()`.
- La creación del producto y sus variantes ocurre en una sola transacción, junto con la auditoría.
- Agregar variantes reutiliza el mismo servicio: omite las combinaciones existentes y devuelve la lista de omitidas para mostrarlas.
- Alpine muestra en el formulario la cantidad de combinaciones que se van a crear, solo como ayuda. La validación real está en el servidor.

### D5. Edición de variantes
- `UpdateVarianteRequest` acepta solo `sku`, `codigo_barras`, `costo`, `precio_final` y `activo`. Color y talle no están entre los campos aceptados (`validated()`), así que se ignoran aunque se envíen.
- Edición en masa (aplicar costo y precio a varias variantes) queda fuera. La pantalla del producto lista las variantes con edición individual.

### D6. Activo del producto y de la variante
- Una variante se puede vender si `variante.activo && producto.activo`. Se expone con el scope `Variante::vendibles()`.
- Desactivar el producto no cambia el flag de cada variante, así que al reactivarlo vuelve el estado anterior de cada una.

### D7. Permisos y navegación
- Se agregan a `Permiso` los casos `CatalogoVer = 'catalogo.ver'` y `CatalogoGestionar = 'catalogo.gestionar'`. La matriz en `Rol::permisos()` queda así:
  - Gerente: ambos;
  - Vendedor, Cajero y Depósito: solo `catalogo.ver`.
  - Re-ejecutar `RolesYPermisosSeeder` sincroniza la matriz, porque usa `syncPermissions`.
- Políticas: `ProductoPolicy` y una `MaestroPolicy` compartida por las tablas maestras. Las rutas van agrupadas bajo `/catalogo/...`.
- Navegación: entrada "Catálogo" con `@can('catalogo.ver')`. El submenú de tablas maestras se muestra con `@can('catalogo.gestionar')`.

### D8. Tablas maestras con un controlador genérico
- Las cuatro tablas simples (categorías, marcas, temporadas, colores) tienen el mismo esquema y comportamiento. Se resuelven con un enum `TablaMaestra` (modelo, etiqueta singular y plural, ruta) y un único `MaestroController` con vistas compartidas.
- Las alícuotas tienen su propio controlador, porque suman el porcentaje y la regla "en uso".
- *Alternativa descartada:* cuatro controladores idénticos. Repiten código sin aportar nada.

### D9. Auditoría
Se usa `RegistradorAuditoria` dentro de la transacción de cada operación. Acciones:
- `catalogo.<tabla>.creado`, `catalogo.<tabla>.actualizado`;
- `producto.creado` (incluye la cantidad de variantes generadas), `producto.actualizado`;
- `variante.creada`, `variante.actualizada`.

En el alta por grilla se registra un evento por producto con el detalle de las variantes, no uno por variante, para no inflar la auditoría.

### D10. Seeders
- `AlicuotaIvaSeeder` (21; 10,5; 27; 0) y `ColorSeeder` ("Único"): idempotentes y agregados a `DatabaseSeeder`.
- `CategoriasCalzadoSeeder`: no se agrega a `DatabaseSeeder`; se ejecuta a mano con `php artisan db:seed --class=CategoriasCalzadoSeeder`.

## Risks / Trade-offs

- [Un código de fábrica EAN-13 que empiece con 2 puede chocar con la secuencia interna] → El índice único lo impide y el generador salta números ocupados (D3).
- [El catálogo crece y la búsqueda por nombre con `LIKE '%x%'` no usa índice] → Volumen esperado bajo (un comercio). El código de barras y el SKU usan índice único. Si hiciera falta, más adelante se puede agregar FULLTEXT.
- [La columna generada `talle_clave` en MariaDB 10.4] → Las columnas generadas almacenadas con índice están soportadas desde 10.2. Se cubre con un test de duplicado sin talle.
- [Desactivar un producto deja variantes con `activo = true` propio] → El scope `vendibles()` es el único criterio para la venta. Se documenta y se cubre con un test.
- [Cambiar el precio no conserva historial propio] → La auditoría guarda anterior y nuevo. Los ítems de venta guardarán su propio precio y costo (doc 05).

## Migration Plan

1. Migraciones nuevas: no tocan tablas existentes. Requieren autorización para correrlas sobre `comercio`.
2. Re-ejecutar `RolesYPermisosSeeder`, `AlicuotaIvaSeeder` y `ColorSeeder`, todos idempotentes.
3. Rollback: `php artisan migrate:rollback` del lote. Las tablas son nuevas y sin datos previos.

## Open Questions

- Formato visible de los importes en pantalla (con o sin símbolo `$`). Se puede ajustar sin cambiar specs ni tareas.
