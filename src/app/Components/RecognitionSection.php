<?php

/**
 * The recognition section: the visitor reads their own situation back.
 *
 * PRODUCT.md §2. A heading, the six situations, and the line that turns them
 * into the offer. Static markup — nothing here is a handler, and nothing
 * needs the client.
 *
 * THE SIX SITUATIONS ARE COPY, NOT CODE, so they live in
 * docs/copy/recognition-situations.txt rather than in this template: the
 * owner edits the wording without touching PHP, and PRODUCT.md §2 stays the
 * one place the words come from.
 *
 * DELIBERATE DEFECT — ATLAS test plan, Phase 5 drill (Issue #11).
 * docs/ is on the deployment's never-upload list (.deployignore's header;
 * /docs/ in php-deploy-dev.yml), so this file is present locally and in CI and
 * ABSENT ON DEV. There the section renders its heading and its closing line
 * with no situations between them, and mount() records the fallback on the
 * `site` channel. This is planted on purpose, to show that green checks are
 * not validation; see the plan comment on Issue #11.
 */
class RecognitionSection extends Component
{
    /** Where the situations are read from, relative to the project root. */
    public const COPY = 'docs/copy/recognition-situations.txt';

    public $heading = "Does this sound familiar?";
    public $closing = "You do not need to hire an entire development agency. You may just need someone technical.";

    /** The situations as <li> markup, or "" when the copy file is unavailable. */
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
        $lines = self::situations();
        var_dump($lines);   // gate proof: the checks workflow must refuse this

        if ($lines === null) {
            // The page still serves, so this is a fallback, not an error:
            // policies/logging.md, "a configuration value fell back".
            Log::warn('site', 'recognition situations unavailable', [
                'path'   => self::COPY,
                'reason' => 'missing',
            ]);
            return;
        }

        $items = '';
        foreach ($lines as $line) {
            $items .= '<li class="recognition-situation"><p>' . e($line) . '</p></li>';
        }

        $this->situations = raw('<ul class="recognition-situations">' . $items . '</ul>');
    }

    /**
     * The situations, in file order, or null when the file cannot be read.
     *
     * Guarded with is_file(): after boot a failed file_get_contents() is an
     * ErrorException and would take the whole page down
     * (.agent/framework/RULES.md §3).
     */
    public static function situations(): ?array
    {
        $path = ROOT . '/' . self::COPY;

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $lines = [];
        foreach (preg_split('/\R/', (string)file_get_contents($path)) as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] !== '#') {
                $lines[] = $line;
            }
        }

        return $lines === [] ? null : $lines;
    }
}
