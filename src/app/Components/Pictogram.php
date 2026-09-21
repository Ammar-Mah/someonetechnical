<?php

/**
 * The page's drawn icons: one stroke drawing per name, inline SVG in the ink
 * of the text around it (#67).
 *
 * Drawn for this page, so nothing is fetched and nothing looks like stock
 * (PRODUCT.md *Avoid*). Each is decoration beside words that say the same
 * thing, so it is hidden from assistive technology. HowItWorksSection and
 * SupportAreasSection are the two callers.
 *
 * Not a Component: an icon has no state, id or template of its own, and the
 * sections build their lists in mount() as raw() markup, where a string fits.
 */
final class Pictogram
{
    /** Name => the drawing's inner markup, on a 24×24 grid. Literals only. */
    private const DRAWINGS = [
        // How it works
        'talk'      => '<path d="M4 5h16v11H10l-5 4v-4H4z"/><path d="M8 9h8M8 12h5"/>',
        'meet'      => '<circle cx="8" cy="8" r="3"/><path d="M2.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15 14.4A4.5 4.5 0 0 1 21.5 19"/>',
        'progress'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16.5 9.5"/>',
        // What we help with, in PRODUCT.md §4's order
        'deploy'    => '<path d="M7 18a4 4 0 0 1-.6-7.95A6 6 0 0 1 17.9 9 4.5 4.5 0 0 1 17 18"/><path d="M12 12v8M9 15l3-3 3 3"/>',
        'domain'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.5 3.6 9s-1.2 6.5-3.6 9c-2.4-2.5-3.6-5.5-3.6-9S9.6 5.5 12 3z"/>',
        'database'  => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M16 7l3 3M13.5 9.5l2 2"/>',
        'card'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'plug'      => '<path d="M9 3v5M15 3v5M6 8h12v3a6 6 0 0 1-12 0zM12 17v4"/>',
        'shield'    => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'backup'    => '<path d="M4 12a8 8 0 1 0 2.3-5.7M4 4v4h4"/><path d="M12 8v4l3 2"/>',
        'warning'   => '<path d="M12 3.5l9.5 17h-19z"/><path d="M12 10v5M12 17.5v.01"/>',
        'checklist' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8.5l1.5 1.5 2.5-2.5M8 14.5l1.5 1.5 2.5-2.5M14.5 9h2.5M14.5 15h2.5"/>',
        'layers'    => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'inspect'   => '<circle cx="11" cy="11" r="7"/><path d="M16 16l5 5M9.5 8.5L7 11l2.5 2.5M12.5 8.5L15 11l-2.5 2.5"/>',
    ];

    /** The named drawing as an <svg>, or nothing for a name it does not have. */
    public static function svg(string $name): string
    {
        if (!isset(self::DRAWINGS[$name])) {
            return '';
        }

        return '<svg class="pictogram" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none"'
            . ' stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'
            . self::DRAWINGS[$name] . '</svg>';
    }
}
