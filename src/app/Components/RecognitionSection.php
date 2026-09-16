<?php

/**
 * The recognition section: the visitor reads their own situation back.
 *
 * PRODUCT.md §2. A heading, the six situations, and the line that turns them
 * into the offer. Static markup — nothing here is a handler, and nothing
 * needs the client.
 *
 * THE COPY LIVES IN THE COMPONENT, which is what ARCHITECTURE.md → Sections
 * says and what every other section is to follow. It was briefly read at
 * render time from a file under docs/ instead; docs/ is on the deployment's
 * never-upload list, so the six situations were present locally and in CI and
 * absent on DEV, where the section rendered with nothing between its heading
 * and its closing line. The checks could not see it — the repository is whole
 * when they run — and DEV validation caught it (#11, rid 216e72bb).
 * tests/cases/site.php refuses a path the deployment excludes wherever this
 * application's PHP spells one out.
 */
class RecognitionSection extends Component
{
    /** PRODUCT.md §2, word for word. The page carries these bytes exactly. */
    private const SITUATIONS = [
        '“It works in preview, but I don’t know how to put it online.”',
        '“The AI changed something and now login is broken.”',
        '“I connected Stripe, but I’m not sure it is safe.”',
        '“It keeps telling me to update an environment variable.”',
        '“I have users coming. Is this actually ready?”',
        '“I don’t even know what question I should be asking.”',
    ];

    public $heading = "Does this sound familiar?";
    public $closing = "You do not need to hire an entire development agency. You may just need someone technical.";

    /** The situations as <li> markup, built in mount(). */
    public $situations = "";

    protected string $template = '
        <section class="recognition">
            <div class="recognition-inner">
                <h2 class="recognition-heading">{{$heading}}</h2>
                {{$situations}}
                <p class="recognition-closing">{{$closing}}</p>
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        foreach (self::SITUATIONS as $situation) {
            $items .= '<li class="recognition-situation"><p>' . e($situation) . '</p></li>';
        }

        $this->situations = raw('<ul class="recognition-situations">' . $items . '</ul>');
    }
}
