# Tasks

> Alcance: este cambio actualiza los requerimientos y el modelo de datos documentados. El comportamiento de `specs/catalogo-talles/spec.md` se implementa en los cambios de la Fase 1 (base/parámetros, catálogo, ventas, clientes y reportes), que deben cumplir esa spec y las decisiones D1–D5 de design.md.

## 1. Generalizar el alcance del sistema

- [x] 1.1 En `documentacion/00-indice.md`, cambiar el título y la descripción de "zapatería de dama" a "comercio minorista", mencionando el calzado como caso de uso principal con talles; verificar con `grep -i "zapater" documentacion/00-indice.md` que no quede como definición exclusiva del sistema
- [x] 1.2 En `documentacion/01-relevamiento-y-alcance.md`, cambiar "variantes talle + color" por "variantes por color y, opcionalmente, talle (configurable)" y agregar "talles en productos" a la lista de **Extensibilidad**; verificar leyendo la sección "Alcance funcional" y "Requerimientos no funcionales derivados"

## 2. Requerimientos (doc 02)

- [x] 2.1 Actualizar CAT-01 para que las categorías sean configurables (ABM) con las de calzado como ejemplo, y CAT-02 a "Variantes por color y, si el parámetro de talles está habilitado, por talle; cada una con SKU y código de barras/QR propio"; verificar que ambos IDs y prioridades se mantienen
- [x] 2.2 Agregar CAT-06 (A): "Parámetro 'Usar talles en productos' habilitable/deshabilitable por el Administrador; deshabilitado oculta el talle en catálogo, venta, clientes y reportes sin borrar datos"; verificar que el ID no colisiona con otro existente
- [x] 2.3 Ajustar VEN-01 ("talle, si está habilitado, y color"), CLI-01 ("talle habitual, si está habilitado"), REP-04 ("curva de talles, si está habilitado") y RNF-06 (sumar talles a los parámetros configurables); verificar con `grep -n "talle" documentacion/02-requerimientos.md` que cada mención aclara su condición

## 3. Reportes y modelo de datos (docs 03 y 05)

- [x] 3.1 En `documentacion/03-reportes.md`, marcar "Por producto, talle y color" y "Curva de talles" como dependientes del parámetro, y generalizar el ejemplo de categorías; verificar leyendo las secciones Ventas y Stock
- [x] 3.2 En `documentacion/05-modelo-de-datos.md`, agregar `usar_talles` a `parametros`; cambiar `variantes.talle` a nullable con la columna generada `talle_clave` y la unicidad `(producto_id, color_id, talle_clave)` según D2; marcar `clientes.talle_habitual` como opcional; agregar la decisión de diseño correspondiente; verificar que la sección "Decisiones de diseño" la incluye
- [x] 3.3 Cambiar "lo que ve la clienta" por "lo que ve el cliente" y otras referencias exclusivas a calzado femenino en el doc 05; verificar con `grep -in "clienta" documentacion/` sin resultados

## 4. Plan por fases (doc 06)

- [x] 4.1 En `documentacion/06-fases.md`, incluir CAT-06 en la fila "Catálogo" de la Fase 1 y "parámetro de talles" en la fila "Base"; aclarar que la justificación de cambios de talle aplica a comercios con talles; verificar leyendo la tabla de la Fase 1

## 5. Verificación de consistencia

- [x] 5.1 Revisar que todos los documentos usen el mismo nombre de parámetro ("Usar talles en productos") y los mismos IDs; verificar con `grep -rn "talle" documentacion/` y `git diff --check`
- [x] 5.2 Ejecutar `openspec validate talles-configurables --strict` y verificar que pasa sin errores

## Workflow follow-up

- Al crear los cambios de Fase 1 (parámetros, catálogo, venta rápida, clientes, reportes), referenciar `catalogo-talles` y agregar tests de: alta de variante con/sin talle según parámetro, duplicado de variante sin talle, autorización y auditoría del cambio de parámetro, y ocultamiento de talle en venta y ficha de cliente.
- Archivar este cambio una vez revisada la documentación, para que `catalogo-talles` quede en `openspec/specs/` como requisito vigente.
