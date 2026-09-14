<?php

/**
 * The template engine.
 *
 * A template is compiled ONCE into a plain PHP file and included thereafter, so
 * repeat renders cost a function call and opcache holds the compiled form.
 * Previously every render re-scanned the source with several regexes and ran
 * eval() for anything that was not a bare variable — and eval'd code is the one
 * kind of PHP opcache can never keep.
 *
 * Compilation also makes the engine SAFER rather than less safe. In the old
 * interpreter, substituted values were parked behind placeholders precisely
 * because later passes ({% %}, <x:…>) ran over the whole string and would
 * otherwise have re-read data as template syntax. A compiled template has no
 * later passes at all: {{ }} writes straight to the output buffer, and the
 * {% %} and <x:…> constructs were turned into code at compile time, from the
 * template source, before any data existed.
 *
 * The old interpreter is still here, and is used when a template cannot be
 * compiled (a syntax error in an expression, say). It logs when that happens.
 */
class Template
{
    private static array $sections = [];
    private static ?string $currentSection = null;
    private static ?string $layout = null;

    /** Compiled render closures for this request, keyed by template hash. */
    private static array $renderers = [];

    /** Set false to force the interpreter — for debugging a compilation problem. */
    public static bool $compile = true;

    /** Compiled templates live here, under the (deny-all) cache directory. */
    private const CACHE_SUBDIR = 'templates';

    /** How many compiled files to keep before pruning the oldest. */
    private const CACHE_CAP = 500;

