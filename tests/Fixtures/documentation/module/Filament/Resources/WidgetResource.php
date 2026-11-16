<?php

// Fixture for ModuleActionInventory (CLA-693): text only, never autoloaded.
return [
    ViewAction::make(),
    Action::make('publish')->label('Publish'),
    \Filament\Actions\DeleteBulkAction::make(),
    SchemaActions::make([]),
    Action::make(),
];
