<?php

/**
 * The intake: the seven questions of PRODUCT.md's conversion flow, and the
 * confirmation that replaces them once a request is stored.
 *
 * It asks only what that section lists, one question per intake_requests
 * column, and nothing else. "I don't know" is an acceptable answer to every
 * question but the contact details: the two choice questions carry it as a
 * real option, and the free-text ones say so inside the question.
 *
 * IT IS A CONVERSATION, NOT A FORM (#43). Every question is one turn: what
 * Someone Technical says, in a bubble, and under it the visitor's reply. The
 * two speakers are named in the markup rather than only drawn in CSS, so the
 * thread reads the same to a screen reader as it looks on the screen. A turn
 * is a <div> when its answer is one control and a <fieldset> when the answer
 * is a group; a <legend> must be its fieldset's first child, so there the
 * bubble IS the legend and the speaker line is a <span> inside it.
 *
 * The whole region — the thread or the confirmation — is REGION_ID, and
 * IntakeHandler replaces it on a stored request. A refusal replaces nothing:
 * it fills that field's error slot and marks the input, so the visitor keeps
 * every word they typed (.agent/framework/RULES.md §5, "Validation is an early
 * return").
 *
 * `method="post"` IS NOT DECORATION. `xon:submit` compiles to an inline
 * `onSubmit`, and only Baustein.js calls preventDefault() — but every script
 * is moved to just before </body>, so the form is interactive for a moment
 * before `xhandle` exists, and forever if that script fails or is blocked. A
 * form with no method defaults to GET, which would put the visitor's name and
 * email address in the address bar, in their history and in the web server's
 * access log — outside the application log the audit hook filters. The method
 * is what keeps a submit the framework did not catch from leaking the answers.
 *
 * SUCH A SUBMIT IS NOW ANSWERED. It lands back here as a POST, which nothing
 * stores, and the visitor used to be handed an empty form with no explanation.
 * A POST renders the notice below; a browser that will not run the client at
 * all reads the <noscript> line before it ever tries. Neither path stores,
 * validates or logs anything — IntakeHandler owns all three.
 */
class IntakeScreen extends Component
{
    /** The region a stored request replaces. */
    public const REGION_ID = 'intake-region';

    /** The two fields that are validated, and the id of each one's error slot. */
    public const NAME_ID       = 'intake-contact-name';
    public const EMAIL_ID      = 'intake-contact-email';
    public const NAME_ERROR_ID  = 'intake-contact-name-error';
    public const EMAIL_ERROR_ID = 'intake-contact-email-error';

    /**
     * The choices the two choice questions offer. IntakeHandler stores no
     * other value, and each is inside is_live's and help_wanted's VARCHAR(20).
     */
    public const IS_LIVE     = ['Yes, it is live', 'Not yet', 'I don’t know'];
    public const HELP_WANTED = ['Guidance', 'Hands-on help', 'I’m not sure'];

    /**
     * The honeypot's name (#16). The field sits off-screen, out of the Tab
     * order and hidden from assistive technology, so only something filling in
     * the markup fills it; IntakeHandler thanks it and stores nothing.
     */
    public const TRAP = 'website';

    /** Who is speaking. Both are read out, not only drawn. */
    public const THEM = 'Someone technical';
    public const YOU  = 'You';

    /** The thread's order. A turn's place in it is all its entrance needs. */
    private const TURNS = ['building', 'ai_tool', 'stuck_on', 'is_live', 'help_wanted', 'contact', 'preferred_time'];

    /** Said inside every question that is not the contact details. */
    private const DONT_KNOW = '“I don’t know” is a fine answer.';

    /** Told to a visitor whose submit the client never caught. */
    private const DID_NOT_SEND = 'That did not send, and nothing you typed was kept. It usually means the page had not finished loading. Please answer again and press send.';

    /** Told before they try, when this browser will not run the client at all. */
    private const NEEDS_SCRIPT = 'Sending needs JavaScript. Switch it on for this page and reload, and your answers will reach us.';

    public $heading = "Tell us where you are stuck";
    public $lede    = "Seven questions, none of them a trick. Plain language is completely fine, and you can leave anything blank.";
    public $action  = "Send this to someone technical";

    /** The turns as markup, built in mount(). */
    public $questions = "";

    /** The did-not-send notice, or nothing. Built in mount(). */
    public $alert = "";

    protected string $template = '
        <section class="intake">
            <div class="intake-inner" id="' . self::REGION_ID . '">
                <h1 class="intake-heading">{{$heading}}</h1>
                <p class="intake-lede">{{$lede}}</p>
                {{$alert}}
                <form class="intake-form" method="post" xon:submit="IntakeHandler.send()">
                    {{$questions}}
                    <div class="intake-trap" aria-hidden="true"><label for="intake-' . self::TRAP . '">Leave this empty</label><input id="intake-' . self::TRAP . '" name="' . self::TRAP . '" type="text" tabindex="-1" autocomplete="off"></div>
                    <noscript><p class="intake-alert">' . self::NEEDS_SCRIPT . '</p></noscript>
                    <p class="intake-submit"><button class="site-cta" type="submit">{{$action}}</button></p>
                </form>
            </div>
        </section>';

