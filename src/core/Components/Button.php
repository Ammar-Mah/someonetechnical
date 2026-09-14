<?php

/**
 * A button.
 *
 * Presentation only — a button does not know what it does. Wire it to a
 * handler with ->onClick(), which is the framework's own method:
 *
 *   Button::make()->set('Save', 'save')->primary()->onClick('AppHandler.save()')
 *   Button::make()->set('Delete', 'trash')->danger()
 *       ->confirm('Delete this item?')->onClick('AppHandler.delete()')
 *   Button::make()->set('Cancel')->ghost()->onClick('$remove(#my-modal)')
 *
 * The last one is a surface handler: the $ prefix means the client executes it
 * locally and never calls the server.
 */
class Button extends Component
{
    public $label    = "";
    public $icon     = "";
    public $type     = "button";
    public $disabled = "";
    public $extra    = "";

    protected string $template =
        '<button type="{{$type}}" class="btn" {{$disabled}} {{$extra}}>{{$icon}}{{$label}}</button>';

    /**
     * @param string $label shown on the button; run through trans() and escaped
     * @param string $icon  optional Icon name
     */
    public function set(string $label = "", string $icon = ""): static
    {
        // e() inside raw(): the wrapper is markup this method built, the label
        // inside it is data. That distinction is the whole point of raw().
        $this->label = $label === "" ? "" : raw('<span>' . e(trans($label)) . '</span>');
        $this->icon  = $icon === "" ? "" : Icon::make()->set($icon, 'sm');
        return $this;
    }

    public function primary(): static { return $this->addClass('btn-primary'); }
    public function danger(): static  { return $this->addClass('btn-danger'); }
    public function ghost(): static   { return $this->addClass('btn-ghost'); }
    public function link(): static    { return $this->addClass('btn-link'); }
    public function small(): static   { return $this->addClass('btn-sm'); }
    public function large(): static   { return $this->addClass('btn-lg'); }

    /** Full width. */
    public function block(): static   { return $this->addClass('btn-block'); }

    /** 'button' (default) | 'submit' | 'reset' */
    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Submit a form this button is not inside.
     *
     * A dialog usually puts its buttons in a footer, outside the <form> — the
     * HTML `form` attribute is what connects the two, and it triggers the same
     * submit event the framework already listens for.
     */
    public function form(string $formId): static
    {
        $this->extra = raw(trim((string)$this->extra . ' form="' . e($formId) . '"'));
        return $this->type('submit');
    }

    public function disable(bool $disabled = true): static
    {
        $this->disabled = $disabled ? raw('disabled') : '';
        return $this;
    }

    /**
     * Ask before running the click handler.
     *
     * The client shows its own modal and only dispatches to the server if the
     * person says yes, so the handler needs no confirmation logic of its own.
     */
    public function confirm(string $question): static
    {
        return $this->with(['x-confirm' => trans($question)]);
    }

    public function tooltip(string $text): static
    {
        return $this->addClass('tip')->with(['data-tooltip' => trans($text)]);
    }
}
