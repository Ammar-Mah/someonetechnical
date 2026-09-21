<?php

/**
 * The final call to action: the page asks once more, says what the visitor
 * does not need to know first, and what they can count on.
 *
 * PRODUCT.md §9, in its own words and typography: the heading, the supporting
 * text, the action to the intake (SiteHeader::START_HREF, like every "Get
 * someone technical"), and the secondary note with the availability light.
 * Beneath them, three of §8's principles in a line — all the page keeps of
 * the trust section it once had (#67).
 *
 * The panel is filled with the accent. Ink reads 5.3:1 on it and the muted
 * tone does not, so every word here is ink (app.css). The action sits in a
 * flex row, as the hero's does, so the button's hover lift applies to it.
 */
class FinalCtaSection extends Component
{
    public $heading    = "You’ve asked the AI enough.";
    public $text       = "Show the problem to someone who can understand the project, explain what is happening and help you move forward.";
    public $action     = "Get someone technical";
    public $note       = "You don’t need to diagnose the problem before contacting us.";
    public $principles = "Real engineers · Plain language · You stay in control";

    protected string $template = '
        <section class="final-cta">
            <div class="final-cta-inner">
                <div class="final-cta-panel">
                    <h2 class="final-cta-heading">{{$heading}}</h2>
                    <p class="final-cta-text">{{$text}}</p>
                    <p class="final-cta-action">
                        <a class="site-cta" href="' . SiteHeader::START_HREF . '">{{$action}}</a>
                    </p>
                    <p class="final-cta-note">{{$note}}</p>
                    <p class="final-cta-principles">{{$principles}}</p>
                </div>
            </div>
        </section>';
}
