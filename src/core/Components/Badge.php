<?php

/**
 * A pill: status, count, tag.
 *
 *   Badge::make()->set('Open')->tone('success')
 *   Badge::make()->set('3')->tone('brand')
 *   Badge::make()->set('Overdue')->tone('danger')->dot()
 *   Badge::make()->set('Custom')->color('#7c3aed')      // arbitrary colour
 *
 * tone() picks one of the five token-driven looks, so a badge follows the
 * theme. color() is the escape hatch for a colour that comes from data (a
 * user-chosen label colour); it derives readable ink automatically.
 */
class Badge extends Label
{
    public $tone = "";
    public $tint = "";

    protected string $template =
        '<span class="badge {{$tone}} inline-flex items-center gap-1" style="{{$tint}}">{{$icon}}{{$text}}</span>';

    /** brand | success | danger | warning | info | "" for neutral */
    public function tone(string $tone): static
    {
        $this->tone = $tone === "" ? "" : 'badge-' . $tone;
        return $this;
    }

    /** Show a small filled dot before the text, in the current tone colour. */
    public function dot(bool $on = true): static
    {
        return $on ? $this->addClass('badge-dot') : $this;
    }

    /**
     * A badge in an arbitrary colour, with ink chosen for contrast.
     * $hex is a background colour such as '#7c3aed'.
     */
    public function color(string $hex): static
    {
        $hex = trim($hex);
        if ($hex === '') return $this;
        $ink = function_exists('readableInk') ? readableInk($hex) : '#000';
        $this->tint = 'background:' . $hex . ';color:' . $ink . ';';
        return $this;
    }
}
