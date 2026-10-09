---
name: implementar-funcionalidad
description: Flujo estructurado para analizar, planificar, implementar y verificar una funcionalidad Laravel 12 conservando las pruebas existentes.
---
# Implementar funcionalidad

1. Lee `CLAUDE.md` y las instrucciones de directorios relevantes.
2. Revisa `git status --short` y no sobrescribas cambios previos del usuario.
3. Analiza requisitos y el código relacionado: rutas, controladores, modelos, policies, vistas, migraciones y tests.
4. Presenta un plan antes de editar: alcance, archivos, decisiones, riesgos y pruebas.
5. Espera aprobación si no se autorizó claramente la implementación.
6. Implementa el cambio mínimo completo y consistente con los patrones del repositorio.
7. Añade o ajusta tests sin eliminar ni debilitar los existentes.
8. Ejecuta los tests relevantes; ejecuta la suite completa si el alcance y el tiempo lo permiten.
9. Revisa `git diff --check`, `git diff` y el estado de Git.
10. Informa resultados verificables, tests no ejecutados y riesgos pendientes.

No ejecutes operaciones destructivas, migraciones contra producción ni cambios de secretos.
