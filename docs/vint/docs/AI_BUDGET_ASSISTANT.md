Especificación Técnica: Asistente de Presupuestos con IA
Versión: 1.0 Fecha: 21 Enero 2026 Tecnología: Laravel 12, FilamentPHP 5, Livewire, Gemini 3 Flash, SQL Server (Legacy), MySQL (Insights).

1. Resumen Ejecutivo
   El Asistente de Presupuestos es una herramienta RAG (Retrieval-Augmented Generation) integrada en el panel administrativo. Su objetivo es asistir al Gerente en la creación de ofertas para licitaciones complejas (ej. "Estadio de Amberes").

Propuesta de Valor:
Cero Complacencia: Respuesta basada estrictamente en datos históricos (hechos), no en suposiciones.
Optimización Logística: Selección de personal basada en geolocalización real y sinergia de equipos.
Mitigación de Riesgos: Detección proactiva de desviaciones financieras en proyectos pasados similares.

2. Arquitectura de Datos (Estrategia Anti-Alucinación)
   Para evitar latencia y consumo excesivo de tokens, el sistema no consulta la base de datos legacy (CAFCA SQL Server) en tiempo real durante el chat. Se utiliza una arquitectura de Sincronización ETL (Extract, Transform, Load).

A. Flujo de Datos
Fuente de Verdad (Legacy): SQL Server (Esquema CAFCA). Contiene todos los datos crudos históricos.
Almacén de Conocimiento (Insights): MySQL Local. Tabla project_insights. Contiene datos procesados, limpios y enriquecidos listos para la IA.
Inferencia (Gemini): La IA recibe un JSON construido exclusivamente desde MySQL (project_insights y employees), garantizando respuestas rápidas y factuales.

3. Esquema de Base de Datos: project_insights (MySQL)
   Esta tabla es el cerebro del sistema. Se llena mediante un Job nocturno.

PHP
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
  Schema::create('project_insights', function (Blueprint $table) {
  $table->id();

  // 1. Identidad y Ubicación (Vital para filtrado geoespacial)
  $table->string('project_id')->unique()->index(); // ID original CAFCA (ej: '23-0045')
  $table->string('name');
  $table->string('city')->index(); // Ej: 'Antwerpen'
  $table->string('zipcode')->index(); // Ej: '2000'
  $table->string('category')->index()->nullable(); // 'Estadio', 'Nave Industrial', etc.

  // 2. Metadatos Temporales
  $table->date('date_finished')->index();
  $table->string('project_manager_ref')->nullable();

 
  // 3. Hard Metrics (La verdad financiera)
  // Calculado desde CAFCA: (Precio Venta - Coste Real) / Precio Venta
  $table->decimal('final_margin_percent', 5, 2);
  // Diferencia entre horas estimadas vs reales
  $table->decimal('budget_deviation_hours', 10, 2);

 
  // 4. RAG Context (Datos pre-procesados para la IA)
  // JSON con estructura: [{"name": "Jef", "zip": "2600", "role": "Lead", "efficiency_score": 98}]
  $table->json('team_composition')->nullable();

  // JSON con estructura: [{"supplier": "Rexel", "material": "KNX", "incident": "Late Delivery"}]
  $table->json('key_suppliers')->nullable();

  // Resumen narrativo generado al cerrar el proyecto
  $table->text('ai_summary_narrative')->nullable();

  $table->timestamps();

 
  // Índices compuestos para búsqueda rápida
  $table->index(['category', 'zipcode']);
  });
  }
};

4. Mapeo de Datos Legacy (CAFCA SQL Server)
   Para llenar la tabla anterior, se deben consultar las siguientes tablas del esquema CAFCA Full Dump:

Concepto de Negocio Tabla SQL Server Campos Clave Lógica de Extracción
Detalle Proyecto project id, name, project_address_seq_nr Cruzar con dossier para obtener dirección final.
Finanzas (Real) rpt_project_results profit_percent, hours_regie, costprice_labor Detectar si hours_regie > 10% del total (Alerta Roja).
Trabajadores employee id, name, zipcode, qualification_1 Usar zipcode para calcular cercanía al proyecto.
Histórico Equipos rpt_followup_labor project_id, employee_id, hours Agrupar por project_id para ver quiénes trabajaron juntos.
Proveedores purchase, supplier supplier_id, date_end Identificar proveedores principales por volumen de compra.

