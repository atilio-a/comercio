# Spec Delta

## Purpose

Define los productos y sus variantes, que son la unidad de stock y de venta: cómo se crean, cómo se identifican con SKU y código de barras, cómo se fija su precio con IVA y quién puede consultarlos o modificarlos.

## ADDED Requirements

### Requirement: Permisos del catálogo
El sistema SHALL controlar el catálogo con los permisos `catalogo.ver` (consultar productos y variantes) y `catalogo.gestionar` (crear y modificar productos, variantes y tablas maestras). Gerente SHALL tener ambos; Vendedor, Cajero y Depósito SHALL tener solo `catalogo.ver`; el Administrador SHALL tener todos.

#### Scenario: Vendedor consulta el catálogo
- **WHEN** un usuario con rol Vendedor abre el listado de productos
- **THEN** ve los productos, pero no las opciones de crear ni editar

#### Scenario: Cajero intenta modificar un precio
- **WHEN** un usuario con rol Cajero envía una modificación de precio de una variante
- **THEN** el sistema responde con un error de autorización y el precio no cambia

### Requirement: Alta de producto
Un usuario con `catalogo.gestionar` SHALL poder crear un producto con nombre, categoría activa obligatoria, marca y temporada activas opcionales, y alícuota de IVA activa obligatoria (21 % por defecto).

#### Scenario: Producto sin categoría
- **WHEN** se intenta crear un producto sin categoría
- **THEN** el sistema rechaza el alta indicando que la categoría es obligatoria

#### Scenario: Producto sin marca ni temporada
- **WHEN** se crea un producto con nombre, categoría y alícuota, sin marca ni temporada
- **THEN** el producto se crea correctamente

### Requirement: Generación de variantes por grilla
Al crear un producto, el sistema SHALL generar una variante por cada combinación de los colores elegidos y, con talles habilitados, de los talles ingresados, todas con el costo y el precio final indicados. Los talles SHALL poder ingresarse separados por coma y como rangos numéricos (por ejemplo "35-38, 40"). El sistema SHALL rechazar grillas de más de 200 combinaciones.

#### Scenario: Grilla con talles habilitados
- **WHEN** con talles habilitados se crea el producto "Bota Ana" con los colores negro y suela, los talles "35-37", costo 40000 y precio final 89900
- **THEN** se crean 6 variantes (2 colores × 3 talles), cada una con costo 40000 y precio final 89900

#### Scenario: Grilla con talles deshabilitados
- **WHEN** con talles deshabilitados se crea el producto "Taza Flores" con los colores azul y rojo
- **THEN** se crean 2 variantes sin talle

#### Scenario: Grilla demasiado grande
- **WHEN** se elige una combinación de colores y talles que da más de 200 variantes
- **THEN** el sistema rechaza el alta indicando el máximo permitido

#### Scenario: Producto sin variantes
- **WHEN** se intenta crear un producto sin elegir ningún color
- **THEN** el sistema rechaza el alta indicando que debe tener al menos una variante

### Requirement: Agregar variantes a un producto existente
Un usuario con `catalogo.gestionar` SHALL poder agregar variantes a un producto existente con la misma grilla de colores y talles. Las combinaciones que ya existen SHALL omitirse e informarse, sin generar error.

#### Scenario: Agregar un talle nuevo
- **WHEN** "Bota Ana" tiene talles 35 a 37 en negro y se agregan los talles "37-38" en negro
- **THEN** se crea solo la variante negro / 38
- **AND** el sistema informa que negro / 37 ya existía

### Requirement: Identidad de la variante
El color y el talle de una variante SHALL no poder modificarse una vez creada, porque identifican lo que se stockea y se vende. El SKU, el código de barras, el costo, el precio final y el estado activo SHALL poder editarse.

#### Scenario: Intento de cambiar el talle
- **WHEN** se envía una edición de la variante negro / 37 con talle 38
- **THEN** el talle no cambia y la variante conserva negro / 37

