<?php

/**
 * A run of text, optionally preceded by an icon.
 *
 * The smallest useful component, and the one to reach for whenever a handler
 * has to replace a piece of text: give it a stable id and the handler can
 * target it with ->inner('#that-id', $newText).
 *
 *   Label::make()->set('Total')
 *   Label::make('greeting')->set($user['name'])->icon('user')->muted()
 *
 * set() escapes what it is given, so it is safe to pass database text.
 * Use html() when the value is markup you built yourself.
 */
class Label extends Component
{
    public $text = "";
    public $icon = "";

    protected string $template = '<span class="inline-flex items-center gap-2">{{$icon}}{{$text}}</span>';

    /** Plain text. The template escapes it. */
    public function set($text): static
    {
        $this->text = (string)$text;
        return $this;
    }

    /** Text passed through trans() first. */
    public function t(string $key): static
    {
        return $this->set(trans($key));
    }

    /** Markup. NOT escaped — never hand this user input. */
    public function html(string $markup): static
    {
        $this->text = raw($markup);
        return $this;
    }

    public function icon(string $name, string $size = "sm"): static
    {
        // The component itself, not its rendered string: a Component is markup
        // by construction, so the template prints it without escaping.
        $this->icon = $name === "" ? "" : Icon::make()->set($name, $size);
        return $this;
    }

    public function muted(): static
    {
        return $this->addClass('text-muted');
    }

    public function small(): static
    {
        return $this->addClass('text-sm');
    }

    public function bold(): static
    {
        return $this->addClass('font-semibold');
    }
}