Lógica de Negocio: Sincronización (ETL)
Comando Artisan: php artisan cafca:sync-insights Frecuencia: Daily (Midnight)

Algoritmo:

Identificar: Seleccionar proyectos en SQL Server donde state = 'FINISHED' y date_end > 2023.
Calcular Sinergia: \* Para cada proyecto, extraer lista de empleados desde rpt_followup_labor.
Si el proyecto tuvo profit_percent > 15%, marcar a esa combinación de empleados como "High Efficiency Team". 3. Calcular Desviaciones:
Comparar sales_estimate.total_hours vs rpt_project_results.project_uren.
Guardar la diferencia en budget_deviation_hours. 4. Persistir: Guardar/Actualizar en MySQL project_insights.

6. Lógica de Inferencia (El "Brain" del Asistente)
   Cuando el usuario pregunta por un nuevo presupuesto, el sistema ejecuta estos pasos en el BudgetAssistantService:

Paso 1: Retrieval (Búsqueda)
PHP

// Pseudo-código Laravel
$location = "Antwerpen"; // Extraído del prompt del usuario
$type = "Stadium";

// A. Buscar Proyectos Similares (MySQL)
$history = ProjectInsight::where('category', $type)
    ->orWhere('name', 'LIKE', "%$type%")
  ->orderBy('date_finished', 'desc')
  ->take(3)->get();

// B. Buscar Trabajadores (Geolocalización)
// Lógica: Buscar empleados activos cuyo CP esté en un radio de 20km del proyecto
$workers = Employee::whereIn('zipcode', $nearbyZipCodes)->get();

Paso 2: Construcción del Contexto (JSON)
Se genera un JSON estricto para Gemini:

JSON

{
  "request": "Instalación luminarias Estadio Amberes",
  "historical_analysis": [
  {
  "project": "Gante Arena",
  "result": "Profit -5%",
  "cause": "Excessive Regie Hours (+150h)",
  "team": ["Jef", "Pieter"]
  }
  ],
  "available_local_talent": [
  { "name": "Jef", "distance": "5km", "skill": "Height Certified" },
  { "name": "Bart", "distance": "8km", "skill": "KNX Senior" }
  ],
  "constraints": {
  "weather_factor": "Winter (Add 15% contingency)",
  "site_condition": "New Construction"
  }
}
Paso 3: System Prompt (Instrucciones a Gemini)
"Eres un experto en estimación de costes para instalaciones eléctricas en Bélgica. Analiza el JSON adjunto.

Benchmarking: Advierte sobre los errores del proyecto 'Gante Arena'.
Logística: Sugiere la cuadrilla de 'Jef' y 'Bart' por su proximidad (ahorro de transporte) y su historial previo.
Materiales: Recomienda proveedores locales en Amberes para evitar costes de envío.
Tono: Factual, directo, tercera persona (como si fueses un equipo de consultores)."

7. Interfaz de Usuario (Filament)
   Página: admin/cafca/budget-assistant
   Componente: Livewire Chat Widget.
   Input: Campo de texto libre ("Tengo una obra en...").
   Output:
     Texto renderizado en Markdown.
     Tablas HTML para el desglose de horas/materiales.
     Alertas visuales (Cards de Filament) para Riesgos Detectados.
     Mapas (integración futura) mostrando la ubicación de los trabajadores sugeridos vs la obra.

8. Siguientes Pasos para Implementación
   Ejecutar Migración: Crear tabla project_insights en MySQL.
   Configurar Conexión BD: Asegurar que Laravel tiene acceso de lectura a SQL Server (sqlsrv) y escritura en MySQL.
   Desarrollar ETL Command: Codificar la lógica de importación desde CAFCA.
   Service Class: Implementar BudgetAssistantService con la lógica de filtrado geoespacial.
   Filament Page: Crear la interfaz de chat.
