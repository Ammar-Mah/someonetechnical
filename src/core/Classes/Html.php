<?php

/**
 * A string that is deliberately markup.
 *
 * Templates escape what they print. That is the right default — most values
 * reaching a template came from a database or a request, and forgetting to
 * escape one of those is how pages get broken and users get attacked. But some
 * values genuinely ARE markup: the HTML a component assembled for its own
 * children, an icon's <svg>, a fragment of attributes.
 *
 * Wrapping such a value says so, once, at the point where the author knows:
 *
 *     $this->icon = raw('<svg …>');          // I built this; do not escape it
 *     $this->label = $userSuppliedTitle;     // escaped for me
 *
 * A Component is markup by construction, so it needs no wrapper — printing one
 * renders it. This class is for the times you have a STRING and mean it.
 *
 * Nothing here decides anything: it is a marker, and Template::out() reads it.
 */
final class Html implements Stringable
{
    public function __construct(private string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }

    /** True when there is nothing to render — for `if ($x->isEmpty())` guards. */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }
}
