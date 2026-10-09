# Proposal

## Why

Los requerimientos en `documentacion/` describen un sistema exclusivo para una zapatería de dama: toda variante de producto exige un talle (CAT-02) y varias funciones lo dan por sentado (venta rápida, ficha de cliente, reportes por talle). Queremos que la misma herramienta sirva a comercios que no venden calzado (por ejemplo, regalería, librería, bazar), donde el talle no aplica. Conviene resolverlo ahora, antes de implementar la Fase 1, porque condiciona el modelo de `variantes` y los formularios del catálogo y de venta.

## What Changes

- Nuevo parámetro de sistema **"Usar talles en productos"** (habilitado/deshabilitado) en la tabla `parametros`, editable por el Administrador desde la pantalla de parametrización. Valor por defecto: habilitado (comportamiento actual de zapatería).
- CAT-02 pasa de "variantes talle + color" a "variantes por color y, si el parámetro está habilitado, por talle". Con el parámetro deshabilitado, las variantes se identifican solo por producto y color.
- La columna `variantes.talle` pasa a ser opcional (nullable) y la unicidad "producto + color + talle" se mantiene tratando la ausencia de talle como un valor único.
- Cuando el parámetro está deshabilitado se ocultan los campos y filtros de talle en: alta/edición de variantes, venta rápida (VEN-01), ficha de cliente (`talle_habitual`, CLI-01), reportes por talle y curva de talles (REP-04, doc 03).
- Cambiar el parámetro no modifica ni borra datos existentes: las variantes que ya tienen talle lo conservan y se siguen mostrando con él.
- La documentación deja de presentarse como "zapatería de dama" y pasa a "comercio minorista", con las categorías de calzado como datos iniciales de ejemplo (ABM), no como lista fija.
- Se agrega el parámetro a RNF-06 / "Extensibilidad" junto a multi-sucursal, facturación y stock negativo.

No hay cambios **BREAKING**: todavía no existe código de catálogo implementado (el repositorio es un esqueleto Laravel 12), así que el cambio afecta solo requerimientos y el diseño a implementar.

## Capabilities

### New Capabilities
- `catalogo-talles`: uso opcional del talle en variantes de producto, controlado por un parámetro de sistema; define cómo se crean, identifican, venden, informan y muestran las variantes según el parámetro esté habilitado o no.

### Modified Capabilities
<!-- No hay specs existentes en openspec/specs/. -->

## Impact

- **Documentación:** `documentacion/00-indice.md`, `01-relevamiento-y-alcance.md`, `02-requerimientos.md` (CAT-01, CAT-02, VEN-01, CLI-01, REP-04, RNF-06), `03-reportes.md`, `05-modelo-de-datos.md` (`parametros`, `variantes`, `clientes`), `06-fases.md`.
- **Implementación futura (Fase 1):** migración de `variantes` con `talle` nullable y unicidad compatible con MySQL/MariaDB; lectura del parámetro en catálogo, venta, clientes y reportes; pantalla de parametrización con permiso de Administrador.
- **Fuera de alcance:** volver opcional el color (se mantiene obligatorio); habilitar talles por categoría o por producto individual; migrar o limpiar talles existentes al deshabilitar el parámetro.
