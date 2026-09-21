<?php

/**
 * The support areas section: what someone technical can help with, at a
 * glance.
 *
 * PRODUCT.md §4's twelve areas, word for word and in §4's order, each a
 * drawing (Pictogram) and its name — no explanation beneath it, so the list
 * reads in seconds (#67). The section carries SiteHeader::WHAT_WE_HELP_WITH as
 * its id, the target of the header's and the footer's "What we help with"
 * (DECISIONS 2026-09-15).
 *
 * §4 rules out a grid of identical feature cards, so the areas are tags that
 * wrap as the width allows, as long as their names (app.css). The closing row
 * puts the action in a flex row, as every "Get someone technical" button is.
 */
class SupportAreasSection extends Component
{
    /** PRODUCT.md §4, in its order: [drawing, area]. */
    private const AREAS = [
        ['deploy',    'Deployment and hosting'],
        ['domain',    'Domains and email'],
        ['database',  'Databases and storage'],
        ['key',       'Authentication and permissions'],
        ['card',      'Payments and subscriptions'],
        ['plug',      'APIs and integrations'],
        ['shield',    'Security and secrets'],
        ['backup',    'Backups and monitoring'],
        ['warning',   'Broken builds and unexpected errors'],
        ['checklist', 'Production and launch readiness'],
        ['layers',    'Architecture and platform decisions'],
        ['inspect',   'Understanding what the AI actually created'],
    ];

    public $heading = "What we help with";
    public $note    = "Not on the list? Bring it anyway.";
    public $action  = "Get someone technical";

    /** The areas as <li> markup, built in mount(). */
    public $areas = "";

    protected string $template = '
        <section id="' . SiteHeader::WHAT_WE_HELP_WITH . '" class="support-areas">
            <div class="support-areas-inner">
                <h2 class="support-areas-heading">{{$heading}}</h2>
                {{$areas}}
                <p class="support-areas-action">
                    <span class="support-areas-note">{{$note}}</span>
                    <a class="site-cta" href="' . SiteHeader::START_HREF . '">{{$action}}</a>
                </p>
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        foreach (self::AREAS as [$drawing, $area]) {
            $items .= '<li class="support-area">' . Pictogram::svg($drawing)
                . '<span class="support-area-name">' . e($area) . '</span></li>';
        }

        $this->areas = raw('<ul class="support-areas-list">' . $items . '</ul>');
    }
}
