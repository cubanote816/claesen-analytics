# Monthly Billing Control — Guía de usuario

> Para: equipo interno de gestión (Claesen Verlichting BV)
> Módulo: Intelligence Hub → Facturatiebeheer

---

## ¿Qué es esto?

El **Monthly Billing Control** es un panel de revisión mensual que detecta automáticamente anomalías de facturación y te ayuda a gestionarlas antes de cerrar el mes. El sistema analiza los proyectos activos, las facturas emitidas y los costes registrados, y genera una lista de alertas priorizadas para que nada se escape.

**Lo que el sistema hace:** detectar, recomendar y documentar.
**Lo que tú haces:** revisar cada alerta, confirmar o descartar, y cerrar el mes con confianza.

El sistema **nunca genera facturas ni modifica nada en CAFCA**. Toda acción sobre facturas se realiza en CAFCA como siempre.

---

## Cómo acceder

1. Inicia sesión en el panel de administración.
2. En la barra lateral izquierda, abre el grupo **Intelligence Hub**.
3. Haz clic en **Facturatiebeheer**.

La URL directa es: `http://[servidor]/billing-control`

---

## La pantalla principal

### Selector de período

En la parte superior encontrarás un selector de mes:

```
Periode: [Junio 2026 ▼]
```

Por defecto muestra el mes actual. Puedes navegar a meses anteriores para revisar o comparar. Al cambiar de período, todos los KPIs, pestañas y alertas se actualizan automáticamente.

---

### Banner de bloqueo mensual

Si aparece este banner en rojo en la parte superior:

> ⚠ **Maandafsluiting geblokkeerd** — er zijn nog kritieke of hoge facturatieafwijkingen onopgelost.

Significa que hay alertas críticas o altas pendientes de revisión para ese período. **No se recomienda cerrar el mes** hasta que hayas gestionado esas alertas (confirmado, descartado o resuelto cada una).

El banner desaparece automáticamente cuando no quedan alertas críticas o altas en estado "open" o "in review".

---

### Tarjetas KPI

Seis tarjetas resumen el estado actual del período:

| Tarjeta | Qué muestra |
|---------|-------------|
| **Totaal** | Número total de alertas generadas |
| **Open** | Alertas nuevas, aún sin revisar |
| **Review** | Alertas que alguien está revisando |
| **Bevestigd** | Alertas confirmadas (problema real, acción pendiente) |
| **Kritiek** | Alertas críticas aún activas (open + review) |
| **Hoog** | Alertas altas aún activas (open + review) |

---

### Las pestañas

Las alertas están organizadas en 6 pestañas. El número entre paréntesis indica cuántas alertas hay en cada grupo:

| Pestaña | Qué contiene |
|---------|-------------|
| **Alle** | Todas las alertas del período |
| **Facturatie** | Proyectos sin factura emitida + proyectos con larga brecha sin facturar |
| **Vorderingen** | Facturas vencidas e impagadas + facturas con pago parcial |
| **Kosten** | Costes registrados que aún no están marcados como facturados |
| **Creditnotas** | Notas de crédito emitidas en el período (solo visibilidad) |
| **System** | Alertas generadas automáticamente por el sistema |

---

## Los tipos de alerta

### Facturatie

#### Ontbrekende factuur (Alerta alta)
Un proyecto tiene costes registrados en el mes pero **no se ha emitido ninguna factura** para ese proyecto en ese período.

*Ejemplo:* El proyecto P20260031 tiene €8.400 en materiales y mano de obra en mayo, pero ninguna factura de mayo aparece en el sistema para ese proyecto.

*Qué hacer:* Verifica en CAFCA si corresponde emitir una factura (o una factura parcial). Si el proyecto es interno o no facturable, puedes descartar la alerta.

#### Factuurkloof (Alerta media)
Un proyecto activo con actividad económica lleva **más de 30 días sin ninguna factura emitida**.

*Qué hacer:* Revisar si el ciclo de facturación del proyecto está al día.

---

### Vorderingen

#### Vervallen vordering (Alerta crítica o alta)
Una factura ha **superado su fecha de vencimiento y sigue sin pagar**, con un saldo abierto superior a €500.

- **Crítica:** más de 60 días de retraso.
- **Alta:** hasta 60 días de retraso.

*Qué hacer:* Contactar al cliente para seguimiento del pago. Si ya se ha cobrado pero no está actualizado en CAFCA, verificar el registro.

#### Gedeeltelijke betaling (Alerta media)
Una factura tiene **pago parcial recibido** pero el saldo restante es superior a €500 y la fecha de vencimiento aún no ha llegado.

*Qué hacer:* Verificar si el cliente acordó un pago fraccionado o si hay un error de registro.

---

### Kosten

#### Niet-gefactureerde kost (Alerta media o alta)
Un proyecto tiene costes de seguimiento (`followup_cost`) en el período que **no están marcados como facturados** en CAFCA, y la suma total supera €500.

- **Media:** hasta €10.000 en costes sin facturar.
- **Alta:** más de €10.000 en costes sin facturar.

*Qué hacer:* Verificar en CAFCA si esos costes corresponden a una factura ya emitida (y solo falta marcarlos) o si hay una factura pendiente de crear.

