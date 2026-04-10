<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

function inspectTable($tableName) {
    try {
        $columns = DB::connection('sqlsrv')->select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
        ", [$tableName]);
        
        echo "\n--- Schema for: $tableName ---\n";
        foreach ($columns as $col) {
            echo "{$col->COLUMN_NAME} ({$col->DATA_TYPE})" . ($col->IS_NULLABLE === 'YES' ? ' NULL' : ' NOT NULL') . "\n";
        }
    } catch (\Exception $e) {
        echo "Error inspecting $tableName: " . $e->getMessage() . "\n";
    }
}

inspectTable('invoice');
inspectTable('followup_cost');
inspectTable('followup_labor_analytical');
inspectTable('project');
