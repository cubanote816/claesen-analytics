# Identificación visual de luminarias con IA — Guía de usuario

> Para: técnicos de campo (Claesen-Sport) y equipo de backoffice (panel de administración)
> Módulo: FieldOps → Armaturen / Catalogs

---

## ¿Qué es esto?

Al agregar una luminaria a un marco (frame) en la app de campo, en vez de navegar manualmente el catálogo (Grupo → Marca → Modelo) el técnico puede **sacar una foto** de la luminaria instalada y dejar que el sistema sugiera qué modelo es, comparándola contra:

1. El catálogo interno de Claesen (10 modelos ya cargados).
2. Si nada del catálogo interno coincide, el propio conocimiento de la IA (Claude Sonnet 5) sobre los modelos reales de las 4 marcas con las que trabaja Claesen: **Philips/Signify, Schréder, Thorn, Musco**.

**Lo que el sistema hace:** sugerir candidatos con un % de confianza y la evidencia visual concreta que los respalda.
**Lo que el técnico hace:** revisar la sugerencia y confirmarla — o descartarla y elegir manualmente, como siempre.

El sistema **nunca asigna un modelo automáticamente**. Ninguna luminaria se crea ni se modifica sin que el técnico confirme explícitamente.

---

## Cómo usarlo (técnico de campo)

1. Abrí el frame donde vas a agregar la luminaria → **"Armatuur toevoegen"**.
2. Arriba del selector manual vas a ver el botón **"Identificeren met foto"** (Identificar con foto).
3. Tocá el botón — se abre la cámara del dispositivo (o la galería, si preferís elegir una foto ya tomada).
4. Sacá/elegí la foto. El análisis tarda entre 10 y 20 segundos.

### Si el sistema encuentra una coincidencia

Vas a ver una o más tarjetas con:

```
[foto del producto]  BVP518
                      35% · Waarschijnlijk
                      • Two stacked rectangular LED modules...
                      • Trapezoidal cast-metal mounting bracket...
                                              [Gebruik dit]
```

- El **%** es qué tan seguro está el sistema — nunca vas a ver un modelo sugerido sin que también te diga por qué (la lista de evidencia visual).
- Si hay más de un candidato, es porque dos modelos se parecen mucho (por ejemplo, BVP518 y BVP528 son de la misma familia y solo difieren en tamaño) — el sistema es honesto sobre esa ambigüedad en vez de elegir uno al azar.
- Tocá **"Gebruik dit"** en el candidato correcto — se abre la misma tarjeta de confirmación que verías si lo hubieras elegido a mano (imagen, nombre, número de serie opcional, Guardar).

### Si el producto no está en nuestro catálogo pero el sistema reconoce la marca

Vas a ver una tarjeta de color **ámbar**, distinta a las demás:

```
✨  Schréder — OMNISTAR LED XL          [No verificado]
    No está en el catálogo interno — sugerencia de IA a confirmar · 40%
    • Distinctive trapezoidal housing...
                                    [Agregar al catálogo]
```

Esto significa: el sistema no tiene ese modelo exacto en la base de Claesen, pero reconoce que es (probablemente) un producto real de una de nuestras 4 marcas. Si confirmás que es correcto:

1. Tocá **"Agregar al catálogo"**.
2. El sistema crea el modelo nuevo en el catálogo al instante — **no tenés que esperar a que nadie lo apruebe**, podés seguir cargando la luminaria en el momento.
3. Ese modelo nuevo queda marcado como **"sin verificar"** para que backoffice lo revise más adelante (ver abajo) — pero ya está disponible para vos y para cualquier otro técnico que lo necesite después.

### Si el sistema no encuentra nada

Vas a ver el mensaje **"Geen zekere match gevonden — kies hieronder handmatig"** (No se encontró una coincidencia confiable). No es un error — el sistema prefiere decir "no sé" antes que inventar un modelo sin evidencia suficiente (por ejemplo, si la foto no muestra ninguna etiqueta o logo visible). En ese caso, simplemente usá el selector manual de siempre (Grupo → Marca → Modelo), que sigue funcionando exactamente igual.

