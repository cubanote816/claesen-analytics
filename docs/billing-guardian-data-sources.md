# Monthly Billing Guardian — Origen de los datos

> Para: Gerencia / Dirección (Claesen Verlichting BV)
> Propósito: explicar de dónde provienen los resultados del sistema de detección de anomalías de facturación

---

## Resumen ejecutivo

El **Monthly Billing Guardian** no inventa datos ni hace estimaciones. Analiza exclusivamente la información que ya existe en el sistema ERP CAFCA (SQL Server), copiada cada noche a una base de datos local de análisis. Los resultados reflejan el estado real de proyectos, costes y facturas tal como están registrados en CAFCA.

El sistema **no modifica nada en CAFCA**. Es un auditor de solo lectura.

---

## El recorrido del dato: de CAFCA a la pantalla

```
CAFCA ERP
(SQL Server — servidor 192.168.254.102)
        │
        │  Sincronización automática
        │  cada noche a las 04:00
        │  (solo lectura del ERP)
        ▼
Base de datos local
(MySQL — servidor del panel)
        │
        │  El Guardian analiza
        │  y detecta anomalías
        ▼
Alertas de facturación
(pantalla Monthly Billing Control)
```

### ¿Qué se sincroniza cada noche?

El proceso nocturno copia las siguientes tablas de CAFCA al sistema local:

| Datos en CAFCA | Tabla local | Contenido |
|----------------|-------------|-----------|
| `project` | `intelligence_mirror_projects` | Proyectos: ID, nombre, estado, precio contrato, cliente |
| `followup_cost` | `intelligence_mirror_costs` | Costes por proyecto: tipo, precio unitario, cantidad, fecha, si está facturado |
| `invoice` | `intelligence_mirror_invoices` | Facturas: número, proyecto, importe total, importe cobrado, fecha vencimiento, estado pago |
| `relation` | `intelligence_mirror_relations` | Clientes: ID, nombre |
| `estimate` | `intelligence_mirror_estimate_calc` | Presupuestos vinculados a proyectos |
| `workdoc` | `intelligence_mirror_workdocs` | Documentos de trabajo por proyecto |

**Profundidad histórica:** el sistema mantiene todos los proyectos activos de los últimos 6 meses, más todas las facturas con saldo pendiente de pago (independientemente de su antigüedad — hay facturas impagadas desde 2009 que siguen visibles).

---

## De dónde sale cada tipo de alerta

### 1. "Ontbrekende factuur" — Proyecto sin factura

**Pregunta que responde:** *¿Hay proyectos donde se trabajó este mes pero no se emitió ninguna factura?*

**Datos que usa:**

- **`followup_cost`** → suma de `cost_price × quantity` por proyecto, filtrado por fecha dentro del mes analizado y donde `already_invoiced = false` en CAFCA (campo que indica si el coste ya fue incluido en una factura).
- **`invoice`** → busca si existe alguna factura (que no sea nota de crédito) con fecha dentro del mes para ese proyecto.

**Lógica de detección:**

```
SI el proyecto tiene costes en el mes > €500
Y NO tiene ninguna factura emitida en ese mismo mes
Y el proyecto tiene precio de contrato o presupuesto vinculado
→ ALERTA
```

**El umbral de €500** evita alertas por proyectos con actividad mínima (ajustes menores, trabajos internos). Es configurable desde BI Configuratie.

**Proyectos internos:** si un proyecto no tiene precio de contrato ni presupuesto vinculado en CAFCA, el sistema lo considera no facturable y no genera alerta (salvo que se active la opción en configuración).

---

### 2. "Vervallen vordering" — Factura vencida

**Pregunta que responde:** *¿Hay facturas cuya fecha de vencimiento ya pasó y el cliente no ha pagado?*

**Datos que usa:**

- **`invoice.fl_paid`** → indicador booleano de CAFCA: si es `false`, la factura no está completamente pagada.
- **`invoice.date_expiration`** → fecha de vencimiento de la factura según lo acordado con el cliente.
- **`invoice.total_price`** → importe total de la factura.
- **`invoice.total_paid`** → importe recibido hasta la fecha.