    /**
     * Turn a template into HTML.
     *
     * @param string $template code to be processed
     * @param array  $data     values the template may reference
     */
    public static function process(string $template, array $data = []): string
    {
        // Layout inheritance is stateful and rewrites the source itself, so it
        // runs before anything is compiled. Guarded: no component template uses
        // a directive, and str_contains is far cheaper than five regexes.
        if (str_contains($template, '@')) {
            $template = self::directives($template);
        }

        $html = self::render($template, $data);

        // Defer scripts to just before </body> so the page paints sooner.
        //
        // Only for a full document. A component fragment has no </body>, and
        // appending the script after the component's root element would produce
        // two top-level nodes — the append action only keeps children[0], so the
        // script silently vanished and the markup was wrong.
        if (str_contains($html, '</body>') && stripos($html, '<script') !== false) {
            $html = self::deferScripts($html);
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Layout directives
    // -------------------------------------------------------------------------

    private static function directives(string $template): string
    {
        // @extend('layout')
        if (preg_match('/@extend\(\'([^\']+)\'\)/', $template, $matches)) {
            self::$layout = $matches[1];
            $template = str_replace($matches[0], '', $template);
        }

        // @section('name') … @endsection — captured as SOURCE and injected at
        // the layout's @yield, where it is compiled along with the layout.
        if (str_contains($template, '@section')) {
            $template = preg_replace_callback('/@section\(\'([^\']+)\'\)(.*?)@endsection/s', function ($matches) {
                self::$sections[$matches[1]] = $matches[2];
                return '';
            }, $template);
        }

        // @yield('name')
        if (str_contains($template, '@yield')) {
            $template = preg_replace_callback('/@yield\(\'([^\']+)\'\)/', function ($matches) {
                return self::$sections[$matches[1]] ?? '';
            }, $template);
        }

        return $template;
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * True when a template contains anything that has to be evaluated at all.
     *
     * Ordered so the common answers come out of the cheapest tests: almost
     * every template that is dynamic says so with a brace, and one that is
     * wholly static gets away with three substring scans.
     */
    private static function isDynamic(string $source): bool
    {
        if (str_contains($source, '{')
            && (str_contains($source, '{{') || str_contains($source, '{%'))) {
            return true;
        }
        if (stripos($source, '<x:') !== false) {
            return true;
        }
        return str_contains($source, '@')
            && (str_contains($source, '@css(') || str_contains($source, '@js('));
    }

    private static function render(string $source, array $data): string
    {
        // A template with no placeholders is already its own output. Common
        // enough to be worth the three substring tests.
        if (!self::isDynamic($source)) {
            return $source;
        }

        if (self::$compile) {
            $renderer = self::renderer($source);
            if ($renderer !== null) {
                ob_start();
                try {
                    $renderer($data);
                } catch (Throwable $e) {
                    ob_end_clean();
                    Log::exception('template', $e, ['excerpt' => substr($source, 0, 200)]);
                    throw $e;
                }
                return (string)ob_get_clean();
            }
        }

        return self::interpret($source, $data);
    }

    /** The compiled closure for a template, or null if it could not be compiled. */
    private static function renderer(string $source): ?Closure
    {
        $key = md5($source);

        if (array_key_exists($key, self::$renderers)) {
            return self::$renderers[$key];
        }

        $path = self::cacheDir() . DIRECTORY_SEPARATOR . 't' . $key . '.php';

        if (!is_file($path)) {
            $code = self::compile($source);
            if ($code === null || !self::store($path, $code)) {
                return self::$renderers[$key] = null;
            }
        }

        try {
            $renderer = include $path;
        } catch (Throwable $e) {
            // A cache file that will not load is worse than none: drop it so the
            // next request recompiles rather than failing the same way forever.
            @unlink($path);
            Log::exception('template', $e, ['cache' => basename($path)]);
            return self::$renderers[$key] = null;
        }

        if (!$renderer instanceof Closure) {
            @unlink($path);
            Log::warn('template', 'compiled template did not return a renderer', ['cache' => basename($path)]);
            return self::$renderers[$key] = null;
        }

        return self::$renderers[$key] = $renderer;
    }

    private static function cacheDir(): string
    {
        static $dir = null;
        if ($dir === null) {
            $dir = (defined('ROOT') ? ROOT : dirname(__DIR__, 3))
                 . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . self::CACHE_SUBDIR;
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Write atomically, so a concurrent reader never includes half a file. */
    private static function store(string $path, string $code): bool
    {
        $dir = self::cacheDir();
        if (!is_dir($dir) || !is_writable($dir)) return false;

        $tmp = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $code, LOCK_EX) === false) return false;
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }

        self::prune();
        return true;
    }

    /**
     * Keep the compiled directory bounded.
     *
     * Component templates are fixed, but a VIEW is compiled from its own
     * output, so a view that emits something varying outside {{ }} would mint a
     * new file every time. This only runs when a new file is written, which
     * after warm-up is almost never.
     */
    private static function prune(): void
    {
        $files = glob(self::cacheDir() . DIRECTORY_SEPARATOR . 't*.php') ?: [];
        if (count($files) <= self::CACHE_CAP) return;

        $byAge = [];
        foreach ($files as $file) $byAge[$file] = @filemtime($file) ?: 0;
        asort($byAge);

        $drop = count($files) - (int)(self::CACHE_CAP * 0.8);
        foreach (array_slice(array_keys($byAge), 0, $drop) as $file) @unlink($file);

        Log::info('template', 'pruned the compiled-template cache', ['removed' => $drop]);
    }

    // -------------------------------------------------------------------------
    // The compiler
    // -------------------------------------------------------------------------

    /**
     * Compile a template into PHP source, or null if the result would not parse.
     *
     * The generated file is pure PHP — no inline HTML — which sidesteps both the
     * "?> eats the following newline" rule and any literal "<?" inside the
     * markup.
     */
    private static function compile(string $source): ?string
    {
        $seq = 0;
        $body = self::compileNodes($source, $seq);

        $code = "<?php\n"
              . "// Compiled Baustein template. Generated file — do not edit.\n"
              . "return static function (array \$__d) {\n"
              . "extract(\$__d, EXTR_SKIP);\n"
              . $body
              . "};\n";

        // Validate without executing. A bad expression in a template would
        // otherwise become a fatal at include time, on every request.
        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (Throwable $e) {
            Log::warn('template', 'could not compile a template, using the interpreter', [
                'error'   => $e->getMessage(),
                'excerpt' => substr(trim($source), 0, 200),
            ]);
            return null;
        }

        return $code;
    }

    /** Walk a template and emit PHP for each construct. */
    private static function compileNodes(string $source, int &$seq): string
    {
        $out = '';
        $i = 0;
        $length = strlen($source);

        while ($i < $length) {
            $next = self::nextConstruct($source, $i);

            if ($next === null) {
                $out .= self::emitLiteral(substr($source, $i));
                break;
            }

            [$at, $kind] = $next;
            if ($at > $i) {
                $out .= self::emitLiteral(substr($source, $i, $at - $i));
            }

            switch ($kind) {
                case 'expr':
                    // {{-- a comment --}} is dropped entirely: it reaches
                    // neither the output nor the compiled file.
                    if (substr($source, $at, 4) === '{{--') {
                        $close = strpos($source, '--}}', $at + 4);
                        $i = $close === false ? $length : $close + 4;
                        break;
                    }
                    $close = strpos($source, '}}', $at + 2);
                    if ($close === false) { $out .= self::emitLiteral(substr($source, $at)); $i = $length; break; }
                    $out .= self::emitExpression(trim(substr($source, $at + 2, $close - $at - 2)));
                    $i = $close + 2;
                    break;

                case 'stmt':
                    $close = strpos($source, '%}', $at + 2);
                    if ($close === false) { $out .= self::emitLiteral(substr($source, $at)); $i = $length; break; }
                    $out .= self::emitStatements(trim(substr($source, $at + 2, $close - $at - 2)));
                    $i = $close + 2;
                    break;

                case 'tag':
                    $i = self::emitComponentTag($source, $at, $seq, $out);
                    break;

                case 'asset':
                    $i = self::emitAsset($source, $at, $out);
                    break;
            }
        }

        return $out;
    }

    /** @return array{0:int,1:string}|null offset and kind of the next construct */
    private static function nextConstruct(string $source, int $from): ?array
    {
        $best = null;

        foreach ([['{{', 'expr'], ['{%', 'stmt'], ['@css(', 'asset'], ['@js(', 'asset']] as [$needle, $kind]) {
            $at = strpos($source, $needle, $from);
            if ($at !== false && ($best === null || $at < $best[0])) $best = [$at, $kind];
        }

        $at = stripos($source, '<x:', $from);
        if ($at !== false && ($best === null || $at < $best[0])) $best = [$at, 'tag'];

        return $best;
    }

    private static function emitLiteral(string $text): string
    {
        if ($text === '') return '';
        return 'echo ' . var_export($text, true) . ";\n";
    }

    /**
     * {{ expression }}
     *
     * A bare variable is emitted directly. Anything else is PHP source, exactly
     * as eval() received it before, so existing templates keep working.
     */
    private static function emitExpression(string $expression): string
    {
        if ($expression === '') return '';

        if (preg_match('/^\$([A-Za-z_]\w*)$/', $expression, $m)) {
            // ?? short-circuits, so missing() is only reached when the value is
            // genuinely absent — and it costs nothing when it is not.
            return 'echo Template::out($' . $m[1] . ' ?? Template::missing(' . var_export($m[1], true) . "));\n";
        }

        return 'echo Template::out(' . $expression . ");\n";
    }

    /**
     * {% statements %}
     *
     * Entities are decoded first, because a template authored inside HTML may
     * carry "&amp;&amp;" where it means "&&".
     *
     * Backslashes used to be stripped here as well, which quietly broke "\n"
     * and every qualified name. They are left alone now.
     */
    private static function emitStatements(string $code): string
    {
        $code = htmlspecialchars_decode($code, ENT_QUOTES);
        $trimmed = rtrim($code);
        if ($trimmed === '') return '';

        // Only terminate a statement that is not already terminated and does not
        // open or close a block — so "{% if ($a) { %} … {% } %}" spans blocks.
        $last = substr($trimmed, -1);
        if (!in_array($last, [';', '{', '}', ':'], true)) $trimmed .= ';';

        return $trimmed . "\n";
    }

    /** @css('path'[, true|false]) and @js('path'[, true|false]) */
    private static function emitAsset(string $source, int $at, string &$out): int
    {
        $isCss = strncasecmp(substr($source, $at, 5), '@css(', 5) === 0;
        $pattern = $isCss
            ? '/^@css\(\'([^\']+)\'(?:,\s*(true|false))?\)/'
            : '/^@js\(\'([^\']+)\'(?:,\s*(true|false))?\)/';

        if (!preg_match($pattern, substr($source, $at, 200), $m)) {
            $out .= self::emitLiteral(substr($source, $at, $isCss ? 5 : 4));
            return $at + ($isCss ? 5 : 4);
        }

        $cached = isset($m[2]) ? ($m[2] === 'true') : true;
        $call = 'asset(' . var_export($m[1], true) . ', ' . ($cached ? 'true' : 'false') . ')';

        $out .= $isCss
            ? 'echo \'<link rel="stylesheet" href="\' . htmlspecialchars(' . $call . ", ENT_QUOTES, 'UTF-8') . '\">';\n"
            : 'echo \'<script src="\' . htmlspecialchars(' . $call . ", ENT_QUOTES, 'UTF-8') . '\"></script>';\n";

        return $at + strlen($m[0]);
    }

    /**
     * <x:Comp …/> and <x:Comp …>slot</x:Comp>
     *
     * Slot content is compiled and captured through an output buffer, so
     * nesting works to any depth. Attribute values may themselves contain
     * {{ }}, which becomes string concatenation.
     *
     * @return int the offset to continue scanning from
     */
    private static function emitComponentTag(string $source, int $at, int &$seq, string &$out): int
    {
        if (!preg_match('/^<x:(\w+)/i', substr($source, $at, 64), $m)) {
            $out .= self::emitLiteral(substr($source, $at, 3));
            return $at + 3;
        }

        $name    = ucfirst($m[1]);
        $openEnd = self::endOfTag($source, $at);

        if ($openEnd === null) {                       // malformed: emit it literally
            $out .= self::emitLiteral(substr($source, $at));
            return strlen($source);
        }

        $openTag     = substr($source, $at, $openEnd - $at + 1);
        $selfClosing = str_ends_with(rtrim(substr($openTag, 0, -1)), '/');

        $properties = [];
        if (preg_match_all('/([\w:-]+)="([^"]*)"/', $openTag, $props, PREG_SET_ORDER)) {
            foreach ($props as $p) $properties[$p[1]] = $p[2];
        }

        $slotExpr = "''";
        $continue = $openEnd + 1;
        $literal  = $openTag;

        if (!$selfClosing) {
            $closeAt = self::matchingClose($source, $name, $openEnd + 1);
            if ($closeAt === null) {                   // unbalanced: emit it literally
                $out .= self::emitLiteral($openTag);
                return $openEnd + 1;
            }

            $slotSource = substr($source, $openEnd + 1, $closeAt - $openEnd - 1);
            $literal    = $openTag . $slotSource . '</x:' . $name . '>';
            $continue   = $closeAt + strlen('</x:' . $name . '>');

            $var  = '$__slot' . $seq++;
            $out .= "ob_start();\n" . self::compileNodes($slotSource, $seq) . $var . " = ob_get_clean();\n";
            $slotExpr = $var;
        }

        $parts = [];
        foreach ($properties as $key => $value) {
            $parts[] = var_export($key, true) . ' => ' . self::compileAttributeValue($value);
        }

        $out .= 'echo Template::tag(' . var_export($name, true) . ', ['
              . implode(', ', $parts) . '], ' . $slotExpr . ', ' . var_export($literal, true) . ");\n";

        return $continue;
    }

    /** An attribute value, possibly containing {{ }}, as a PHP expression. */
    private static function compileAttributeValue(string $value): string
    {
        if (!str_contains($value, '{{')) {
            return var_export($value, true);
        }

        $parts = [];
        $i = 0;
        $length = strlen($value);

        while ($i < $length) {
            $at = strpos($value, '{{', $i);
            if ($at === false) { $parts[] = var_export(substr($value, $i), true); break; }
            if ($at > $i) $parts[] = var_export(substr($value, $i, $at - $i), true);

            $close = strpos($value, '}}', $at + 2);
            if ($close === false) { $parts[] = var_export(substr($value, $at), true); break; }

            // stringify(), not out(): this value is being handed to a component
            // as data. The component escapes it when it writes it to the DOM.
            $expression = trim(substr($value, $at + 2, $close - $at - 2));
            $parts[] = preg_match('/^\$([A-Za-z_]\w*)$/', $expression, $m)
                ? 'Template::stringify($' . $m[1] . ' ?? null)'
                : 'Template::stringify(' . $expression . ')';
            $i = $close + 2;
        }

        return $parts === [] ? "''" : implode(' . ', $parts);
    }

    // -------------------------------------------------------------------------
    // Runtime helpers — called by compiled templates
    // -------------------------------------------------------------------------

    /**
     * Print a value into a template. THIS IS WHERE ESCAPING HAPPENS.
     *
     * The rule, in the order it is applied:
     *
     *   a plain STRING   is escaped — it is data until someone says otherwise
     *   an OBJECT        renders itself: a Component is markup by construction,
     *                    and Html is a string whose author marked it as markup
     *   an ARRAY         is each element by these same rules, concatenated
     *   null / false     print nothing
     *
     * Escaping by default is the whole point. Before this, {{$title}} wrote a
     * database value straight into the page and every setter in the kit had to
     * remember htmlspecialchars — fifty-odd calls, any one of which could be
     * forgotten, with no signal when it was. Now forgetting is safe and the
     * deliberate case is the one you have to write: raw().
     */
    public static function out(mixed $value): string
    {
        if ($value === null || $value === false) return '';

        if (is_string($value)) {
            // Most values contain nothing that needs converting, and this check
            // is far cheaper than the conversion.
            return strpbrk($value, "&<>\"'") === false
                ? $value
                : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string)$value : '';
        }

        if (is_array($value)) {
            $out = '';
            foreach ($value as $item) $out .= self::out($item);
            return $out;
        }

        return (string)$value;
    }

    /**
     * Coerce a value to string WITHOUT escaping.
     *
     * For values on their way into a component as data rather than into the
     * page as markup — an <x:Comp attr="{{$x}}"> attribute, say. Escaping there
     * would be wrong twice over: the component escapes when it writes the
     * attribute, so the value would come out double-escaped, and a component
     * that inspects the value would see entities instead of what was passed.
     */
    public static function stringify(mixed $value): string
    {
        if ($value === null || $value === false) return '';
        if (is_string($value)) return $value;
        if (is_object($value)) return method_exists($value, '__toString') ? (string)$value : '';
        if (is_array($value)) {
            $out = '';
            foreach ($value as $item) $out .= self::stringify($item);
            return $out;
        }
        return (string)$value;
    }

    /**
     * A template referenced a variable that was not supplied.
     *
     * Renders nothing and says so on the 'template' channel. The interpreter
     * used to render the literal source text ("$foo") instead, which looked
     * like a broken page rather than a missing value and was recorded nowhere.
     */
    public static function missing(string $name): mixed
    {
        Log::debug('template', 'undefined template variable', ['name' => $name]);
        return null;
    }

    /** Instantiate and render a component for an <x:…> tag. */
    public static function tag(string $name, array $properties, string $slot = '', string $literal = ''): string
    {
        $name = ucfirst($name);

        if (!class_exists($name)) {
            // Unknown component: leave the markup as-is rather than fatal.
            Log::warn('template', 'unknown component tag', ['tag' => 'x:' . $name]);
            return $literal;
        }

        // Already-rendered children: markup, not data.
        if ($slot !== '') $properties['slot'] = raw($slot);

        $component = $name::make($properties['id'] ?? '')->with($properties);

        // Only when there is one: addClass('') used to register an empty entry,
        // which rendered as a stray leading space in the class attribute.
        $class = $properties['class'] ?? '';
        if ($class !== '') $component->addClass($class);

        return (string)$component;
    }

    // -------------------------------------------------------------------------
    // Scanning helpers, shared by the compiler and the interpreter
    // -------------------------------------------------------------------------

    /** Offset of the '>' closing the tag that starts at $from, or null. */
    private static function endOfTag(string $html, int $from): ?int
    {
        $len = strlen($html);
        $quote = null;
        for ($i = $from; $i < $len; $i++) {
            $ch = $html[$i];
            if ($quote !== null) {
                if ($ch === $quote) $quote = null;
                continue;
            }
            if ($ch === '"' || $ch === "'") { $quote = $ch; continue; }
            if ($ch === '>') return $i;
        }
        return null;
    }

    /** Offset of the </x:Name> that closes the tag opened before $from, honouring nesting. */
    private static function matchingClose(string $html, string $name, int $from): ?int
    {
        $open = '<x:' . $name;
        $close = '</x:' . $name . '>';
        $depth = 0;
        $i = $from;

        while ($i < strlen($html)) {
            $nextOpen = stripos($html, $open, $i);
            $nextClose = stripos($html, $close, $i);
            if ($nextClose === false) return null;

            // Only count an opener if it is really this tag (not x:BoxWide).
            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $after = $html[$nextOpen + strlen($open)] ?? '';
                if ($after === '' || preg_match('/[\s>\/]/', $after)) $depth++;
                $i = $nextOpen + strlen($open);
                continue;
            }

            if ($depth === 0) return $nextClose;
            $depth--;
            $i = $nextClose + strlen($close);
        }
        return null;
    }

