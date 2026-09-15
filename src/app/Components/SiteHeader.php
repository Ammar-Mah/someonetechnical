<?php

/**
 * The bar across the top of the page: the name, the two section links, and
 * the way in to the intake.
 *
 * THE LINK TARGETS LIVE HERE. The how-it-works and what-we-help-with sections
 * take their ids from the constants below, and every link to them — this
 * header, SiteFooter, the hero — reads the same constants, so a target changes
 * in one place (DECISIONS 2026-09-15).
 *
 * Every link is visible at every width. A menu toggle would hide links behind
 * state and need a script; rows that wrap keep the source order, the Tab order
 * and the reading order the same. The section links and the action wrap as
 * one group, so a narrowing window gives the name its own row first, then the
 * action.
 *
 * The section links are fragments, so they only work on the page that holds
 * the sections, `main`. A view that renders this header anywhere else has to
 * point them at the home page.
 */
class SiteHeader extends Component
{
    /** Id of the how-it-works section (#11). */
    public const HOW_IT_WORKS = 'how-it-works';

    /** Id of the what-we-help-with section (#12). */
    public const WHAT_WE_HELP_WITH = 'what-we-help-with';

    /** Where every "Get someone technical" and "Book a session" leads: the intake (#15). */
    public const START_HREF = '?page=start';

    public $name = "";

    protected string $template = '
        <header class="site-header">
            <div class="site-header-bar">
                <a class="site-brand" href="./">{{$name}}</a>
                <div class="site-header-actions">
                    <nav class="site-nav" aria-label="Main">
                        <a href="#' . self::HOW_IT_WORKS . '">How it works</a>
                        <a href="#' . self::WHAT_WE_HELP_WITH . '">What we help with</a>
                    </nav>
                    <a class="site-cta" href="' . self::START_HREF . '">Get someone technical</a>
                </div>
            </div>
        </header>';

    public function mount()
    {
        $this->name = Logo::appName();
    }
}
