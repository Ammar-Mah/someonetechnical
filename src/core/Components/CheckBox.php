<?php

/**
 * A checkbox with its label.
 *
 *   CheckBox::make('done-'.$id)->set('Done', $row['done'])
 *       ->with(['record' => $id])->on('change', 'AppHandler.toggleDone()')
 *
 * On change the client sends the input's `value` attribute, not true/false —
 * read the checked state from $request->get('checked') instead, which the
 * client refreshes from the live element before every send.
 */
class CheckBox extends Component
{
    public $label    = "";
    public $name     = "";
    public $value    = "1";
    public $checked  = "";
    public $record   = "";
    public $disabled = "";

    protected string $template = '
        <label class="check">
            <input type="checkbox" name="{{$name}}" value="{{$value}}" {{$checked}} {{$disabled}}>
            <span>{{$label}}</span>
        </label>';

    public function set(string $label = "", bool $checked = false, string $value = "1"): static
    {
        $this->label   = trans($label);
        $this->value   = $value;
        $this->checked = $checked ? raw('checked') : '';
        return $this;
    }

    public function check(bool $checked = true): static
    {
        $this->checked = $checked ? raw('checked') : '';
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
        return $disabled ? $this->addClass('is-disabled') : $this;
    }
}
