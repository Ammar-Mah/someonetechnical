<?php

/**
 * The continuity section: the next session starts where the last one ended.
 *
 * PRODUCT.md §7. The heading, the promise that nobody has to tell the whole
 * story again, and the record that makes it true: kept with the visitor's
 * permission, and holding what §7 lists, shown on a note beside the text.
 *
 * Nothing here mentions passwords, credentials or keys, not even to rule them
 * out. §7 asks that nothing imply they are stored, and a sentence about them
 * either way would be a security claim PRODUCT.md does not make.
 */
class ContinuitySection extends Component
{
    /** What the record holds, PRODUCT.md §7, in its order. */
    private const RECORD = ['Tools', 'Hosting', 'Integrations', 'Previous issues', 'Important decisions'];

    public $heading = "Someone who remembers your project";
    public $lede    = "You shouldn’t have to tell the whole story again at every session.";
    public $text    = "With your permission, Someone Technical keeps a concise record of your project, so your next session continues from where the last one ended.";
    public $label   = "Your project record";

    /** The record's contents as <li> markup, built in mount(). */
    public $record = "";

    protected string $template = '
        <section class="continuity">
            <div class="continuity-inner">
                <div class="continuity-copy">
                    <h2 class="continuity-heading">{{$heading}}</h2>
                    <p class="continuity-lede">{{$lede}}</p>
                    <p class="continuity-text">{{$text}}</p>
                </div>
                <div class="continuity-note">
                    <h3 class="continuity-note-label">{{$label}}</h3>
                    {{$record}}
                </div>
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        foreach (self::RECORD as $at => $entry) {
            $items .= '<li class="continuity-entry" style="--at: ' . $at . ';">' . e($entry) . '</li>';
        }

        $this->record = raw('<ul class="continuity-record">' . $items . '</ul>');
    }
}