---

## Para backoffice (`super_admin`): revisar entradas sin verificar

Cuando un técnico confirma una sugerencia fuera de catálogo, se crea un modelo (y si hace falta, también una marca/subgrupo) marcado como **"sin verificar"** — visible en:

**Panel de administración → Field Operations → Catalogs → Luminaire Types** (y **Luminaire Subgroups**, si se creó una marca nueva).

En la tabla vas a ver una columna con un badge:

| Badge | Qué significa |
|-------|----------------|
| **Sin verificar** (ámbar) | Creado por un técnico a partir de una sugerencia de IA, todavía nadie lo revisó |
| **Manual** / **AI suggestion** (gris) | Ya sea creado a mano o creado por IA y ya verificado |

Para cada entrada "Sin verificar" tenés una acción **"Marcar como verificado"** — usala una vez que confirmes que el nombre/marca están bien escritos (sin errores de tipeo, sin duplicar un modelo que ya existía con otro nombre). Si encontrás un duplicado o un error, corregilo con la edición normal del catálogo (o fusionalo a mano reasignando las luminarias afectadas) antes de marcarlo verificado.

Esto es una revisión de calidad, no una aprobación bloqueante — la entrada ya está en uso desde que el técnico la confirmó.

---

## Costo

Cada foto analizada es una llamada real a la API de Claude Sonnet 5 (Anthropic), facturada aparte de cualquier suscripción de Claude que ya tenga el equipo. Costo aproximado: **$0.01–0.02 por foto**. El crédito cargado en la cuenta de Anthropic vence 1 año después de la fecha de compra (no por consumo), así que no hay apuro en "usarlo antes de que se pierda" mes a mes.

---

## Limitaciones conocidas (probadas en la práctica, no teóricas)

- **El catálogo interno es chico a propósito (10 modelos).** Cuatro de esos diez son variantes muy parecidas entre sí de la misma familia Philips (BVP518/528/418/428) — el sistema casi nunca llega al 100% de confianza ("Geïdentificeerd") aunque la foto sea perfecta, porque honestamente no puede distinguir esas variantes solo por la forma. Esto es esperado, no un error.
- **La sugerencia "fuera de catálogo" (tarjeta ámbar) es conservadora.** En las pruebas reales de este feature, el sistema solo la ofrece cuando la foto muestra algo que realmente reconoce como de una marca específica — con fotos de catálogo genéricas (sin logo ni etiqueta visible), prefiere decir "no lo sé" antes que arriesgar un nombre de marca. En la práctica, para que aparezca esta tarjeta, la foto real del técnico probablemente necesita mostrar algún detalle distintivo del diseño de esa marca (no hace falta que se lea el logo, pero ayuda).
- **Nunca se auto-completa nada.** Tanto para un match de catálogo como para una sugerencia externa, el técnico siempre tiene que tocar un botón para confirmar — no hay ningún flujo automático que cree o edite una luminaria sin esa confirmación explícita.

---

## Preguntas frecuentes

**¿Puedo usar una foto que ya tenía guardada, en vez de sacar una nueva?**
Sí, el botón abre tanto la cámara como la galería del dispositivo (depende del navegador/sistema operativo).

**¿Qué pasa si me equivoco y confirmo el candidato incorrecto?**
Nada se guarda todavía en ese momento — la tarjeta de confirmación te deja cambiar el número de serie o cancelar antes de tocar "Guardar". Si ya guardaste la luminaria con el tipo equivocado, editala como a cualquier luminaria (el tipo se puede corregir desde la edición normal).

**¿Puedo seguir usando el selector manual si no quiero sacar una foto?**
Sí, siempre. El botón de identificar con foto es un atajo opcional — el selector Grupo → Marca → Modelo de siempre sigue ahí sin cambios.

**¿Qué pasa si dos técnicos confirman el mismo modelo nuevo (fuera de catálogo) por separado?**
El sistema detecta que la marca y el nombre del modelo ya existen (sin importar mayúsculas/minúsculas) y reutiliza la entrada existente en vez de crear un duplicado.
