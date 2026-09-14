<?php

/**
 * The application mark and name.
 *
 *   Logo::make()                       // mark + APP_NAME, links to the app root
 *   Logo::make()->markOnly()
 *   Logo::make()->name('Something else')
 *
 * REBRANDING: replace MARK with your own artwork and the whole app follows —
 * this component is the only place it is drawn. public/img/favicon.svg holds
 * the same shape for the browser tab; regenerate it with faviconSvg().
 *
 * The name comes from APP_NAME in runtime.php, so there is no second place to
 * change it.
 */
class Logo extends Component
{
    /** The mark, as the inner markup of a 24x24 SVG. Filled, not stroked. */
    public const MARK =
        '<rect x="3" y="3" width="8" height="8" rx="2"/>'
      . '<rect x="13" y="3" width="8" height="8" rx="2" opacity=".5"/>'
      . '<rect x="3" y="13" width="8" height="8" rx="2" opacity=".5"/>'
      . '<rect x="13" y="13" width="8" height="8" rx="2"/>';

    public $mark  = "";
    public $title = "";
    public $href  = "";

    protected string $template = '<a class="app-logo" href="{{$href}}">{{$mark}}<span>{{$title}}</span></a>';

    public function mount()
    {
        $this->mark  = raw(self::markSvg('24'));
        $this->title = self::appName();
        $this->href  = defined('APP_URL') ? APP_URL : '/';
    }

    /**
     * The product name, translated.
     *
     * Passed through trans() so a language file can carry a localised form —
     * ar.json maps "dori" to "دوري". A name with no entry comes back unchanged,
     * which is what most products want.
     */
    public static function appName(): string
    {
        $name = defined('APP_NAME') && APP_NAME !== '' ? (string)APP_NAME : 'Baustein';
        return function_exists('trans') ? trans($name) : $name;
    }

    /** The mark on its own, at any pixel size. */
    public static function markSvg(string $size = '24'): string
    {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
             . ' fill="currentColor" stroke="none" aria-hidden="true" style="color:var(--brand)">'
             . self::MARK . '</svg>';
    }

    /** A standalone favicon document. Write it to public/img/favicon.svg. */
    public static function faviconSvg(string $colour = '#2563eb'): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="' . $colour . '">'
             . self::MARK . '</svg>';
    }

    /** Drop the wordmark. */
    public function markOnly(bool $on = true): static
    {
        if ($on) $this->title = "";
        return $this;
    }

    public function name(string $name): static
    {
        $this->title = $name;
        return $this;
    }
}
