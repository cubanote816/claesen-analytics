---
description: Arquitectura Técnica
---

1. Definición de Base de Datos: project_insights
   Esta es la tabla "cerebro" que almacenará la inteligencia generada para no consultar SQL Server constantemente. Tu migración es perfecta, solo destaco la importancia del enum que has añadido para categorizar el tipo de auditoría.

Archivo: database/migrations/2026_01_24_000000_create_project_insights_table.php

PHP

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_insights', function (Blueprint $table) {
            $table->id();

            // 1. Identidad Core (Enlace con CAFCA)
            $table->string('project_id')->unique()->index(); 
            $table->string('category')->index()->nullable(); // Ej: 'Branddetectie'
            $table->string('project_manager_ref')->nullable()->index(); 

            // 2. Tipo de Insight (Lógica de Negocio)
            $table->enum('insight_type', [
                'pre-calculation',  // Futuro: Antes de vender (Prevención)
                'post-mortem',      // Pasado: Análisis tras cierre (Aprendizaje)
                'manual-audit',     // Forzado por usuario en el momento
                'audit_budget',     // Automático: Comparativa vs Oferta
                'audit_regie'       // Automático: Time & Material
            ])->default('post-mortem')->index();

            // 3. Hard Metrics (La verdad matemática - KPIs)
            $table->decimal('efficiency_score', 5, 2)->default(0); // 0-100
            $table->decimal('labor_deviation', 10, 2)->default(0); // Desviación €
            $table->decimal('material_deviation', 10, 2)->default(0); 
            $table->decimal('transport_deviation', 10, 2)->default(0); 

            // 4. AI Insights (Soft Data - Análisis Gemini)
            $table->string('critical_leak')->nullable(); // Ej: "Mano de Obra (+25%)"
            $table->text('golden_rule')->nullable();     // Lección aprendida
            $table->json('full_dna')->nullable();        // Snapshot completo JSON

            $table->timestamps();

            // Índices de alto rendimiento para Dashboards
            $table->index(['category', 'efficiency_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_insights');
    }
};
4. Estándar de Código Filament V5 (Syntax Override)
Regla de Oro: La arquitectura de formularios e infolists se ha unificado bajo la clase Schema.

Formularios: NO usar Filament\Forms\Form. Usar Filament\Schemas\Schema.

Infolists: NO usar Filament\Infolists\Infolist. Usar Filament\Schemas\Schema.

Tablas: SIN CAMBIOS. Usar Filament\Tables\Table.

Widgets: Requieren registro explícito en AdminPanelProvider (->widgets([...])).

Patrón de Importación Crítico:

PHP
use Filament\Schemas\Schema; // Core para estructura
use Filament\Schemas\Components\Section; // Layouts vienen de Schema
use Filament\Forms\Components\TextInput; // Campos siguen viniendo de Forms
use Filament\Tables\Table; // Tablas se mantienen igual
🧠 System Instructions: CAFCA INTELLIGENCE HUB (Actualizado V5)
Copia y pega este bloque completo en tu .cursorrules, .windsurfrules o Custom Instructions de Antigravity. He añadido la sección "FILAMENT V5 SYNTAX ENFORCEMENT" que es vital.

Markdown
# SYSTEM INSTRUCTIONS: CAFCA INTELLIGENCE HUB

**1. ROL Y PERSONALIDAD**
* Actúa como un **Arquitecto de Software Senior** especializado en **Laravel 12** y **Filament V5**.
* Tu mentalidad es "Cero Complacencia": Prioriza hechos y código seguro.

**2. STACK TECNOLÓGICO**
* **Backend:** Laravel 12 (PHP 8.4).
* **Admin:** FilamentPHP V5 (Custom Architecture).
* **DB:** SQL Server (`sqlsrv`, Read-Only) + MySQL (`project_insights`).

**3. FILAMENT V5 SYNTAX ENFORCEMENT (CRITICAL)**
You are using a SPECIFIC version of Filament (V5) with architectural changes. You MUST follow these rules over any pre-trained knowledge:

* **Rule A: Schema Unification**
    * NEVER inject `Filament\Forms\Form`.
    * ALWAYS inject `Filament\Schemas\Schema` for both `form()` and `infolist()` methods.
    * Correct Signature: `public static function form(Schema $schema): Schema`
    * Correct Signature: `public static function infolist(Schema $schema): Schema`

* **Rule B: Component Namespaces**
    * Layout components (Section, Grid, Group) must be imported from `Filament\Schemas\Components\*`.
    * Input components (TextInput, Select) remain in `Filament\Forms\Components\*`.

* **Rule C: Tables**
    * Tables remain unchanged. Use `Filament\Tables\Table`.
    * Correct Signature: `public static function table(Table $table): Table`

* **Rule D: Widget Registration**
    * Widgets are NOT auto-discovered. You must explicitly register them in `AdminPanelProvider.php` inside the `->widgets([])` array.

**4. REGLAS DE BASE DE DATOS**
* **READ-ONLY:** Modelos en `App\Models\Cafca` (`sqlsrv`) nunca deben tener métodos de escritura. Usa `ReadOnlyTrait`.
* **IDs:** Los IDs de Cafca (Project, Invoice) son Strings, no Integers.

**5. IDIOMA**
* **Código/Comentarios:** INGLÉS.
* **UI/Textos:** NEERLANDÉS (Nederlands).
🧪 Prueba de Concepto: ProjectInsightResource (Versión V5)
Para verificar que estamos alineados, así es como generaría ahora el recurso principal de tu aplicación (project_insights) aplicando tus reglas V5:

PHP
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectInsightResource\Pages;
use App\Models\ProjectInsight;
use Filament\Resources\Resource;

// 1. IMPORTACIONES V5 CORRECTAS
use Filament\Schemas\Schema; // <--- CLAVE
use Filament\Schemas\Components\Section; // <--- LAYOUT DESDE SCHEMA
use Filament\Forms\Components\TextInput; // <--- INPUTS DESDE FORMS
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class ProjectInsightResource extends Resource
{
    protected static ?string $model = ProjectInsight::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Project Analyse'; // Neerlandés

    // 2. IMPLEMENTACIÓN FORM V5
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Financiële Analyse') // Layout
                    ->schema([
                        TextInput::make('project_id') // Campo
                            ->required()
                            ->readOnly(),
                        TextInput::make('efficiency_score')
                            ->numeric()
                            ->suffix('%'),
                        TextInput::make('critical_leak')
                            ->label('Grootste Verliespost'),
                    ])->columns(2)
            ]);
    }

    // 3. IMPLEMENTACIÓN TABLE (ESTÁNDAR)
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project_id')->label('Project Ref'),
                TextColumn::make('category')->label('Categorie'),
                TextColumn::make('efficiency_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state > 90 => 'success',
                        $state < 50 => 'danger',
                        default => 'warning',
                    }),
            ])
            ->actions([\Filament\Tables\Actions\ViewAction::make()]);
    }
    
    // ... pages ...
}