### Requirement: Códigos únicos con generación automática
Cada variante SHALL tener un SKU y un código de barras únicos en todo el catálogo. Si al crear una variante el SKU queda vacío, el sistema SHALL generar uno legible derivado del producto, el color y el talle. Si el código de barras queda vacío, el sistema SHALL generar un EAN-13 válido de uso interno con prefijo 2. Un código ingresado a mano SHALL aceptarse si es único y tiene entre 4 y 50 caracteres entre letras, números, guion, guion bajo y punto.

#### Scenario: Códigos generados
- **WHEN** se crea una variante sin SKU ni código de barras
- **THEN** la variante recibe un SKU único y un código de barras EAN-13 que empieza con 2 y tiene dígito verificador válido

#### Scenario: Código de fábrica
- **WHEN** se edita una variante y se escanea el código "7791234567890" con un lector
- **THEN** la variante queda con ese código de barras

#### Scenario: Código repetido
- **WHEN** se intenta asignar a una variante un código de barras que ya tiene otra variante
- **THEN** el sistema rechaza el cambio indicando que el código ya está en uso

### Requirement: Precio final con IVA como dato maestro
Cada variante SHALL tener un costo mayor o igual a 0 y un precio final con IVA mayor a 0, ambos con dos decimales. El sistema SHALL calcular el neto como el precio final dividido por (1 + alícuota), redondeado a dos decimales, y el IVA como la diferencia entre precio final y neto, de modo que neto + IVA sea siempre igual al precio final.

#### Scenario: Cálculo con 21 %
- **WHEN** una variante de un producto con alícuota 21 % tiene precio final 12100,00
- **THEN** el sistema muestra neto 10000,00 e IVA 2100,00

#### Scenario: Redondeo
- **WHEN** una variante de un producto con alícuota 21 % tiene precio final 100,00
- **THEN** el sistema muestra neto 82,64 e IVA 17,36

#### Scenario: Alícuota 0 %
- **WHEN** una variante de un producto con alícuota 0 % tiene precio final 5000,00
- **THEN** el sistema muestra neto 5000,00 e IVA 0,00

#### Scenario: Precio inválido
- **WHEN** se intenta guardar una variante con precio final 0 o costo negativo
- **THEN** el sistema rechaza el cambio con un error de validación

### Requirement: Desactivar en lugar de borrar
Productos y variantes SHALL no borrarse. Desactivar un producto SHALL dejar inactivas para la venta todas sus variantes. Una variante desactivada SHALL conservar sus códigos, que no podrán reutilizarse.

#### Scenario: Producto desactivado
- **WHEN** se desactiva el producto "Bota Ana"
- **THEN** el producto y sus variantes figuran como inactivos y siguen existiendo con sus datos

#### Scenario: Código de una variante inactiva
- **WHEN** una variante inactiva tiene el código "2000000000015" y se intenta asignar ese código a otra variante
- **THEN** el sistema rechaza el cambio indicando que el código ya está en uso

### Requirement: Búsqueda de productos
Un usuario con `catalogo.ver` SHALL poder buscar productos por nombre, SKU o código de barras exacto, y filtrar por categoría, marca y estado activo, con resultados paginados.

#### Scenario: Búsqueda por código de barras
- **WHEN** un usuario busca "7791234567890"
- **THEN** el resultado muestra el producto que tiene una variante con ese código

#### Scenario: Filtro por categoría
- **WHEN** un usuario filtra por la categoría "Sandalias"
- **THEN** solo ve productos de esa categoría

### Requirement: Cambios del catálogo auditados
Toda alta o modificación de productos y variantes SHALL registrarse en la auditoría con los valores anteriores y nuevos de los campos cambiados, incluidos costo y precio final.

#### Scenario: Cambio de precio auditado
- **WHEN** un Gerente cambia el precio final de la variante negro / 37 de 89900 a 94900
- **THEN** la auditoría registra el usuario, la variante, el precio anterior 89900 y el nuevo 94900
