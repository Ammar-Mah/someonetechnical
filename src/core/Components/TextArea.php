<?php

/**
 * A multi-line input.
 *
 *   TextArea::make('note')->set($row['note'], 'Add a note…')->rows(6)
 *       ->on('blur', 'AppHandler.saveNote()')
 *
 * 'blur' is usually the right event: 'input' would post on every keystroke
 * (debounced, but still), and a textarea is rarely a search box.
 */
class TextArea extends Component
{
    public $value       = "";
    public $placeholder = "";
    public $name        = "";
    public $record      = "";
    public $rows        = "4";
    public $disabled    = "";

    protected string $template =
        '<textarea class="textarea" name="{{$name}}" rows="{{$rows}}"
                   placeholder="{{$placeholder}}" {{$disabled}}>{{$value}}</textarea>';

    public function set($value = "", string $placeholder = ""): static
    {
        $this->value = (string)$value;
        if ($placeholder !== "") $this->placeholder = trans($placeholder);
        return $this;
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function rows(int $rows): static
    {
        $this->rows = (string)max(1, $rows);
        return $this;
    }

    public function disable(bool $disabled = true): static
    {
        $this->disabled = $disabled ? raw('disabled') : '';
        return $this;
    }
}
