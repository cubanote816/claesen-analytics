<?php

// Fixture for ModuleActionInventory (CLA-718): text only, never autoloaded.
trait HasOrphanAction
{
    public function orphanAction(): mixed
    {
        return Action::make('orphan');
    }
}
