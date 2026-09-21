<?php

/**
 * The how-it-works section: three steps, in order, each one picture and a
 * line.
 *
 * PRODUCT.md §3's three step titles, each with its drawing (Pictogram) and one
 * short line in place of §3's longer explanations (#67). The section carries
 * SiteHeader::HOW_IT_WORKS as its id — the header link, the footer link and
 * this target all read that one constant (DECISIONS 2026-09-15), so the id
 * never drifts from the links.
 *
 * An <ol> because the steps have an order and a screen reader should say so;
 * the numeral and the drawing beside each step are decoration on top of that,
 * hidden from the accessibility tree rather than read twice. Every step's text
 * is in the served HTML and visible from the first paint; the motion is an
 * entrance only.
 */
class HowItWorksSection extends Component
{
    /** The three steps, in page order: [drawing, heading, line]. */
    private const STEPS = [
        ['talk',     'Show us where you are stuck', 'Tell us what’s happening, in plain words.'],
        ['meet',     'Meet someone technical',      'An experienced engineer joins you, one to one.'],
        ['progress', 'Leave with progress',         'Solved in the session where possible, or a clear next step.'],
    ];

    public $heading = "How it works";

    /** The three steps as <li> markup, built in mount(). */
    public $steps = "";

    protected string $template = '
        <section id="' . SiteHeader::HOW_IT_WORKS . '" class="how-it-works">
            <div class="how-it-works-inner">
                <h2 class="how-it-works-heading">{{$heading}}</h2>
                {{$steps}}
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        $number = 0;

        foreach (self::STEPS as [$drawing, $title, $line]) {
            $number++;
            $items .= '<li class="step">'
                . '<span class="step-picture" aria-hidden="true">' . Pictogram::svg($drawing)
                . '<span class="step-index">' . $number . '</span></span>'
                . '<h3 class="step-title">' . e($title) . '</h3>'
                . '<p class="step-text">' . e($line) . '</p>'
                . '</li>';
        }

        $this->steps = raw('<ol class="steps">' . $items . '</ol>');
    }
}
