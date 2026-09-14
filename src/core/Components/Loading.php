<?php

/**
 * A placeholder for something that has not arrived yet.
 *
 * Render it where the content will go, then have the handler replace it:
 *
 *   Container::make('report')->slot(Loading::make()->message('Loading report…'))
 *   // …and in the handler
 *   Event::make()->inner('#report', $report)->send();
 *
 * Loading::make()->bar() is the indeterminate variant, for the top of a region
 * that is refreshing in place.
 */
class Loading extends Component
{
    public $body = "";

    protected string $template = '<div class="flex items-center justify-center gap-2 p-4 text-muted">{{$body}}</div>';

    public function mount()
    {
        $this->body = raw('<span class="spinner"></span>');
    }

    public function message(string $text): static
    {
        $this->body = raw('<span class="spinner"></span>'
            . '<span class="text-sm">' . htmlspecialchars(trans($text), ENT_QUOTES, 'UTF-8') . '</span>');
        return $this;
    }

    /** A thin indeterminate bar instead of a spinner. */
    public function bar(): static
    {
        $this->body     = '';
        $this->template = '<div class="progress progress-indeterminate"></div>';
        return $this;
    }
}
