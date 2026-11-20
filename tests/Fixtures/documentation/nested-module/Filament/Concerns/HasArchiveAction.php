<?php

// Fixture for ModuleActionInventory (CLA-718): text only, never autoloaded.
trait HasArchiveAction
{
    public function archiveAction(): mixed
    {
        return Action::make('archive');
    }
}
