<?php

/**
 * A native single-choice dropdown.
 *
 *   Select::make('status')->set(['open' => 'Open', 'done' => 'Done'], 'open')
 *       ->name('status')->on('change', 'AppHandler.setStatus()')
 *
 * Native because it is the right control for one choice from a short list: it
 * is keyboard accessible, and on a phone it opens the platform picker. Use
 * Picker when you need multiple choice, search, or rich option markup.
 */
class Select extends Component
{
    public $options     = "";
    public $name        = "";
    public $record      = "";
    public $disabled    = "";

    protected string $template =
        '<select class="select" name="{{$name}}" {{$disabled}}>{{$options}}</select>';

    /**
     * @param array  $items    [value => label]
     * @param mixed  $selected currently selected value
     * @param string $empty    label for a leading blank option ('' for none)
     */
    public function set(array $items, $selected = null, string $empty = ""): static
    {
        $html = "";

        if ($empty !== "") {
            $html .= '<option value="">'
                   . htmlspecialchars(trans($empty), ENT_QUOTES, 'UTF-8')
                   . '</option>';
        }

        foreach ($items as $value => $label) {
            $isSelected = ((string)$value === (string)$selected) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>'
                   . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8')
                   . '</option>';
        }

        $this->options = raw($html);
        return $this;
    }

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
}
