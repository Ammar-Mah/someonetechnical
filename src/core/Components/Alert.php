<?php

/**
 * A coloured message block.
 *
 *   Alert::make()->success('Saved.')
 *   Alert::make()->danger('That name is already taken.')
 *   Alert::make('hint')->info('Pick a project to get started.')
 *
 * For a message that should appear briefly and disappear, use a toast instead:
 *   Event::make()->call('toast', ['Saved', 'success'])->send();
 */
class Alert extends Component
{
    public $message = "";
    public $icon    = "";
    public $tone    = "alert-info";

    protected string $template = '
        <div class="alert {{$tone}}">
            {{$icon}}
            <div class="alert-body">{{$message}}</div>
        </div>';

    public function info(string $message): static    { return $this->set($message, 'info', 'info'); }
    public function success(string $message): static { return $this->set($message, 'success', 'check-circle'); }
    public function warning(string $message): static { return $this->set($message, 'warning', 'alert'); }
    public function danger(string $message): static  { return $this->set($message, 'danger', 'x-circle'); }

    /**
     * @param string $tone info | success | warning | danger
     * @param string $icon Icon name; '' for no icon
     */
    public function set(string $message, string $tone = "info", string $icon = ""): static
    {
        $this->message = trans($message);
        $this->tone    = 'alert-' . $tone;
        $this->icon    = $icon === "" ? "" : Icon::make()->set($icon, 'sm');
        return $this;
    }

    /** Markup instead of plain text — never hand this user input. */
    public function html(string $markup): static
    {
        $this->message = raw($markup);
        return $this;
    }
}
