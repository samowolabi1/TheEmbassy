<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['quiz_id', 'question', 'options', 'answer', 'reference', 'position'])]
#[Hidden(['answer'])]
class QuizQuestion extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['options' => 'array', 'answer' => 'integer'];
    }
}
