<?php

/**
 * The how-it-works section: three steps, in order, plainly.
 *
 * PRODUCT.md §3. The section carries SiteHeader::HOW_IT_WORKS as its id —
 * the header link, the footer link and this target all read that one constant
 * (DECISIONS 2026-09-15), so the id never drifts from the links.
 *
 * An <ol> because the steps have an order and a screen reader should say so;
 * the numeral beside each step is decoration on top of that, hidden from the
 * accessibility tree rather than read twice.
 *
 * "Visual and interactive" (PRODUCT.md §3) stops where comprehension starts:
 * every step's text is in the served HTML and visible from the first paint.
 * The motion is an entrance only, and the one interactive element is the
 * action at the end.
 */
class HowItWorksSection extends Component
{
    /** The three steps, in page order: [heading, explanation]. */
    private const STEPS = [
        ['Show us where you are stuck', 'Tell us what you are building and what is happening. Plain language is completely fine.'],
        ['Meet someone technical',      'Join a one-to-one session with an experienced engineer who can inspect the situation with you.'],
        ['Leave with progress',         'Resolve the issue during the session where possible, or receive a clear explanation and practical next steps.'],
    ];

    public $heading = "How it works";
    public $action  = "Get someone technical";

    /** The three steps as <li> markup, built in mount(). */
    public $steps = "";

    protected string $template = '
        <section id="' . SiteHeader::HOW_IT_WORKS . '" class="how-it-works">
            <div class="how-it-works-inner">
                <h2 class="how-it-works-heading">{{$heading}}</h2>
                {{$steps}}
                <p class="how-it-works-action">
                    <a class="site-cta" href="' . SiteHeader::START_HREF . '">{{$action}}</a>
                </p>
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        $number = 0;

        foreach (self::STEPS as [$title, $explanation]) {
            $number++;
            $items .= '<li class="step" style="--step: ' . $number . ';">'
                . '<span class="step-index" aria-hidden="true">' . $number . '</span>'
                . '<h3 class="step-title">' . e($title) . '</h3>'
                . '<p class="step-text">' . e($explanation) . '</p>'
                . '</li>';
        }

        $this->steps = raw('<ol class="steps">' . $items . '</ol>');
    }
}
