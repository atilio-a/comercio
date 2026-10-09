# Spec Delta

## Purpose

Mantiene las tablas maestras que clasifican y valorizan los productos (categorías, marcas, temporadas, colores y alícuotas de IVA), configurables por cada comercio sin tocar código.

## ADDED Requirements

### Requirement: Mantenimiento de tablas maestras
Un usuario con permiso `catalogo.gestionar` SHALL poder listar, crear, editar y activar o desactivar categorías, marcas, temporadas y colores. Cada registro SHALL tener un nombre único dentro de su tabla, sin distinguir mayúsculas.

#### Scenario: Alta de una marca
- **WHEN** un usuario con `catalogo.gestionar` crea la marca "Vizzano"
- **THEN** la marca queda activa y disponible para asignar a productos

#### Scenario: Nombre repetido
- **WHEN** ya existe la categoría "Botas" y se intenta crear la categoría "botas"
- **THEN** el sistema rechaza el alta indicando que el nombre ya está en uso

#### Scenario: Usuario sin permiso
- **WHEN** un usuario con rol Vendedor intenta crear una categoría
- **THEN** el sistema responde con un error de autorización y no crea nada

### Requirement: Desactivar en lugar de borrar
Los registros de las tablas maestras SHALL no borrarse. Un registro desactivado SHALL dejar de ofrecerse al crear o editar productos, y SHALL seguir mostrándose en los productos que ya lo usan.

#### Scenario: Temporada desactivada
- **WHEN** se desactiva la temporada "Invierno 2026"
- **THEN** no aparece entre las opciones al crear un producto
- **AND** los productos que ya la tenían la siguen mostrando

### Requirement: Alícuotas de IVA
El sistema SHALL ofrecer las alícuotas de IVA 21 %, 10,5 %, 27 % y 0 % como datos iniciales. Un usuario con `catalogo.gestionar` SHALL poder agregar alícuotas con nombre y porcentaje entre 0 y 100 con hasta dos decimales, y desactivarlas. El porcentaje de una alícuota usada por algún producto SHALL no poder modificarse.

#### Scenario: Datos iniciales
- **WHEN** se instala el sistema y se cargan los datos iniciales
- **THEN** existen las alícuotas 21 %, 10,5 %, 27 % y 0 %, activas

#### Scenario: Porcentaje inválido
- **WHEN** se intenta crear una alícuota con porcentaje 150
- **THEN** el sistema rechaza el alta con un error de validación

#### Scenario: Alícuota en uso
- **WHEN** la alícuota "IVA 21 %" está asignada a un producto y se intenta cambiar su porcentaje
- **THEN** el sistema rechaza el cambio indicando que la alícuota está en uso

### Requirement: Color por defecto
El sistema SHALL crear como dato inicial el color "Único", para productos que no se diferencian por color.

#### Scenario: Comercio sin colores
- **WHEN** un comercio carga un producto que no tiene variantes de color
- **THEN** puede usar el color "Único" para su única variante

### Requirement: Categorías de calzado como ejemplo opcional
Las categorías Botas, Sandalias, Zapatos y Zapatillas SHALL poder cargarse con un seeder opcional. No SHALL cargarse con los datos iniciales obligatorios.

#### Scenario: Instalación para un comercio que no vende calzado
- **WHEN** se cargan solo los datos iniciales obligatorios
- **THEN** no existe ninguna categoría y el comercio crea las propias

### Requirement: Cambios en tablas maestras auditados
Toda alta, modificación, activación o desactivación de un registro de tabla maestra SHALL registrarse en la auditoría con los valores anteriores y nuevos.

#### Scenario: Desactivación auditada
- **WHEN** un usuario desactiva el color "Fucsia"
- **THEN** la auditoría registra el usuario, el color y el cambio de activo a inactivo
