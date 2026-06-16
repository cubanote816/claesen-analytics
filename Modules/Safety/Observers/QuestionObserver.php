<?php

namespace Modules\Safety\Observers;

use Modules\Safety\Models\Question;

class QuestionObserver
{
    public function creating(Question $question): void
    {
        if (auth()->check()) {
            $question->created_by_user_id = auth()->id();
        }
    }

    public function updating(Question $question): void
    {
        if (auth()->check()) {
            $question->updated_by_user_id = auth()->id();
        }
    }
}