    private static function deferScripts(string $html): string
    {
        $scripts = [];
        if (preg_match_all('/<(script\w*)\b\s*([^>]*)>(.*?)<\/\1>/s', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) $scripts[] = $match[0];
        }

        foreach ($scripts as $script) {
            $html = str_replace($script, '', $html);
            $html = str_replace('</body>', $script . '</body>', $html);
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // The interpreter — fallback for a template that will not compile
    // -------------------------------------------------------------------------

    private static function interpret(string $template, array $data): string
    {
        // Substituted values are parked behind opaque placeholders. Everything
        // after this point sees placeholders where data used to be, and so
        // cannot interpret that data as template syntax. They are swapped back
        // in as the very last thing this method does.
        $protected = [];
        $processed = $template;

        if (str_contains($processed, '{{--')) {
            $processed = preg_replace('~\{\{--.*?--\}\}~s', '', $processed) ?? $processed;
        }

        if (str_contains($processed, '{{')) {
            $processed = preg_replace_callback('~\{{\s*(.+?)\s*\}}~is', function ($matches) use ($data, &$protected) {
                $value = self::safeEval($matches[1], $data);
                $token = "\x02bstn:" . count($protected) . "\x03";
                $protected[$token] = self::out($value);
                return $token;
            }, $processed);
            // Keep the fallback engine's escaping identical to the compiler's,
            // or a template that failed to compile would quietly become unsafe.
        }

        if (str_contains($processed, '{%')) {
            $processed = preg_replace_callback('~\{%\s*(.+?)\s*\%}~is', function ($matches) use ($data) {
                try {
                    return self::safeEvalMulti(htmlspecialchars_decode($matches[1], ENT_QUOTES), $data);
                } catch (Throwable $e) {
                    Log::exception('template', $e, ['block' => substr($matches[1], 0, 120)]);
                    return '';
                }
            }, $processed);
        }

        if (str_contains($processed, '@css') || str_contains($processed, '@js')) {
            $processed = preg_replace_callback('/@css\(\'([^\']+)\'(?:,\s*(true|false))?\)/', function ($m) {
                return '<link rel="stylesheet" href="' . htmlspecialchars(asset($m[1], !isset($m[2]) || $m[2] === 'true'), ENT_QUOTES, 'UTF-8') . '">';
            }, $processed);
            $processed = preg_replace_callback('/@js\(\'([^\']+)\'(?:,\s*(true|false))?\)/', function ($m) {
                return '<script src="' . htmlspecialchars(asset($m[1], !isset($m[2]) || $m[2] === 'true'), ENT_QUOTES, 'UTF-8') . '"></script>';
            }, $processed);
        }

        if (stripos($processed, '<x:') !== false) {
            $processed = self::resolveComponentTags($processed);
        }

        if ($protected) {
            $processed = strtr($processed, $protected);
        }

        return $processed;
    }

    /**
     * Resolve <x:Comp …/> and <x:Comp …>slot</x:Comp> into rendered components.
     *
     * A scanner rather than a regex: a non-greedy regex with no notion of depth
     * ends at the FIRST closing tag, so nesting a component inside another of
     * the same name produced mangled output.
     */
    private static function resolveComponentTags(string $html): string
    {
        $out = '';
        $cursor = 0;
        $length = strlen($html);

        while ($cursor < $length) {
            if (!preg_match('/<x:(\w+)/i', $html, $m, PREG_OFFSET_CAPTURE, $cursor)) {
                $out .= substr($html, $cursor);
                break;
            }

            $tagStart = (int)$m[0][1];
            $name = ucfirst($m[1][0]);
            $out .= substr($html, $cursor, $tagStart - $cursor);

            $openEnd = self::endOfTag($html, $tagStart);
            if ($openEnd === null) {
                $out .= substr($html, $tagStart);
                break;
            }

            $openTag = substr($html, $tagStart, $openEnd - $tagStart + 1);
            $selfClosing = str_ends_with(rtrim(substr($openTag, 0, -1)), '/');

            $properties = [];
            if (preg_match_all('/([\w:-]+)="([^"]*)"/', $openTag, $props, PREG_SET_ORDER)) {
                foreach ($props as $p) { $properties[$p[1]] = $p[2]; }
            }

            if ($selfClosing) {
                $slot = '';
                $literal = $openTag;
                $cursor = $openEnd + 1;
            } else {
                $closeAt = self::matchingClose($html, $name, $openEnd + 1);
                if ($closeAt === null) {
                    $out .= $openTag;
                    $cursor = $openEnd + 1;
                    continue;
                }
                $inner = substr($html, $openEnd + 1, $closeAt - $openEnd - 1);
                $slot = self::resolveComponentTags($inner);
                $literal = $openTag . $inner . '</x:' . $name . '>';
                $cursor = $closeAt + strlen('</x:' . $name . '>');
            }

            $out .= self::tag($name, $properties, $slot, $literal);
        }

        return $out;
    }

    /**
     * evaluating {{ $var }} with the help of data array content
     * @return mixed|null
     */
    public static function safeEval(string $statement, array $data = []): mixed
    {
        $var = trim($statement, '$');
        if (array_key_exists($var, $data)) {
            // Returned as-is, including arrays. This used to implode() an array
            // into a string here, which flattened away the difference between a
            // Component, a raw() string and plain text — so out() then escaped
            // the whole thing, markup and all, and the interpreter disagreed
            // with the compiler about what a list of mixed values renders as.
            return $data[$var];
        }

        $__eval = function () use ($statement, $data) {
            extract($data, EXTR_SKIP);
            try {
                return eval("return $statement;");
            } catch (Throwable $e) {
                return $statement;
            }
        };
        return $__eval();
    }

    /**
     * evaluating {% statement1; statement2; %} (execute this code block and return results)
     * @return mixed
     */
    public static function safeEvalMulti(string $code, array $data = []): mixed
    {
        $func = function () use ($code, $data) {
            ob_start();
            try {
                extract($data, EXTR_SKIP);
                eval($code);
                return ob_get_clean();
            } catch (Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        return $func();
    }

    // -------------------------------------------------------------------------
    // Views
    // -------------------------------------------------------------------------

    public static function view(string $view, array $data = []): string
    {
        self::$sections = [];
        self::$layout = null;

        $viewPath = SRC . '/app/Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            Log::warn('template', 'view not found', ['view' => $view]);
            return "View $view not found";
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $viewPath;
        $viewContent = ob_get_clean();

        // Run the view first: this populates the sections and records the layout.
        $processedView = self::process($viewContent, $data);

        if (self::$layout) {
            $layoutPath = SRC . '/app/Views/' . self::$layout . '.php';
            if (file_exists($layoutPath)) {
                ob_start();
                include $layoutPath;
                $layoutContent = ob_get_clean();

                // The sections are in self::$sections and are injected at @yield.
                return self::process($layoutContent, $data);
            }
            Log::warn('template', 'layout not found', ['layout' => self::$layout]);
        }

        return $processedView;
    }

    // -------------------------------------------------------------------------
    // Odds and ends
    // -------------------------------------------------------------------------

    public static function DomeElement($name, $id, $attr = [], $inner = ""): string
    {
        $elem = "<" . $name . " id=\"" . $id . "\"";
        foreach ($attr as $attrName => $attrValue) {
            $elem .= " $attrName=\"$attrValue\"";
        }
        $elem .= ">" . $inner;
        $elem .= "</" . $name . ">";
        return $elem;
    }

    public static function remove_attributes($html_text, $attribute_names)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html_text);

        foreach ($dom->getElementsByTagName('*') as $element) {
            foreach ($attribute_names as $attribute_name) {
                $element->removeAttribute($attribute_name);
            }
        }

        return $dom->saveHTML();
    }
}
