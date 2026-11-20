<?php
// Fixture for ModuleActionInventory (CLA-718 review): text only, never autoloaded.
namespace Fx\Archive;
trait HasButtons { public function a(): mixed { return Action::make('archive'); } }
