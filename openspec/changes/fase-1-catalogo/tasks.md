# Tasks

> Requiere autorización del usuario antes de correr migraciones sobre `comercio` (5.4). No usar `migrate:fresh` ni `db:wipe`. Sin dependencias nuevas.

## 1. Permisos del catálogo

- [x] 1.1 Agregar `CatalogoVer` y `CatalogoGestionar` a `App\Enums\Permiso` y la matriz de D7 en `Rol::permisos()`; verificar con `DatosInicialesTest` ampliado: Gerente tiene ambos y Vendedor, Cajero y Depósito solo `catalogo.ver`, sin romper los tests existentes de roles

## 2. Tablas maestras

- [x] 2.1 Crear migraciones, modelos y factories de `categorias`, `marcas`, `temporadas`, `colores` y `alicuotas_iva` según D1, con scope de activos; verificar con un test de unicidad sin distinguir mayúsculas ("Botas" contra "botas")
- [x] 2.2 Crear `AlicuotaIvaSeeder`, `ColorSeeder` (idempotentes, en `DatabaseSeeder`) y `CategoriasCalzadoSeeder` (opcional, fuera de `DatabaseSeeder`); verificar con tests: los datos iniciales crean las 4 alícuotas y "Único" sin categorías, y repetir la carga no duplica
- [x] 2.3 Implementar el enum `TablaMaestra`, `MaestroController`, el Form Request y `MaestroPolicy` con vistas compartidas (listado, alta, edición, activar/desactivar) para categorías, marcas, temporadas y colores, con auditoría en transacción; verificar con tests de alta, nombre repetido, desactivación auditada y 403 para Vendedor
- [x] 2.4 Implementar el ABM de alícuotas (porcentaje entre 0 y 100 con 2 decimales, porcentaje no editable si está en uso) con auditoría; verificar con tests de porcentaje 150 rechazado y alícuota en uso

## 3. Cálculos y códigos

- [x] 3.1 Implementar `CalculadoraIva` con aritmética entera (D2); verificar con tests unitarios: 12100,00 al 21 % da 10000,00 + 2100,00; 100,00 al 21 % da 82,64 + 17,36; 0 % sin IVA; 10,5 % y 27 %; y neto + IVA igual al final para una muestra de importes
- [x] 3.2 Implementar `GeneradorCodigos` (SKU legible con sufijo ante colisión; EAN-13 interno con prefijo 2, secuencia con `lockForUpdate` y salto de ocupados, D3); verificar con tests: dígito verificador correcto, códigos únicos en altas sucesivas y salto cuando un código manual ocupa el siguiente número
- [x] 3.3 Implementar `GeneradorVariantes` (parser de talles con comas, rangos y alfanuméricos; combinaciones; omisión de existentes, D4); verificar con tests unitarios: "35-38, 40" da 5 talles, rangos inválidos o de más de 30 valores se rechazan y no hay repetidos

## 4. Productos y variantes

- [x] 4.1 Crear migraciones, modelos y factories de `productos` y `variantes` (D1, con `talle_clave` generada y el índice único), con relaciones, scope `vendibles()` y casts `decimal:2`; verificar con tests de duplicado con talle, duplicado sin talle y `vendibles()` con producto inactivo
- [x] 4.2 Crear `ProductoPolicy`, el listado con búsqueda por nombre, SKU y código exacto, filtros por categoría, marca y estado, y paginación; verificar con tests de búsqueda por código, filtro por categoría, Vendedor sin botones de gestión y usuario sin `catalogo.ver` con 403
- [x] 4.3 Implementar el alta de producto con grilla (`StoreProductoRequest` con talle según el parámetro, límite de 200 y al menos un color; transacción con códigos y auditoría `producto.creado`); verificar con tests de los escenarios de la spec: 2×3 = 6 variantes, talles deshabilitados sin talle, más de 200 rechazado, sin colores rechazado, talle prohibido con talles deshabilitados, códigos generados y códigos manuales repetidos rechazados
- [x] 4.4 Implementar la pantalla del producto con sus variantes (talle mostrado si existe, neto e IVA calculados), la edición de datos del producto (incluido activar o desactivar) y la auditoría `producto.actualizado`; verificar con tests de cálculo mostrado, desactivación y variante con talle visible aunque los talles estén deshabilitados
- [x] 4.5 Implementar la edición de variante (`UpdateVarianteRequest`: solo SKU, código, costo, precio y activo; color y talle ignorados) con auditoría de precio y costo; verificar con tests de cambio de precio auditado (89900 → 94900), intento de cambiar talle sin efecto, precio 0 y costo negativo rechazados, y código en uso por una variante inactiva rechazado
- [x] 4.6 Implementar "agregar variantes" a un producto existente reutilizando la grilla y omitiendo existentes; verificar con test: negro 35-37 más "37-38" crea solo negro / 38 e informa que negro / 37 ya existía

## 5. Interfaz e integración

- [x] 5.1 Vistas mobile-first del catálogo (listado en tarjetas, formulario de grilla con contador de combinaciones en Alpine, campo talles oculto con talles deshabilitados, campos de código que acepten lector de código de barras) en español y accesibles; verificar con tests de que el campo talles no se renderiza con el parámetro deshabilitado y revisión visual en ancho móvil
- [x] 5.2 Agregar a la navegación la entrada "Catálogo" (`catalogo.ver`) y las tablas maestras (`catalogo.gestionar`); verificar extendiendo `NavegacionTest` por rol
- [x] 5.3 Ejecutar `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `npm run build` y `git diff --check`; verificar que todo termina sin errores y revisar el diff
- [ ] 5.4 Con autorización, correr `php artisan migrate` y los seeders `RolesYPermisosSeeder`, `AlicuotaIvaSeeder` y `ColorSeeder` sobre `comercio`; verificar con la herramienta `database-schema` de Boost y con un recorrido manual: crear categoría y producto con grilla, editar un precio y ver la auditoría

## Workflow follow-up

- Cerrar antes `fase-1-base` (tarea 1.2 y recorrido 8.2) y decidir si se commitean sus cambios antes de empezar este.
- Al archivar, verificar junto con `talles-configurables`: este cambio implementa sus requisitos de catálogo; los de venta, clientes y reportes quedan para esos cambios.
- Siguiente cambio sugerido de la Fase 1: stock por variante y sucursal con kardex (STK-01, STK-02, VEN-09).
