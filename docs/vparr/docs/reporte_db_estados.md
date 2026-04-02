# Informe Final: Ingeniería Inversa de Estados de Proyecto (CAFCA)

Este informe detalla el proceso y los hallazgos finales tras la investigación de la lógica de estados en la base de datos SQL Server de Claesen.

## 1. Arquitectura de Datos Descubierta

El sistema ERP (CAFCA) utiliza una estructura multi-base de datos para gestionar configuraciones:

- **`CLAESEN`**: Base de datos operativa con los datos de proyectos, facturas, etc.
- **`CAFCASYSTEM`**: Contiene diccionarios y tipos de códigos estándar del desarrollador.
- **`CLAESENSYSTEM`**: **Fuente de la Verdad**. Contiene las personalizaciones específicas para Claesen, incluyendo los nombres de estados que ven los usuarios.

## 2. El Mecanismo de Lookup

Los estados se gestionan mediante un sistema de "Codes":

- Se identificó el `code_type = 50` como el contenedor de **"ProjectStatus"**.
- La tabla maestra es `[CLAESENSYSTEM].[CAFCA].[code]`.

## 3. Mapeo Definitivo de Estados

Tras cruzar los datos de frecuencia en la tabla `project` con el diccionario de `CLAESENSYSTEM`, este es el mapa de estados:

| State ID | Etiqueta (Dutch)               | Significado Sugerido              | Frecuencia |
| :------- | :----------------------------- | :-------------------------------- | :--------- |
| **0**    | _(Legacy Default)_             | **Activo / General**              | Alta       |
| **1**    | 01 - Bestelbon te ontvangen    | Recepción de Pedido               | Baja       |
| **3**    | _(Eindfase)_                   | **Fase Final / Cierre**           | Media      |
| **6**    | _(Legacy Closed)_              | **Cerrado Histórico** (2008-2012) | Baja       |
| **9**    | 10 - Materialen in goedkeuring | Aprobación de Materiales          | Baja       |
| **10**   | 20 - Materialen in bestelling  | Compra en Proceso                 | Baja       |
| **11**   | 50 - Werken in Uitvoering      | **En Ejecución**                  | Media      |
| **12**   | 60 - Keuringen uit te voeren   | Inspecciones                      | Baja       |
| **13**   | 85 - Afrekening nakijken       | Revisión de Facturación           | Baja       |
| **14**   | 60 - Opmerkingen aan te passen | Ajustes/Revisiones                | Baja       |
| **15**   | 90 - Opgeleverd                | **Entregado / Finalizado**        | Muy Alta   |
| **16**   | 98 - On Hold                   | En Pausa                          | Baja       |
| **17**   | 60 - Verlichting te richten    | Trabajo en Sitio (Luces)          | Baja       |
| **18**   | 99 - Archief                   | **Archivado**                     | Alta       |
| **19**   | 99 - Cancelled                 | Cancelado                         | Baja       |
| **20**   | 80 - As Built af te leveren    | Documentación Final               | Baja       |
| **21**   | 95 - Lopende Huur/Leasing/LaaS | Contrato de Larga Duración        | Baja       |

## 4. Hallazgos Técnicos Adicionales

- **Procedimientos Almacenados**: Se analizaron `spProjectTotals` y `spProjectPrognosis`. Se confirmó que la tabla `state_project` es para **estados de facturación/certificaciones**, no para el estado vital del proyecto.
- **Flags de Seguridad**: Los campos `fl_active` y `fl_locked` actúan como filtros técnicos. El estado `15` y `18` casi siempre tienen `fl_active = 0`.

## 5. Próximos Pasos (Implementación)

Implementaremos una columna tipo "Badge" en Filament que use esta lógica:

1. **Verde (Success)**: Estados 0, 1, 9, 10, 11 (Proyectos en marcha).
2. **Gris (Secondary)**: Estados 15, 18 (Finalizados/Archivados).
3. **Naranja (Warning)**: Estado 16 (On Hold).
4. **Rojo (Danger)**: Estado 19 (Cancelled).

---

_Informe consolidado por Antigravity Lead Architect_