#### Gesloten met saldo (Alerta alta)
Un proyecto marcado como **inactivo en CAFCA** tiene facturas sin cobrar con saldo abierto.

*Qué hacer:* Decidir si se reabre el proyecto, se gestiona el cobro de las facturas pendientes, o se registra como incobrable.

---

### Creditnotas

#### Creditnota (Alerta baja)
Una nota de crédito (CN%) fue emitida durante el período. Es **solo informativa** — no bloquea el cierre del mes ni requiere acción obligatoria.

*Qué hacer:* Verificar que la nota de crédito está correctamente justificada y documentada.

---

## Las columnas de la tabla

| Columna | Qué muestra |
|---------|-------------|
| **Type** | Tipo de alerta |
| **Project** | ID del proyecto o número de factura afectado |
| **Ernst** | Severidad: Kritiek / Hoog / Medium / Low |
| **Status** | Estado actual de la alerta |
| **Bedrag** | Importe relevante (saldo abierto o coste sin facturar) |
| **Aanbeveling** | Texto automático con la recomendación de acción |
| **Acties** | Botones para mover la alerta por el flujo de revisión |

---

## El flujo de revisión (workflow)

Cada alerta sigue este ciclo de vida:

```
OPEN → [Review] → IN REVIEW → [Bevestigen] → CONFIRMED → [Oplossen] → RESOLVED
                             → [Afwijzen]  → DISMISSED → [Oplossen] → RESOLVED
                                                        → [Heropenen]→ OPEN
```

### Estados explicados

| Estado | Significado |
|--------|------------|
| **Open** | Alerta nueva, detectada por el sistema, aún sin revisar |
| **In review** | Alguien está analizando esta alerta |
| **Confirmed** | Se confirma que es un problema real que requiere acción en CAFCA |
| **Dismissed** | Se descarta la alerta (falso positivo, ya gestionado, no aplica) |
| **Resolved** | La alerta está cerrada (problema resuelto o descarte documentado) |

### Las acciones disponibles

| Botón | Cuándo aparece | Qué hace |
|-------|---------------|---------|
| **Review** | Estado: Open | Marca la alerta como "en revisión" y registra quién la tomó |
| **Bevestigen** | Estado: In review | Confirma que es un problema real |
| **Afwijzen** | Estado: In review | Descarta la alerta (falso positivo) |
| **Oplossen** | Estado: Confirmed o Dismissed | Cierra definitivamente la alerta |
| **Heropenen** | Estado: Dismissed | Reabre la alerta si el descarte fue incorrecto |

---

## El botón "Guardian uitvoeren"

En la esquina superior derecha encontrarás el botón **Guardian uitvoeren** (Ejecutar Guardian).

Al pulsarlo, el sistema analiza el período seleccionado en ese momento y:
- Genera nuevas alertas que no existían.
- Actualiza las alertas ya existentes (en estado "open" o "in review") con los datos más recientes.
- **No modifica** alertas que ya estaban confirmadas, descartadas o resueltas.

**Cuándo usarlo:**
- Al inicio de cada mes, para obtener el análisis completo del mes anterior.
- Cuando quieras refrescar los datos de un período después de haber registrado cambios en CAFCA.

El Guardian también se ejecuta automáticamente el día 2 de cada mes a las 7:00.

---

## Flujo de trabajo mensual recomendado

### Semana 1 del mes (revisión del mes anterior)

1. Seleccionar el mes anterior en el selector de período.
2. Si el Guardian no se ha ejecutado aún, pulsar **Guardian uitvoeren**.
3. Revisar el banner: ¿hay bloqueo de cierre mensual?
4. Empezar por la pestaña **Vorderingen** → gestionar las facturas vencidas (críticas primero).
5. Seguir con **Facturatie** → verificar proyectos sin factura.
6. Revisar **Kosten** → confirmar o descartar costes sin facturar.
7. Revisar brevemente **Creditnotas** (solo verificación).
8. Marcar todas las alertas revisadas como Confirmed o Dismissed.
9. Resolver las confirmadas una vez que la acción esté tomada en CAFCA.
10. Cuando no queden alertas Críticas o Altas en Open/In Review, el banner desaparece → mes listo para cierre.

---

## Preguntas frecuentes

**¿Por qué aparece una alerta de proyecto sin factura si ya facturé ese proyecto?**
El sistema busca facturas emitidas *en el mismo mes* que el análisis. Si la factura tiene fecha de otro mes, no la ve como factura de ese período. Puedes descartar la alerta con una nota.

**¿El sistema modifica algo en CAFCA?**
No. El sistema es de solo lectura. Toda acción (emitir facturas, marcar costes como facturados) se hace en CAFCA como siempre.

**¿La misma factura vencida aparecerá el mes que viene también?**
Sí, si sigue impagada. El sistema registra la situación de cada mes de forma independiente. Esto es intencional: una deuda que persiste debe seguir visible.

**¿Puedo descartar una alerta sin revisarla?**
No directamente. Primero debes pulsar "Review" (pasa a "In Review") y luego "Afwijzen". Este flujo garantiza que alguien ha visto la alerta antes de descartarla.

**¿Qué pasa si descarto algo por error?**
Puedes reabrirla con el botón **Heropenen** (solo disponible en estado Dismissed).
