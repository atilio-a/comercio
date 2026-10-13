# Spec Delta

## Purpose

Organiza el menú principal en grupos según los permisos del usuario, para que entre en pantallas medianas y siga siendo usable con teclado y lector de pantalla en escritorio y en el celular.

## ADDED Requirements

### Requirement: Menú agrupado
El menú principal SHALL mostrar Inicio y los grupos "Ventas y caja" (Caja, Sesiones de caja, Medios de pago), "Inventario" (Catálogo, Stock, Compras) y "Administración" (Usuarios, Parámetros, Auditoría, Tablas). Cada entrada SHALL mostrarse solo si el usuario tiene su permiso en la sucursal activa, y un grupo sin entradas visibles SHALL no mostrarse.

#### Scenario: Depósito
- **WHEN** un usuario con rol Depósito abre el menú
- **THEN** ve el grupo "Inventario" con Catálogo, Stock y Compras, y no ve "Ventas y caja" ni "Administración"

#### Scenario: Cajero
- **WHEN** un usuario con rol Cajero abre el menú
- **THEN** no ve Compras ni el grupo "Administración"

### Requirement: Accesibilidad del menú
En escritorio cada grupo SHALL abrirse con un botón que indique su estado (`aria-expanded`), sea operable con teclado y se cierre con Escape. En el menú móvil los grupos SHALL ser secciones con título asociado a sus entradas.

#### Scenario: Botón de grupo
- **WHEN** un Administrador abre cualquier página
- **THEN** cada grupo de escritorio es un botón con `aria-expanded` y las secciones móviles tienen título
