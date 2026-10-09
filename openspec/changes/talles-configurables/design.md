# Design

## Context

- El repositorio es un esqueleto Laravel 12 (solo `User`, migraciones base, PHPUnit 11, Tailwind 4). No existen todavía `parametros`, `variantes`, `clientes` ni reportes: se construyen en la Fase 1 según `documentacion/`.
- Por eso este cambio tiene dos partes: (1) actualizar los requerimientos y el modelo de datos documentados, y (2) fijar las decisiones técnicas que deberá respetar la implementación de catálogo, venta, clientes y reportes. Ver proposal.md (Why) y `specs/catalogo-talles/spec.md` para el comportamiento.
- Restricciones vigentes: MySQL/MariaDB en producción; los tests hoy usan la conexión por defecto (sqlite en memoria está comentado en `phpunit.xml`), así que las migraciones deben funcionar en ambos motores.

## Goals / Non-Goals

**Goals:**
- Un único punto de verdad para saber si los talles están habilitados, consultable desde backend y vistas Blade.
- Modelo de `variantes` que funcione igual con y sin talle, sin ramas de esquema distintas por tipo de comercio.
- Cambiar el parámetro en cualquier momento sin migrar datos.

**Non-Goals:**
- Talle opcional por categoría o por producto (posible evolución; el modelo elegido no lo impide).
- Color opcional.
- Catálogo de talles normalizado (tabla `talles`); el talle sigue siendo texto libre corto.

## Decisions

### D1. Parámetro global en `parametros`, clave `usar_talles`
Se guarda en la tabla `parametros` (clave, valor) ya prevista, con valor `1`/`0` y semilla `1`. Se lee a través del mismo acceso a parámetros que usarán multi-sucursal, facturación y stock negativo, con caché invalidada al guardar.
- *Alternativa descartada:* un campo `usa_talles` en `categorias`. Da más flexibilidad, pero el usuario pidió un interruptor en parametrización y agrega complejidad en venta y reportes. Queda como evolución compatible (D2 no lo impide).
- *Alternativa descartada:* variable en `.env`/`config`. No es editable por el Administrador sin tocar el servidor.

### D2. `variantes.talle` nullable + clave generada para la unicidad
`talle` pasa a `varchar(10) NULL` (NULL = sin talle). Como en MySQL/MariaDB un índice único admite varios NULL, se agrega una columna generada almacenada `talle_clave = COALESCE(talle, '')` y el índice único pasa a `(producto_id, color_id, talle_clave)`. Laravel la expresa con `->storedAs(...)`, soportado por MySQL, MariaDB y SQLite.
- *Alternativa descartada:* `talle NOT NULL DEFAULT ''`. Más simple, pero mezcla "sin talle" con un valor vacío y obliga a filtrar `''` en reportes y vistas.
- *Alternativa descartada:* unicidad solo por validación en aplicación. No protege ante altas simultáneas.

### D3. Validación según el parámetro en el Form Request de variantes
El Form Request de alta/edición de variante aplica `required` sobre `talle` si el parámetro está habilitado y `prohibited` si está deshabilitado, más una regla de unicidad sobre producto + color + talle (tratando ausencia como valor único). La base de datos (D2) es la última barrera; la validación da el mensaje amigable.

### D4. La UI decide por parámetro, el dato decide qué mostrar
- Formularios, filtros y pasos de selección (alta de variante, venta rápida, ficha de cliente, filtros de reportes, menú "Curva de talles") se muestran solo si el parámetro está habilitado.
- La presentación de una variante existente (nombre en búsquedas, ticket, kardex) muestra el talle si la variante lo tiene, sin importar el parámetro. Se centraliza en un único accessor/descripción de variante para no repetir la lógica en Blade.
- El parámetro se comparte a las vistas una sola vez (view composer o componente) en lugar de consultarlo en cada plantilla.

### D5. Autorización y auditoría
La edición de parámetros queda bajo el permiso de configuración del Administrador (spatie/laravel-permission en modo teams, como prevé el doc 05). El cambio se registra en `auditorias` con `antes` y `despues`, igual que cualquier otro parámetro.

### D6. Documentación: de "zapatería de dama" a "comercio minorista"
Se generaliza la redacción de los documentos y las categorías de calzado pasan a ser datos iniciales de ejemplo (ABM). Los textos específicos de calzado se conservan como ejemplos cuando ayudan a entender (por ejemplo, cambios de talle en la Fase 1).

## Risks / Trade-offs

- [Deshabilitar talles en una zapatería por error oculta el paso de talle en la venta] → Solo el Administrador puede cambiarlo, queda auditado y el texto de ayuda advierte el efecto; los datos no se pierden y se revierte habilitándolo.
- [Mezcla de variantes con y sin talle en un mismo producto tras cambiar el parámetro] → Es válido por diseño (D2 garantiza unicidad); los reportes agrupan por variante y muestran el talle cuando existe.
- [Columna generada poco familiar para el equipo] → Se documenta en la migración y se cubre con un test que intenta duplicar una variante sin talle.
- [Reportes por talle con datos parciales] → Con el parámetro habilitado, las variantes sin talle se agrupan como "Sin talle".

## Migration Plan

No hay datos ni esquema de catálogo en producción. Los pasos son: actualizar la documentación (este cambio) y luego implementar D1–D5 dentro de la Fase 1 al crear `parametros`, `variantes` y `clientes`. Rollback: al no haber datos previos, basta con revertir la migración de `variantes`; el parámetro es una fila en `parametros`.

## Open Questions

- Texto exacto de la ayuda en la pantalla de parametrización (se define junto con el diseño de esa pantalla).
- Longitud máxima del talle: se propone 10 caracteres (admite "37.5", "XXL", "Único"); se puede ajustar al crear la migración sin afectar la spec.
