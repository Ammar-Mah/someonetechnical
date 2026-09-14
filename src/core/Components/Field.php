<?php

/**
 * A labelled wrapper around any control.
 *
 *   Field::make()->label('Email')->hint('We never share it.')
 *       ->slot(TextInput::make('email')->type('email')->name('email'))
 *
 *   Field::make()->label('Status')->error($errors['status'] ?? '')
 *       ->slot(Select::make()->set($options, $current)->name('status'))
 *
 * slot() takes a component, a string, or an array of either — it is the
 * framework's own method, so this works in HTML notation too:
 *
 *   <x:Field label="Email"><x:TextInput name="email"/></x:Field>
 */
class Field extends Component
{
    public $label = "";
    public $hint  = "";
    public $error = "";

    protected string $template = '
        <div class="field">
            {{$label}}
            {{$slot}}
            {{$hint}}
            {{$error}}
        </div>';

    public function label(string $text): static
    {
        $this->label = $text === "" ? ""
            : raw('<span class="field-label">' . e(trans($text)) . '</span>');
        return $this;
    }

    public function hint(string $text): static
    {
        $this->hint = $text === "" ? ""
            : raw('<span class="field-hint">' . e(trans($text)) . '</span>');
        return $this;
    }

    /** An empty message renders nothing, so this is safe to call unconditionally. */
    public function error(string $message): static
    {
        $this->error = $message === "" ? ""
            : raw('<span class="field-error">' . e(trans($message)) . '</span>');
        return $this;
    }
}