    public function mount()
    {
        $this->questions = raw(
              self::text('building', 'What are you building?', 'textarea')
            . self::text('ai_tool', 'Which AI building tool are you using?', 'input')
            . self::text('stuck_on', 'What are you currently stuck on?', 'textarea')
            . self::choice('is_live', 'Is the project already live?', self::IS_LIVE)
            . self::choice('help_wanted', 'Would you prefer guidance, hands-on help, or are you unsure?', self::HELP_WANTED)
            . self::contact()
            . self::text('preferred_time', 'When would suit you for a session?', 'input')
        );

        // A POST reaching this page is a submit Baustein.js did not catch: the
        // answers are in the body, nothing read them, and without this the
        // visitor is handed an empty form and told nothing. Found in #42's
        // re-review, folded into this Issue.
        $this->alert = raw(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            ? '<p class="intake-alert">' . e(self::DID_NOT_SEND) . '</p>'
            : '');
    }

    /**
     * One turn: what Someone Technical says, then the visitor's reply.
     *
     * $said is phrasing content either way, so the same pieces serve a div's
     * bubble and a fieldset's legend.
     */
    private static function turn(string $name, string $said, string $reply, bool $group): string
    {
        $tag    = $group ? 'fieldset' : 'div';
        $bubble = $group ? 'legend' : 'div';

        return '<' . $tag . ' class="intake-turn" style="--turn: ' . (int)array_search($name, self::TURNS, true) . '">'
            . '<' . $bubble . ' class="intake-said">'
            . '<span class="intake-from">' . e(self::THEM) . '</span>'
            . $said
            . '</' . $bubble . '>'
            . '<div class="intake-reply">'
            . '<span class="intake-from intake-from-you">' . e(self::YOU) . '</span>'
            . $reply
            . '</div>'
            . '</' . $tag . '>';
    }

    /** A free-text question. "I don't know" is accepted, and the bubble says so. */
    private static function text(string $name, string $question, string $control): string
    {
        $id = 'intake-' . str_replace('_', '-', $name);

        $field = $control === 'textarea'
            ? '<textarea class="intake-input" id="' . $id . '" name="' . e($name) . '" rows="3"></textarea>'
            : '<input class="intake-input" id="' . $id . '" name="' . e($name) . '" type="text">';

        // The question is the control's label, so the message and the answer
        // are one thing to a pointer and to a screen reader.
        $said = '<label class="intake-label" for="' . $id . '">' . e($question) . '</label>'
            . '<span class="intake-note">' . e(self::DONT_KNOW) . '</span>';

        return self::turn($name, $said, $field, false);
    }

    /** A question answered by choosing, with "I don't know" among the choices. */
    private static function choice(string $name, string $question, array $choices): string
    {
        $options = '';

        foreach ($choices as $at => $choice) {
            $id = 'intake-' . str_replace('_', '-', $name) . '-' . $at;

            $options .= '<label class="intake-choice" for="' . $id . '">'
                . '<input id="' . $id . '" type="radio" name="' . e($name) . '" value="' . e($choice) . '">'
                . '<span>' . e($choice) . '</span>'
                . '</label>';
        }

        return self::turn(
            $name,
            '<span class="intake-label">' . e($question) . '</span>',
            '<div class="intake-choices">' . $options . '</div>',
            true
        );
    }

    /**
     * The only required question. Both fields carry an error slot, which
     * IntakeHandler fills when it refuses; empty, they render nothing.
     *
     * The slot is its input's `aria-describedby` and a `role="alert"` live
     * region, so a refusal is announced and then read out with the field the
     * handler moves focus to — otherwise a screen reader lands on the input
     * and never hears why. It sits directly under its own input, which is
     * where the visitor is looking when focus arrives.
     */
    private static function contact(): string
    {
        $reply = '<label class="intake-sublabel" for="' . self::NAME_ID . '">Your name</label>'
            . '<input class="intake-input" id="' . self::NAME_ID . '" name="contact_name" type="text" required '
            . 'aria-describedby="' . self::NAME_ERROR_ID . '">'
            . '<p class="intake-error" id="' . self::NAME_ERROR_ID . '" role="alert"></p>'
            . '<label class="intake-sublabel" for="' . self::EMAIL_ID . '">Your email address</label>'
            . '<input class="intake-input" id="' . self::EMAIL_ID . '" name="contact_email" type="email" required '
            . 'aria-describedby="' . self::EMAIL_ERROR_ID . '">'
            . '<p class="intake-error" id="' . self::EMAIL_ERROR_ID . '" role="alert"></p>';

        return self::turn('contact', '<span class="intake-label">How do we reach you?</span>', $reply, true);
    }

    /**
     * What replaces the CONTENTS of the region once a request is stored: one
     * more turn, and it is Someone Technical's.
     *
     * The region's own element stays — inner() replaces children, so a wrapper
     * carrying REGION_ID again would nest a second element with that id inside
     * the first. IntakeHandler adds the `intake-confirmed` class to the region
     * instead.
     *
     * The visitor's name is the one thing they typed that comes back, and it
     * comes back through e().
     */
    public static function confirmation(string $name): string
    {
        return '<div class="intake-turn">'
            . '<div class="intake-said">'
            . '<span class="intake-from">' . e(self::THEM) . '</span>'
            . '<h1 class="intake-heading">Thank you, ' . e($name) . '.</h1>'
            . '<p class="intake-lede">Your request has arrived. Someone technical will read it and come back to you'
            . ' at the address you gave, with a time that works.</p>'
            . '</div>'
            . '<p class="intake-note">Nothing else is needed from you for now.</p>'
            . '</div>';
    }
}
