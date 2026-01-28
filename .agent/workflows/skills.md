---
description: De la lista de 244+, estas son las que debes tener activas o "cargadas" mentalmente en tu agente
---

De la lista de 244+, estas son las que debes tener activas o "cargadas" mentalmente en tu agente:

rag-engineer (La Joya de la Corona)

Por qué: Tu proyecto es un sistema RAG (Retrieval-Augmented Generation). Extraes datos de SQL Server (Retrieval), los procesas en MySQL y usas Gemini (Generation) para dar insights. Esta skill es crítica para diseñar los chunks de información que le pasas a la IA (como el JSON del historial de proyectos).

software-architecture

Por qué: Estás integrando un sistema monolítico legacy (Cafca) con una app moderna. Necesitas patrones sólidos (Repository Pattern, ReadOnlyTrait) para no corromper la base de datos antigua.

prompt-engineer

Por qué: Ya tenemos los prompts (Auditor, Watchdog), pero necesitarás refinar la "ingeniería" de los mismos para reducir alucinaciones y asegurar que el JSON de salida sea siempre válido para Filament.

database-design

Por qué: El diseño de la tabla project_insights es híbrido (columnas relacionales + JSON full_dna). Esta skill ayudará a indexar correctamente para que el dashboard vuele.

clean-code

Por qué: Con la mentalidad "Zero Complacency", el código debe ser mantenible desde el día 1. Nada de código espagueti en los controladores.

security-review

Por qué: Manejas datos financieros sensibles (márgenes, costos, empleados). Aunque sea una herramienta interna, la seguridad es no negociable.

systematic-debugging

Por qué: La conexión sqlsrv con bases de datos legacy suele dar problemas de encoding (UTF-8 vs Latin1) y tipos de datos extraños. Esta skill te ayudará cuando Laravel intente leer un string y reciba basura.

backend-dev-guidelines

Para: Implementar Laravel Job Batching. Esta skill maneja la arquitectura de colas, failovers y monitoreo asíncrono que el reporte exige para evitar el timeout.

production-code-audit

Para: La limpieza de datos Legacy. Esta skill es experta en "Defensive Coding". La usaremos para crear los Accessors y Mutators que limpian los espacios en blanco y castean las fechas extrañas de SQL Server automáticamente.

rag-implementation

Para: El "Semantic Fingerprint". Esta skill entiende cómo optimizar llamadas a LLMs. Te ayudará a crear la lógica de hash (MD5) para no gastar dinero en proyectos que no han cambiado.

`skills/game-development/web-games` |
| **web-performance-optimization** | "Optimize website and web application performance including loading speed, Core Web Vitals, bundle size, caching strategies, and runtime performance"
