<?php

/**
 * The hero: what Someone Technical is, at a glance, and the moment a person
 * steps in.
 *
 * PRODUCT.md §1. The headline is the page's only <h1>. The supporting text,
 * the two actions and the availability note follow it, all static, so the page
 * loads into a complete hero before anything moves. The copy lives here, in
 * PRODUCT.md's own words and typography.
 *
 * The card beside them tells the story in motion: suggestions pile up while
 * the status reads "Still asking AI…", then someone technical joins and names
 * the actual problem. It repeats the text around it in pictures, so it is
 * hidden from assistive technology and announces nothing.
 *
 * Every animation in app.css runs from a starting frame TO the base state, so
 * this markup, unanimated, is already the settled card: the reply showing, the
 * suggestions muted, "Someone technical joined" in the status line.
 */
class HeroSection extends Component
{
    /** What the AI suggests, in order. The third is the first again. */
    private const SUGGESTIONS = [
        'Try clearing the cache and deploying again.',
        'Let’s double-check your environment variables.',
        'Try clearing the cache and deploying again.',
    ];

    /** The reply that ends them: calm, and it names the real problem. */
    private const REPLY = 'Your code is fine. The live site can’t reach its database yet, so let’s connect it together.';

    public $title  = "Your AI built the app. Now you need someone technical.";
    public $lede   = "Get one-to-one help from an experienced engineer with deployment, security, databases, payments, integrations and all the important details your AI keeps talking around.";
    public $action = "Get someone technical";
    public $more   = "See how it works";
    public $note   = "Bring the problem. You don’t need to know what it’s called.";

    /** The card's conversation as <li> markup, built in mount(). */
    public $thread = "";

    protected string $template = '
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-inner">
                <div class="hero-copy">
                    <h1 id="hero-title" class="hero-title">{{$title}}</h1>
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
                    {{$thread}}
                </div>
            </div>
        </section>';

    public function mount()
    {
        $items = '';
        foreach (self::SUGGESTIONS as $at => $suggestion) {
            $items .= '<li class="hero-message hero-message-ai" style="--at: ' . $at . ';">'
                . '<span class="hero-from">AI suggestion</span>' . e($suggestion)
                . '</li>';
        }
        $items .= '<li class="hero-message hero-message-human">'
            . '<span class="hero-from">Someone technical</span>' . e(self::REPLY)
            . '</li>';

        $this->thread = raw('<ol class="hero-thread">' . $items . '</ol>');
    }
}