**Lógica de detección:**

```
SI la factura tiene fl_paid = false (no está pagada en CAFCA)
Y la fecha de vencimiento ya pasó (anterior a hoy)
Y el saldo abierto (total_price − total_paid) > €500
→ ALERTA

Severidad:
  - Más de 60 días de retraso → CRÍTICA
  - Hasta 60 días → ALTA
```

**Importante:** el campo `fl_paid` es el que gestiona el equipo financiero en CAFCA. Cuando se registra un pago completo en CAFCA, este campo pasa a `true` y la alerta desaparece en el siguiente análisis.

**Ejemplo real (datos junio 2026):**
- Factura F25260007 — TC Tenkie — €65.867,48 — 286 días vencida → **CRÍTICA**
- Factura F25260201 — Happy Waregem — €33.903,52 — 12 días vencida → **ALTA**

---

### 3. "Gedeeltelijke betaling" — Pago parcial

**Pregunta que responde:** *¿Hay facturas donde el cliente pagó algo pero no el total, y el plazo aún no venció?*

**Datos que usa:** los mismos campos de `invoice` que la alerta anterior.

**Lógica de detección:**

```
SI la factura tiene fl_paid = false
Y se ha recibido algún pago (total_paid > 0)
Y el saldo restante > €500
Y la fecha de vencimiento NO ha llegado aún (o no tiene fecha)
→ ALERTA MEDIA
```

**La distinción con "factura vencida" es intencional:** una factura parcialmente pagada dentro de plazo es un seguimiento; la misma factura después del vencimiento pasa a ser una deuda. El sistema nunca genera las dos alertas para la misma factura.

---

### 4. "Niet-gefactureerde kost" — Costes no facturados

**Pregunta que responde:** *¿Hay costes de seguimiento de proyectos que no se han incluido en ninguna factura?*

**Datos que usa:**

- **`followup_cost.already_invoiced`** → campo en CAFCA que el equipo marca en `true` cuando el coste ha sido incluido en una factura emitida.
- **`followup_cost.cost_price`** y **`followup_cost.quantity`** → para calcular el importe del coste.
- **`followup_cost.date`** → para filtrar costes del período analizado.

**Lógica de detección:**

```
Por cada proyecto:
  Sumar todos los costes del mes donde already_invoiced = false en CAFCA

SI el total > €500
→ ALERTA (Media si ≤ €10.000, Alta si > €10.000)
```

**¿Qué es `already_invoiced`?** Es un campo que gestiona el equipo en CAFCA. Cuando se emite una factura que incluye determinados costes, se marcan como `already_invoiced = true` en la línea de coste. Si un coste nunca se marca, el Guardian lo detecta como potencialmente no facturado.

**Nota importante:** el campo es `already_invoiced` en `followup_cost`, no el campo `invoice` de la misma tabla (que es un indicador de tipo de coste, no de estado de facturación).

---

### 5. "Factuurkloof" — Brecha de facturación

**Pregunta que responde:** *¿Hay proyectos activos con actividad reciente que llevan demasiados días sin facturar?*

**Datos que usa:**
- **`followup_cost`** → para confirmar que hubo actividad en el período analizado.
- **`invoice`** → para encontrar la fecha de la última factura emitida para ese proyecto.
- **`project.fl_active`** → solo se revisan proyectos activos.

**Lógica de detección:**

```
SI el proyecto está activo en CAFCA (fl_active = true)
Y tuvo costes en el período analizado
Y la última factura emitida tiene más de 30 días de antigüedad
  (o nunca se ha emitido una factura para este proyecto)
→ ALERTA MEDIA
```

El umbral de 30 días es configurable desde BI Configuratie.

---

### 6. "Creditnota" — Nota de crédito

**Pregunta que responde:** *¿Se emitieron notas de crédito este mes?*

**Datos que usa:**
- **`invoice`** → facturas cuyo número empieza por `CN` (convención de CAFCA para notas de crédito).

**Lógica:** simplemente recoge todas las notas de crédito emitidas en el período. Es una alerta informativa — no indica problema, solo asegura visibilidad gerencial sobre los créditos emitidos.

