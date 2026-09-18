<?php

/**
 * The positioning section: the customer keeps the project.
 *
 * PRODUCT.md §5. Its statement as the heading, in §5's own typography, one
 * short explanation, and the five differentiators word for word and in order.
 * §5 asks for the explanation, so its two sentences are ours: who the service
 * is for, and where it sits, between the tools people build with and the
 * people they could hire, which is §5's "missing support layer". It names and
 * attacks no one, as §5 asks.
 *
 * Each differentiator is one list item. Its comparative is set in <strong>,
 * so the item's text stays exactly §5's while the eye finds "More immediate"
 * first.
 */
class PositioningSection extends Component
{
    /** PRODUCT.md §5, in its order: [comparative, the rest]. Together, word for word. */
    private const DIFFERENTIATORS = [
        ['More immediate',  'than searching for a freelancer'],
        ['More personal',   'than automated support'],
        ['More practical',  'than watching another tutorial'],
        ['More accessible', 'than hiring a fractional CTO'],
        ['More focused',    'than handing the project to an agency'],
    ];

    public $heading = "We don’t take your project away from you. We help you keep building it.";
    public $text    = "Someone Technical is for people who want to stay involved in their project, with experienced judgment on hand at the moments that matter. It fits between the tools you build with and the people you could hire.";

    /** The differentiators as <li> markup, built in mount(). */
    public $differentiators = "";

    protected string $template = '
        <section class="positioning">
            <div class="positioning-inner">
                <div class="positioning-copy">
                    <h2 class="positioning-heading">{{$heading}}</h2>
                    <p class="positioning-text">{{$text}}</p>
                </div>
                {{$differentiators}}
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        foreach (self::DIFFERENTIATORS as $at => [$comparative, $rest]) {
            $items .= '<li class="positioning-point" style="--at: ' . $at . ';">'
                . '<strong>' . e($comparative) . '</strong> ' . e($rest)
                . '</li>';
        }

        $this->differentiators = raw('<ul class="positioning-points">' . $items . '</ul>');
    }
}
