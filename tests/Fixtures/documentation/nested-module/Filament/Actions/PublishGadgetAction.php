<?php

// Fixture for ModuleActionInventory (CLA-718): text only, never autoloaded.
class PublishGadgetAction
{
    public static function make(): Action
    {
        return Action::make('publish_now');
    }
}
