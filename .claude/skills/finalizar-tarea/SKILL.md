---
name: finalizar-tarea
description: Ejecuta la lista de comprobación final de una tarea y genera un informe verificable.
---
# Finalizar tarea

1. Confirma el estado del repositorio y revisa `git diff --check`.
2. Identifica los tests adecuados según el proyecto y ejecuta los relevantes.
3. Si se cambió frontend, ejecuta el build disponible en `package.json`.
4. Si se cambiaron rutas, valida con `php artisan route:list` cuando sea seguro.
5. Revisa el diff completo para detectar cambios ajenos a la tarea, secretos o archivos generados.
6. No hagas commit, push, despliegue ni migración sin autorización explícita.
7. Informa: resumen, archivos cambiados, comandos y resultados, pruebas pendientes y riesgos.
8. Nunca inventes resultados ni ocultes pruebas fallidas.
