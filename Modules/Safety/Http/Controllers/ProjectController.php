<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Performance\Models\Mirror\MirrorProject;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Intento 1: Usar el modelo de CAFCA (SQL Server) con timeout corto
            // Nota: El timeout se maneja por la configuración del driver ODBC, 
            // aquí capturamos la excepción si falla.
            $projects = \Modules\Cafca\Models\Project::where('fl_active', true)
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            // Fallback: Usar la tabla local "mirror" si SQL Server no está disponible
            // Esto es crucial para el entorno de desarrollo y la resiliencia de la PWA.
            $projects = MirrorProject::where('fl_active', true)
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get();
            
            // Si el mirror también está vacío, devolvemos una lista mínima para que la app no rompa
            if ($projects->isEmpty()) {
                $projects = collect([
                    ['id' => 'DEV-001', 'name' => 'Demo Project (Local Fallback)'],
                    ['id' => 'DEV-002', 'name' => 'Test Site (Local Fallback)'],
                ]);
            }
        }

        return response()->json([
            'data' => $projects
        ]);
    }
}
