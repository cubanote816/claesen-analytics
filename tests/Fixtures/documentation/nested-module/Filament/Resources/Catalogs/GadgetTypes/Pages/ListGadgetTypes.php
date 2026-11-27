<?php

// Fixture for ModuleActionInventory (CLA-718): text only, never autoloaded.
class ListGadgetTypes
{
    use HasArchiveAction;

    public function actions(): array
    {
        return [CreateAction::make()];
    }
}
