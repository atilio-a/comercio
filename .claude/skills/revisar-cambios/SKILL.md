---
name: revisar-cambios
description: Revisa un diff Laravel en busca de regresiones, problemas de seguridad y falta de pruebas, sin modificar archivos.
---
# Revisar cambios

- Inspecciona `git status --short` y el diff sin descartar cambios preexistentes.
- Comprueba coherencia con `CLAUDE.md` y patrones existentes.
- Revisa validación, autorización, mass assignment, consultas, transacciones, XSS y datos sensibles.
- Revisa impacto en rutas, contratos, migraciones, Blade, Tailwind y Alpine.
- Comprueba que los cambios estén cubiertos por tests y que no se hayan eliminado tests relevantes.
- No modifiques archivos ni ejecutes comandos destructivos.
- Devuelve hallazgos priorizados: bloqueante/alto/medio/bajo, evidencia, impacto y recomendación.
- Si no hay hallazgos, indicá el alcance de la revisión y las limitaciones; no afirmes seguridad absoluta.
