<?php

/**
 * A single-line input.
 *
 *   TextInput::make('search')->set('', 'Search…')->on('input', 'AppHandler.search()')
 *   TextInput::make('due')->type('date')->set($row['due_at'])->name('due_at')
 *   TextInput::make('qty')->type('number')->min(0)->max(99)->set('1')
 *
 * The client sends the live value as `value` on input, change and blur, so a
 * handler reads it with $request->get('value') and never has to query the DOM.
 * 'input' is debounced by 300 ms, which is what makes it usable for search.
 *
 * set() escapes, so database text is safe to pass.
 */
class TextInput extends Component
{
    public $value       = "";
    public $placeholder = "";
    public $type        = "text";
    public $name        = "";
    public $record      = "";
    public $disabled    = "";
    public $extra       = "";

    protected string $template =
        '<input class="input" type="{{$type}}" name="{{$name}}" value="{{$value}}"
                placeholder="{{$placeholder}}" {{$disabled}} {{$extra}}>';

    /**
     * @param mixed  $value       current value (escaped here)
     * @param string $placeholder run through trans()
     * @param string $record      id of the row this input edits, if any
     */
    public function set($value = "", string $placeholder = "", string $record = ""): static
    {
        $this->value = (string)$value;
        if ($placeholder !== "") $this->placeholder = trans($placeholder);
        if ($record !== "")      $this->record = $record;
        return $this;
    }

    /** text | password | email | number | date | time | search | url | tel */
    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /** The form field name — required if this input is inside a Form. */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function disable(bool $disabled = true): static
    {
        $this->disabled = $disabled ? raw('disabled') : '';
        return $this;
    }

    public function readonly(bool $on = true): static
    {
        return $on ? $this->attr('readonly') : $this;
    }

    public function required(bool $on = true): static
    {
        return $on ? $this->attr('required') : $this;
    }

    public function min($min): static  { return $this->attr('min="' . (int)$min . '"'); }
    public function max($max): static  { return $this->attr('max="' . (int)$max . '"'); }
    public function step($step): static { return $this->attr('step="' . htmlspecialchars((string)$step, ENT_QUOTES, 'UTF-8') . '"'); }

    /** Borderless until hovered — for edit-in-place cells. */
    public function plain(): static { return $this->addClass('input-plain'); }

    /** Mark the field as failing validation. */
    public function invalid(bool $on = true): static
    {
        return $on ? $this->addClass('is-invalid') : $this;
    }

    /** Append a raw attribute to the element. Used by the helpers above. */
    protected function attr(string $attribute): static
    {
        $this->extra = raw(trim((string)$this->extra . ' ' . $attribute));
        return $this;
    }
}
