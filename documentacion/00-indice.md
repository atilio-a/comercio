# Sistema de gestión para comercio minorista — Documentación de análisis

Sistema web mobile-first para la gestión integral de un comercio minorista. El caso de uso principal es un comercio de calzado (botas, sandalias, zapatos y zapatillas), que trabaja con talles; el uso de talles es configurable con el parámetro "Usar talles en productos", de modo que el sistema también sirve a comercios que no los manejan (por ejemplo, regalería, librería o bazar).

**Stack:** Laravel 12 · LAMP (PHP + MySQL/MariaDB) · Tailwind CSS

## Documentos

| N° | Archivo | Contenido |
|----|---------|-----------|
| 01 | `01-relevamiento-y-alcance.md` | Alcance, decisiones de diseño (multi-sucursal, facturación desacoplada) |
| 02 | `02-requerimientos.md` | Requerimientos funcionales y no funcionales con prioridades |
| 03 | `03-reportes.md` | Módulo de reportes y tablero |
| 04 | `04-caja-pagos-cuentas-corrientes.md` | Caja, medios de pago y cuentas corrientes |
| 05 | `05-modelo-de-datos.md` | Entidades, relaciones y decisiones de diseño de base de datos |
| 06 | `06-fases.md` | Plan de entrega por fases y temas abiertos |

## Convenciones de prioridad
- **A** = alta (MVP)
- **M** = media
- **B** = baja (fase posterior)
