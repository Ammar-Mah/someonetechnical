<?php

/**
 * The support areas section: what someone technical can help with.
 *
 * PRODUCT.md §4. The twelve areas, word for word and in §4's order, each with
 * one plain sentence of what the help looks like from the customer's side.
 * §4 gives the names and asks for such explanations, so the sentences are
 * ours. None of them promises security or a fix, names a product or holds a
 * figure: the page makes no claim it cannot keep.
 *
 * The section carries SiteHeader::WHAT_WE_HELP_WITH as its id, the target of
 * the header's and the footer's "What we help with" (DECISIONS 2026-09-15).
 *
 * §4 rules out a grid of identical feature cards, so the areas are set as an
 * index in newspaper columns (app.css): entries without boxes, as tall as
 * their sentences. The closing row puts the action in a flex row, as every
 * "Get someone technical" button is.
 */
class SupportAreasSection extends Component
{
    /** PRODUCT.md §4, in its order: [area, explanation]. */
    private const AREAS = [
        ['Deployment and hosting',                     'Get your app out of the preview and onto a real address people can visit.'],
        ['Domains and email',                          'Connect your own domain, and work out why your emails are not arriving.'],
        ['Databases and storage',                      'Understand where your data lives and how it is kept.'],
        ['Authentication and permissions',             'Make sure the right people can sign in and see only what they should.'],
        ['Payments and subscriptions',                 'Check that charges, renewals and cancellations behave the way you expect.'],
        ['APIs and integrations',                      'Connect the services your app relies on, and find out why one stopped responding.'],
        ['Security and secrets',                       'Find exposed keys and passwords, and keep them out of your code.'],
        ['Backups and monitoring',                     'Know how to get your data back, and notice when something breaks.'],
        ['Broken builds and unexpected errors',        'Read the error together and trace it to what actually changed.'],
        ['Production and launch readiness',            'Go through what needs checking before real users arrive.'],
        ['Architecture and platform decisions',        'Think through the choices that are hard to undo later.'],
        ['Understanding what the AI actually created', 'Walk through the code you now own, in plain language.'],
    ];

    public $heading = "What we help with";
    public $lede    = "The technical parts between a working preview and a real product.";
    public $note    = "Not on the list? Bring it anyway.";
    public $action  = "Get someone technical";

    /** The areas as <li> markup, built in mount(). */
    public $areas = "";

    protected string $template = '
        <section id="' . SiteHeader::WHAT_WE_HELP_WITH . '" class="support-areas">
            <div class="support-areas-inner">
                <h2 class="support-areas-heading">{{$heading}}</h2>
                <p class="support-areas-lede">{{$lede}}</p>
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
        foreach (self::AREAS as $at => [$area, $explanation]) {
            $items .= '<li class="support-area" style="--at: ' . $at . ';">'
                . '<h3 class="support-area-name">' . e($area) . '</h3>'
                . '<p class="support-area-text">' . e($explanation) . '</p>'
                . '</li>';
        }

        $this->areas = raw('<ul class="support-areas-list">' . $items . '</ul>');
    }
}
