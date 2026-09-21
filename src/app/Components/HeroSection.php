<?php

/**
 * The hero: what Someone Technical is, in one look, and the moment a person
 * steps in.
 *
 * PRODUCT.md §1, cut down to what a visitor reads in a few seconds (#67): the
 * headline, the page's only <h1>; one line; the two actions; the availability
 * note. All static, so the page loads into a complete hero before anything
 * moves.
 *
 * The drawing beside them tells the story without words: a tangled line — the
 * project going round in circles — while the status reads "Still asking AI…";
 * then someone technical joins, the line runs straight to a finished mark, and
 * one short reply lands. It repeats the text around it in pictures, so it is
 * hidden from assistive technology and announces nothing.
 *
 * Every animation in app.css runs from a starting frame TO the base state, so
 * this markup, unanimated, is already the settled drawing: the tangle faded,
 * the line drawn, "Someone technical joined" in the status and the reply
 * showing.
 */
class HeroSection extends Component
{
    public $title  = "Your AI built the app.";
    public $turn   = "Now you need someone technical.";
    public $lede   = "One-to-one help from an experienced engineer, for the parts your AI keeps talking around.";
    public $action = "Get someone technical";
    public $more   = "See how it works";
    public $note   = "Bring the problem. You don’t need to know what it’s called.";

    /** The reply that ends the tangle: calm, and it promises no fix. */
    public $reply  = "Found it. Let’s sort it out together.";

    protected string $template = '
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-inner">
                <div class="hero-copy">
                    <h1 id="hero-title" class="hero-title">{{$title}} <span class="hero-turn">{{$turn}}</span></h1>
                    <p class="hero-lede">{{$lede}}</p>
                    <p class="hero-actions">
                        <a class="site-cta" href="' . SiteHeader::START_HREF . '">{{$action}}</a>
                        <a class="hero-more" href="#' . SiteHeader::HOW_IT_WORKS . '">{{$more}}</a>
                    </p>
                    <p class="hero-note">{{$note}}</p>
                </div>
                <div class="hero-card" aria-hidden="true">
                    <p class="hero-status">
                        <span class="hero-status-asking">Still asking AI…</span>
                        <span class="hero-status-joined">Someone technical joined</span>
                    </p>
                    <svg class="hero-drawing" viewBox="0 0 400 200" focusable="false" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path class="hero-tangle" pathLength="1" d="M40 100C70 20 120 190 150 90S230 20 190 140 110 170 210 70 300 190 260 120 320 30 360 100"/>
                        <path class="hero-line" pathLength="1" d="M40 100H360"/>
                        <circle class="hero-start" cx="40" cy="100" r="9"/>
                        <circle class="hero-end" cx="360" cy="100" r="18"/>
                        <path class="hero-tick" d="M351 100l6 6 12-12"/>
                    </svg>
                    <p class="hero-reply"><span class="hero-from">Someone technical</span>{{$reply}}</p>
                </div>
            </div>
        </section>';
}
