<?php

/**
 * An icon that is a button: toolbar controls, row actions, a close X.
 *
 *   IconButton::make()->set('trash')->danger()->tooltip('Delete')
 *       ->confirm('Delete this row?')->onClick('AppHandler.delete()')
 *
 * Give it a `record` so the handler knows what it acts on — every scalar
 * property is written to the DOM and posted back, so $this->record is
 * populated on the server without any extra wiring:
 *
 *   IconButton::make()->set('edit')->with(['record' => $row['id']])
 *       ->onClick('AppHandler.edit()')
 */
class IconButton extends Component
{
    public $icon     = "";
    public $tooltip  = "";
    public $record   = "";
    public $disabled = "";

    protected string $template =
        '<button type="button" class="icon-btn tip" data-tooltip="{{$tooltip}}" {{$disabled}}>{{$icon}}</button>';

    public function set(string $icon, string $size = "sm"): static
    {
        $this->icon = Icon::make()->set($icon, $size);
        return $this;
    }

    public function tooltip(string $text): static
    {
        $this->tooltip = trans($text);
        return $this;
    }

    /** Red on hover — for destructive actions. */
    public function danger(): static { return $this->addClass('is-danger'); }

    public function small(): static  { return $this->addClass('icon-btn-sm'); }
    public function large(): static  { return $this->addClass('icon-btn-lg'); }

    public function disable(bool $disabled = true): static
    {
        $this->disabled = $disabled ? raw('disabled') : '';
        return $this;
    }

    public function confirm(string $question): static
    {
        return $this->with(['x-confirm' => trans($question)]);
    }
}
