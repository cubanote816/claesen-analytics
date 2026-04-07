<?php

namespace Modules\Cafca\Models;

use Modules\Core\Traits\ReadOnlyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'invoice';
    protected $primaryKey = 'id';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }
}
