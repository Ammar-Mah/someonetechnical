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
 * The section links are written './#<id>', not '#<id>', so they lead to the
 * home page's sections from every page the shell renders on — the intake
 * (#42) and the legal pages (#17) as much as `main` itself. On `main` the
 * resolved URL differs from the current one only in its fragment, so the
 * browser jumps within the page rather than reloading it (DECISIONS
 * 2026-09-18).
 */
class SiteHeader extends Component
{
    /** The home page. Every section link is this plus a fragment. */
    public const HOME_HREF = './';

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
                <a class="site-brand" href="' . self::HOME_HREF . '">{{$name}}</a>
                <div class="site-header-actions">
                    <nav class="site-nav" aria-label="Main">
                        <a href="' . self::HOME_HREF . '#' . self::HOW_IT_WORKS . '">How it works</a>
                        <a href="' . self::HOME_HREF . '#' . self::WHAT_WE_HELP_WITH . '">What we help with</a>
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
