<?php

/**
 * The types-of-help section: four ways in, and the obvious first one.
 *
 * PRODUCT.md §6. The four formats with their descriptions word for word, and
 * no price, because none has been set and none may be invented.
 *
 * Help Session is the first item and the only one set apart: the page's raised
 * card, a "Start here" label, and the section's one action, to the intake. The
 * other three are plain items beside it. Four identical cards would be the
 * wall of feature cards PRODUCT.md rules out, and would not show where to start.
 */
class HelpTypesSection extends Component
{
    /** PRODUCT.md §6, in page order: [name, description]. The first is where to start. */
    private const FORMATS = [
        ['Help Session',              'Focused one-to-one assistance with one immediate technical problem.'],
        ['Launch Check',              'A structured human review before exposing the application to real customers.'],
        ['Technical Companion',       'Ongoing access to someone who becomes familiar with the project and its previous decisions.'],
        ['Rescue and Implementation', 'Hands-on technical work when the problem cannot reasonably be solved through guidance alone.'],
    ];

    /** The label and the action on the first format. */
    private const START  = 'Start here';
    private const ACTION = 'Get someone technical';

    public $heading = "Types of help";
    public $lede    = "Start with one problem. Go further only when the project needs it.";

    /** The formats as <li> markup, built in mount(). */
    public $formats = "";

    protected string $template = '
        <section class="help-types">
            <div class="help-types-inner">
                <h2 class="help-types-heading">{{$heading}}</h2>
                <p class="help-types-lede">{{$lede}}</p>
                {{$formats}}
            </div>
        </section>';

    public function mount()
    {
        $items = '';

        foreach (self::FORMATS as $at => [$name, $description]) {
            $first = $at === 0;

            $items .= '<li class="help-type' . ($first ? ' help-type-first' : '') . '" style="--at: ' . $at . ';">'
                . ($first ? '<p class="help-type-start">' . e(self::START) . '</p>' : '')
                . '<h3 class="help-type-name">' . e($name) . '</h3>'
                . '<p class="help-type-text">' . e($description) . '</p>'
                . ($first ? '<p class="help-type-action"><a class="site-cta" href="' . SiteHeader::START_HREF . '">' . e(self::ACTION) . '</a></p>' : '')
                . '</li>';
        }

        $this->formats = raw('<ul class="help-types-list">' . $items . '</ul>');
    }
}
