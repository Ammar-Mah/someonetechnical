<?php

/**
 * The trust section: how the work is done, stated plainly.
 *
 * PRODUCT.md §8. The heading, one line, and the eight operating principles,
 * word for word and in §8's order. Trust comes from those principles alone:
 * §8 forbids testimonials, customer numbers, partner logos, ratings and
 * unsupported claims, so this section carries no quote, figure or image. The
 * principles have no explanation beneath them either, because every sentence
 * added there would be a claim PRODUCT.md does not make.
 *
 * Besides the footer, the only ink band on the page, so a focus outline here
 * has to be the accent (app.css). Nothing in it is focusable today.
 */
class TrustSection extends Component
{
    /** PRODUCT.md §8, word for word, in its order. */
    private const PRINCIPLES = [
        'Real experienced engineers',
        'Clear explanations in plain language',
        'No judgment about how the project was built',
        'No unnecessary rebuilding',
        'Transparent scope before work begins',
        'Careful treatment of project access and credentials',
        'Honest advice when something requires deeper work',
        'The customer retains ownership and control',
    ];

    public $heading = "Real technical judgment. No technical theatre.";
    public $lede    = "What you can expect from the engineer who joins you.";

    /** The principles as <li> markup, built in mount(). */
    public $principles = "";

    protected string $template = '
        <section class="trust">
            <div class="trust-inner">
                <div class="trust-copy">
                    <h2 class="trust-heading">{{$heading}}</h2>
                    <p class="trust-lede">{{$lede}}</p>
                </div>
                {{$principles}}
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        foreach (self::PRINCIPLES as $at => $principle) {
            $items .= '<li class="trust-principle" style="--at: ' . $at . ';">' . e($principle) . '</li>';
        }

        $this->principles = raw('<ul class="trust-principles">' . $items . '</ul>');
    }
}
