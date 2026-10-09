# Spec Delta

## Purpose

Permite identificar visualmente los productos con una galería de imágenes por producto, con una imagen principal y fotos opcionales por color, cargadas y vistas cómodamente desde el celular.

## ADDED Requirements

### Requirement: Galería de imágenes por producto
Un usuario con `catalogo.gestionar` SHALL poder agregar imágenes a un producto, de a una o varias juntas, hasta un máximo de 10 por producto. Las imágenes SHALL mostrarse en el orden en que se cargaron.

#### Scenario: Agregar varias imágenes
- **WHEN** un Gerente sube 3 imágenes a "Bota Ana", que no tenía ninguna
- **THEN** el producto queda con 3 imágenes en el orden de carga

#### Scenario: Límite de imágenes
- **WHEN** un producto tiene 9 imágenes y se intentan subir 2 más
- **THEN** el sistema rechaza la carga indicando que el máximo es 10 y no agrega ninguna

### Requirement: Imagen principal
Todo producto con imágenes SHALL tener exactamente una imagen principal. La primera imagen cargada SHALL ser la principal. Un usuario con `catalogo.gestionar` SHALL poder elegir otra como principal. Si se elimina la principal, SHALL pasar a ser principal la siguiente en orden de carga.

#### Scenario: Primera imagen
- **WHEN** se sube la primera imagen de un producto
- **THEN** esa imagen queda como principal

#### Scenario: Cambiar la principal
- **WHEN** se marca como principal la segunda imagen
- **THEN** la segunda pasa a ser la única principal

#### Scenario: Eliminar la principal
- **WHEN** se elimina la imagen principal de un producto con 3 imágenes
- **THEN** la siguiente en orden de carga queda como principal

### Requirement: Color opcional por imagen
Cada imagen SHALL poder asociarse a uno de los colores que tienen las variantes del producto, o a ninguno. Para un color, la imagen a mostrar SHALL ser la primera asociada a ese color; si no hay ninguna, la principal del producto.

#### Scenario: Imagen del color
- **WHEN** "Bota Ana" tiene una imagen principal sin color y otra asociada a "Suela"
- **THEN** para el color suela se muestra la imagen de suela, y para negro la principal

#### Scenario: Color ajeno al producto
- **WHEN** se intenta asociar una imagen a un color que no tiene ninguna variante del producto
- **THEN** el sistema rechaza el cambio con un error de validación

### Requirement: Validación de archivos
El sistema SHALL aceptar solo imágenes JPEG, PNG o WebP, de hasta 5 MB y como máximo 4000 px de lado, verificando el contenido real del archivo y no solo su extensión.

#### Scenario: Archivo que no es imagen
- **WHEN** se sube un PDF renombrado como "foto.jpg"
- **THEN** el sistema lo rechaza indicando los formatos admitidos

#### Scenario: Archivo demasiado grande
- **WHEN** se sube una imagen de más de 5 MB
- **THEN** el sistema la rechaza indicando el tamaño máximo

### Requirement: Optimización en el navegador
Antes de subir, la interfaz SHALL reducir cada imagen para que su lado mayor no supere 1600 px, respetando la orientación de la foto, y SHALL generar una miniatura de 400 px que el sistema guarda junto con la imagen. Si el navegador no puede procesar la imagen, SHALL subir el archivo original, que el servidor valida igual. Si no llega la miniatura, el sistema SHALL usar la imagen como miniatura.

#### Scenario: Foto grande desde el celular
- **WHEN** un usuario elige una foto de 4000 × 3000 px tomada con el celular
- **THEN** se sube una imagen de 1600 × 1200 px y su miniatura de 400 × 300 px

#### Scenario: Navegador sin soporte
- **WHEN** el navegador no puede reducir la imagen y el archivo original cumple las validaciones
- **THEN** la imagen se guarda y se usa también como miniatura

### Requirement: Acceso privado a las imágenes
Las imágenes SHALL guardarse fuera de la carpeta pública y SHALL servirse solo a usuarios con sesión y permiso `catalogo.ver`.

#### Scenario: Visitante sin sesión
- **WHEN** alguien sin sesión pide la dirección de una imagen
- **THEN** el sistema lo redirige a la pantalla de ingreso y no entrega el archivo

#### Scenario: Usuario con permiso
- **WHEN** un Vendedor abre la ficha de un producto con imágenes
- **THEN** ve las imágenes

### Requirement: Visualización de imágenes
La ficha del producto SHALL mostrar la galería con la principal destacada y el color de cada imagen. El listado de productos y el listado de stock SHALL mostrar la miniatura principal de cada producto, o un marcador neutro si no tiene. Toda imagen SHALL tener un texto alternativo con el nombre del producto y, si corresponde, el color.

#### Scenario: Producto sin imágenes
- **WHEN** un producto no tiene imágenes
- **THEN** el listado muestra un marcador neutro en lugar de la miniatura

### Requirement: Eliminar imágenes
Un usuario con `catalogo.gestionar` SHALL poder eliminar una imagen. El registro y sus archivos SHALL borrarse; los archivos solo después de confirmarse la operación en la base de datos.

#### Scenario: Eliminar una imagen
- **WHEN** un Gerente elimina una imagen de un producto
- **THEN** la imagen deja de mostrarse y sus archivos ya no existen

### Requirement: Permisos y auditoría de imágenes
Agregar, cambiar o eliminar imágenes SHALL requerir `catalogo.gestionar`, y cada operación SHALL registrarse en la auditoría con el producto y los datos de la imagen.

#### Scenario: Vendedor intenta subir una imagen
- **WHEN** un Vendedor envía una imagen para un producto
- **THEN** el sistema responde con un error de autorización y no guarda nada

#### Scenario: Alta auditada
- **WHEN** un Gerente agrega una imagen
- **THEN** la auditoría registra el usuario, el producto y la imagen agregada
