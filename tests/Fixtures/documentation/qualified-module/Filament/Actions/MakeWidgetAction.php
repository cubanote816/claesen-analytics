<?php
// Fixture for ModuleActionInventory (CLA-718 review): text only, never autoloaded.
class MakeWidgetAction
{
    public static function make(): Action { return CreateAction::make(); }

    public static function preview(): Action { return Action::make('preview'); }
}