---

### 7. "Gesloten met saldo" — Proyecto cerrado con saldo

**Pregunta que responde:** *¿Hay proyectos que cerramos en CAFCA pero que todavía tienen facturas sin cobrar?*

**Datos que usa:**
- **`project.fl_active`** → `false` indica que el proyecto está inactivo/cerrado en CAFCA.
- **`invoice`** → facturas de ese proyecto con `fl_paid = false` y saldo abierto > €500.

**Lógica:**

```
SI el proyecto tiene fl_active = false en CAFCA (proyecto cerrado)
Y tiene al menos una factura no pagada con saldo > €500
→ ALERTA ALTA
```

Esta situación es siempre anómala: un proyecto no debería cerrarse con deuda pendiente de cobro.

---

## Cuándo se actualizan los datos

| Evento | Cuándo ocurre |
|--------|--------------|
| Sincronización automática | Cada noche a las 04:00 |
| Ejecución automática del Guardian | Día 2 de cada mes a las 07:00 (analiza el mes anterior) |
| Ejecución manual (botón en pantalla) | En cualquier momento, a petición |

**Regla práctica:** si se registra un pago en CAFCA hoy, la alerta correspondiente desaparecerá en el análisis de mañana (después de la sincronización nocturna). Para verlo reflejado inmediatamente, se puede ejecutar el Guardian manualmente después de forzar una sincronización.

---

## Fiabilidad de los datos

### Lo que garantiza el sistema

- **Fuente única:** todos los datos provienen directamente de CAFCA. No hay datos introducidos manualmente en el panel.
- **Solo lectura:** el sistema nunca modifica CAFCA. Cualquier discrepancia que detectes existe en CAFCA — el panel solo la hace visible.
- **Trazabilidad:** cada alerta incluye el ID exacto del proyecto o factura en CAFCA para verificación directa.
- **Histórico:** las alertas que se gestionan quedan registradas con quién las revisó y cuándo.

### Limitaciones a tener en cuenta

- **Los datos son del día anterior.** La sincronización ocurre a las 04:00. Si se registra un pago a las 14:00, no aparecerá hasta el día siguiente.
- **`already_invoiced` depende de la disciplina del equipo.** Si un coste se incluye en una factura pero no se marca como tal en CAFCA, el Guardian lo seguirá señalando. La solución es mantener el campo actualizado en CAFCA.
- **`fl_paid` es el indicador de pago.** Si CAFCA muestra una factura como pagada pero el Guardian la sigue alertando, es porque el campo `fl_paid` no está actualizado en CAFCA.

---

## Correspondencia CAFCA ↔ datos en pantalla

| Lo que ves en la alerta | De dónde viene en CAFCA |
|------------------------|------------------------|
| Número de proyecto (P20260031) | `project.project_id` |
| Número de factura (F25260007) | `invoice.invoice_id` |
| Importe de la alerta en **Facturatie** | Suma de `followup_cost.cost_price × quantity` del período |
| Importe de la alerta en **Vorderingen** | `invoice.total_price − invoice.total_paid` |
| Nombre del cliente | `relation.name` (del cliente vinculado al proyecto) |
| Estado de pago | `invoice.fl_paid` |
| Fecha de vencimiento | `invoice.date_expiration` |
| Costes no facturados | `followup_cost` donde `already_invoiced = false` |
| Proyecto cerrado | `project.fl_active = false` |

---

## ¿Por qué confiar en estos números?

Los mismos datos que el Guardian analiza son los que gestiona el equipo de Claesen en CAFCA a diario. El sistema no interpreta ni transforma los valores — los lee directamente y aplica umbrales definidos por el equipo:

- **€500** como mínimo para evitar ruido en alertas de facturas y costes (ajustable).
- **30 días** como umbral de brecha de facturación (ajustable).
- **60 días** como frontera entre alerta Alta y Crítica en facturas vencidas.

Si los números de CAFCA son correctos, los resultados del Guardian son correctos. Si hay una discrepancia, la causa está en CAFCA — el panel simplemente la hace visible antes de que se convierta en un problema mayor.
