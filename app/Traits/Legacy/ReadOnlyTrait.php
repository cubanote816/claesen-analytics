<?php

namespace App\Traits\Legacy;

use LogicException;

trait ReadOnlyTrait
{
    public function save(array $options = [])
    {
        throw new LogicException('This model is read-only. Writes are strictly forbidden.');
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new LogicException('This model is read-only. Writes are strictly forbidden.');
    }

    public function delete()
    {
        throw new LogicException('This model is read-only. Deletions are strictly forbidden.');
    }

    public static function create(array $attributes = [])
    {
        throw new LogicException('This model is read-only. Creations are strictly forbidden.');
    }
}
