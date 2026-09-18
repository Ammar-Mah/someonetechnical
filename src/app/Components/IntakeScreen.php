<?php

/**
 * The intake: the seven questions of PRODUCT.md's conversion flow, and the
 * confirmation that replaces them once a request is stored.
 *
 * It asks only what that section lists, one question per intake_requests
 * column, and nothing else. "I don't know" is an acceptable answer to every
 * question but the contact details: the two choice questions carry it as a
 * real option, and the free-text ones say so under the field.
 *
 * The whole region — the questions or the confirmation — is REGION_ID, and
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
 * The conversational presentation is #43's. This is the plain, readable form
 * underneath it, and nothing here decides what is stored — IntakeHandler does.
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

    /** Said under every question that is not the contact details. */
    private const DONT_KNOW = '“I don’t know” is a fine answer.';

    public $heading = "Tell us where you are stuck";
    public $lede    = "Seven questions, none of them a trick. Plain language is completely fine, and you can leave anything blank.";
    public $action  = "Send this to someone technical";

    /** The questions as markup, built in mount(). */
    public $questions = "";

    protected string $template = '
        <section class="intake">
            <div class="intake-inner" id="' . self::REGION_ID . '">
                <h1 class="intake-heading">{{$heading}}</h1>
                <p class="intake-lede">{{$lede}}</p>
                <form class="intake-form" method="post" xon:submit="IntakeHandler.send()">
                    {{$questions}}
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
    }

    /** A free-text question. "I don't know" is accepted, and the note says so. */
    private static function text(string $name, string $question, string $control): string
    {
        $id = 'intake-' . str_replace('_', '-', $name);

        $field = $control === 'textarea'
            ? '<textarea class="intake-input" id="' . $id . '" name="' . e($name) . '" rows="3"></textarea>'
            : '<input class="intake-input" id="' . $id . '" name="' . e($name) . '" type="text">';

        return '<div class="intake-question">'
            . '<label class="intake-label" for="' . $id . '">' . e($question) . '</label>'
            . $field
            . '<p class="intake-note">' . e(self::DONT_KNOW) . '</p>'
            . '</div>';
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

        return '<fieldset class="intake-question">'
            . '<legend class="intake-label">' . e($question) . '</legend>'
            . '<div class="intake-choices">' . $options . '</div>'
            . '</fieldset>';
    }

    /**
     * The only required question. Both fields carry an error slot, which
     * IntakeHandler fills when it refuses; empty, they render nothing.
     *
     * The slot is its input's `aria-describedby` and a `role="alert"` live
     * region, so a refusal is announced and then read out with the field the
     * handler moves focus to — otherwise a screen reader lands on the input
     * and never hears why.
     */
    private static function contact(): string
    {
        return '<fieldset class="intake-question">'
            . '<legend class="intake-label">How do we reach you?</legend>'
            . '<label class="intake-sublabel" for="' . self::NAME_ID . '">Your name</label>'
            . '<input class="intake-input" id="' . self::NAME_ID . '" name="contact_name" type="text" required '
            . 'aria-describedby="' . self::NAME_ERROR_ID . '">'
            . '<p class="intake-error" id="' . self::NAME_ERROR_ID . '" role="alert"></p>'
            . '<label class="intake-sublabel" for="' . self::EMAIL_ID . '">Your email address</label>'
            . '<input class="intake-input" id="' . self::EMAIL_ID . '" name="contact_email" type="email" required '
            . 'aria-describedby="' . self::EMAIL_ERROR_ID . '">'
            . '<p class="intake-error" id="' . self::EMAIL_ERROR_ID . '" role="alert"></p>'
            . '</fieldset>';
    }

    /**
     * What replaces the CONTENTS of the region once a request is stored.
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
        return '<h1 class="intake-heading">Thank you, ' . e($name) . '.</h1>'
            . '<p class="intake-lede">Your request has arrived. Someone technical will read it and come back to you'
            . ' at the address you gave, with a time that works.</p>'
            . '<p class="intake-note">Nothing else is needed from you for now.</p>';
    }
}
