<?php

/**
 * A titled panel: header, body, optional footer.
 *
 *   Card::make('stats')->title('This week', Badge::make()->set('live')->tone('success'))
 *       ->slot($chartHtml)
 *       ->footer(Button::make()->set('Details')->link())
 *
 * The body is the slot, so HTML notation reads naturally:
 *   <x:Card title="Team"> … </x:Card>
 */
class Card extends Component
{
    public $heading = "";
    public $foot    = "";

    protected string $template = '
        <div class="card">
            {{$heading}}
            <div class="card-body">{{$slot}}</div>
            {{$foot}}
        </div>';

    /**
     * @param string $title  run through trans() and escaped
     * @param mixed  $aside  optional markup pinned to the right of the title
     */
    public function title(string $title, $aside = ""): static
    {
        if ($title === "" && (string)$aside === "") {
            $this->heading = "";
            return $this;
        }
        $this->heading = raw('<div class="card-header">'
            . '<span class="card-title">' . htmlspecialchars(trans($title), ENT_QUOTES, 'UTF-8') . '</span>'
            . ((string)$aside === "" ? "" : '<span class="flex items-center gap-2">' . $aside . '</span>')
            . '</div>');
        return $this;
    }

    /** @param string|array|Component $content */
    public function footer($content): static
    {
        $html = is_array($content) ? implode('', array_map('strval', $content)) : (string)$content;
        $this->foot = $html === "" ? "" : raw('<div class="card-footer">' . $html . '</div>');
        return $this;
    }

    /** Remove the body padding — for a card that holds a table or a list. */
    public function flush(bool $on = true): static
    {
        return $on ? $this->addClass('card-flush') : $this;
    }
}
