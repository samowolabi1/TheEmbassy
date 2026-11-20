<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * A question the admin adds to a programme's form.
 */
#[Fillable(['label', 'help', 'type', 'options', 'is_required', 'position'])]
class EmpowermentQuestion extends Model
{
    public const TYPES = [
        'text' => 'Short answer',
        'textarea' => 'Paragraph',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown (pick one)',
        'radio' => 'Multiple choice (pick one)',
        'checkboxes' => 'Checkboxes (pick any)',
        'yes_no' => 'Yes or no',
    ];

    /** Types that need a list of options. */
    public const WITH_OPTIONS = ['select', 'radio', 'checkboxes'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean'];
    }

    public function key(): string
    {
        return 'q'.$this->id;
    }

    /**
     * @return list<string>
     */
    public function optionList(): array
    {
        return array_values(array_filter(array_map('trim', (array) $this->options)));
    }

    /**
     * Validation rules for its answer.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->is_required ? 'required' : 'nullable';
        $field = 'answers.'.$this->key();

        return match ($this->type) {
            'textarea' => [$field => [$required, 'string', 'max:3000']],
            'number' => [$field => [$required, 'numeric', 'min:0', 'max:1000000000']],
            'date' => [$field => [$required, 'date']],
            'select', 'radio' => [$field => [$required, Rule::in($this->optionList())]],
            'checkboxes' => [$field => [$this->is_required ? 'required' : 'nullable', 'array'], $field.'.*' => [Rule::in($this->optionList())]],
            'yes_no' => [$field => [$required, Rule::in(['Yes', 'No'])]],
            default => [$field => [$required, 'string', 'max:255']],
        };
    }

    /**
     * An answer as text, for lists and exports.
     */
    public function answerText(mixed $answer): string
    {
        return is_array($answer) ? implode(', ', $answer) : (string) ($answer ?? '');
    }
}
