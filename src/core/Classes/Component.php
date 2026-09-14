<?php
#[\AllowDynamicProperties]
class Component
{
    /*
     * Parent class for every application component. Subclasses supply a
     * template and some public properties; this class turns them into HTML and
     * decorates the root element so the client can route interactions back.
     */

    /** @param $id Component ID */
    public $id="";
    public $slot="";

    /** @param string $template html code representing component structure */
    protected string $template="";

    /** @param string $compiled result of processing the template with this object's data */
    private string $_compiled="";

    /**
     * Never written to the DOM as an attribute.
     *
     * 'slot' is here because slot content is markup, and markup that a
     * template does not reference would otherwise be HTML-escaped into an
     * attribute — a component given a 5 KB slot it never renders would carry
     * all 5 KB on its root element, and post it back on every interaction.
     *
     * The underscored entries are this class's own internals; see the note on
     * their declarations below.
     */
    protected array $excluded = [
        'template', 'slot', 'rawAttributes',
        '_compiled', '_comp', '_mainTag', '_rootStart', '_actions', '_classes', '_styles',
    ];

    /**
     * Property names whose value is deliberately markup and must not be
     * HTML-escaped when written to the root element. Keep this list short.
     */
    protected array $rawAttributes = [];

    /**
     * Opt-in allowlist of which properties reach the DOM as attributes.
     *
     * By default every scalar property that the template does not reference is
     * written to the root element, and the client posts all of them back on
     * every interaction — so component state round-trips through the DOM in both
     * directions. A component that declares $expose emits only those names,
     * which is how you trim a wide component down.
     *
     * Left empty the old behaviour applies unchanged, so declaring it is safe to
     * do one component at a time: handlers reading an attribute that is no
     * longer sent would otherwise break silently.
     */
    protected array $expose = [];

    /*
     * This class's own state, underscored so it cannot collide with a property
     * a subclass wants to call its own.
     *
     * These were named actions, classes, styles, comp, mainTag, compiled and
     * rootStart, and being PRIVATE is precisely what made that dangerous: a
     * subclass declaring `public $actions` does not override a private parent
     * property, it gets a second slot with the same name — and get_object_vars()
     * inside this class then hands the template the PARENT's value. A Form
     * whose template said {{$actions}} rendered the event-handler array, so
     * "xhandle('AppHandler.save()')" appeared on the page as text where its
     * buttons should have been. Nothing warned; the markup simply came out wrong.
     *
     * Renaming them removes the whole class of bug: the remaining framework
     * properties (id, slot, template, excluded, rawAttributes, expose) are all
     * public or protected, so a subclass that declares one OVERRIDES it, which
     * is both visible and intended.
     */

    /** Cached offset of the root opening tag, reset for each parse(). */
    private ?int $_rootStart = null;

    /** The class name, emitted as the `comp` attribute the client routes on. */
    private string $_comp="";

    /** The root element's tag name, read once from the template. */
    private string $_mainTag="";

    private array $_actions=[];

    private array $_classes=[];
    private array $_styles=[];

    /**
     * Per-template memo of the two things that only depend on the template
     * source: the event-attribute rewrite, and which variable names it
     * mentions.
     *
     * Both used to be recomputed on every single render — a regex pass and one
     * str_contains per property — even though a template is a class constant in
     * all but name. Keyed by the template string itself, which is cheap because
     * PHP memoises a string's hash inside the zend_string.
     *
     * @var array<string, array{0:string, 1:array<string,bool>}>
     */
    private static array $templateInfo = [];

    /**
     * basic constructor, passing id (empty id will generate a unique random id)
     * function mount() will also be executed to perform any initialization for the components
     * @param string $id passed id
     */
    public function __construct(string $id="",$data=[],bool $fullName=false)
    {
        $this->id=$id;
        $this->_comp=get_called_class();
        if ($id=="") $this->id=strtolower(get_called_class())."-".mt_rand(1000000,9999999);
        if ($fullName) $this->id=get_called_class()."-".$this->id;
        $this->with($data);
        $this->template=trim($this->template);
        // Splitting on the first space produced "div>\n<span>" for a root tag
        // written as plain "<div>", after which attribute injection missed and
        // fell back to offset 0 — attributes landed in the element's text and
        // the root got no usable id.
        $this->_mainTag = preg_match('/^<\s*([a-zA-Z][\w:-]*)/', $this->template, $m) ? $m[1] : '';
        $this->mount();
    }

    public function mount(){}

    /**
     * just a helper function to generate new component object without using : "new"
     * @param string $id passed id
     * @return static
     */
    public static function make(string $id="",bool $fullName=false): static
    {
        return new static($id,[],$fullName);
    }

    /**
     * filling data passed to that component, each data item will be converted to object property
     * parse() function will use this data to process the html structure and fill variables accordingly
     * @param array $data
     * @return $this
     */
    public function with(array $data = []):Component{

        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }

    /**
     * The children of this component — whatever its template prints as {{$slot}}.
     *
     * Takes a string, a component, or a list of either. Components stringify
     * themselves, so no cast is needed:
     *
     *     Card::make()->slot([Label::make()->set('a'), Badge::make()->set('b')])
     */
    public function slot(string|array|Component $slot): static
    {
        if (is_array($slot)) $slot = implode("", array_map('strval', $slot));

        // Children are markup by definition, so the slot is never escaped. If
        // you are putting user text in here, wrap it in a component that
        // escapes — Label::set() — or e() it yourself.
        $this->slot = raw((string)$slot);
        return $this;
    }

    /**
     * The same thing, by the name that reads better at some call sites.
     *
     * Several components used to define their own content(), each with slightly
     * different semantics — so you had to remember which of set(), slot() and
     * content() a given component wanted. One alias here means every component
     * takes both, and the rule is simply: set() is the component's own value,
     * slot()/content() is what goes inside it.
     */
    public function content(string|array|Component $content): static
    {
        return $this->slot($content);
    }

    /**
     * The event rewrite and the variable-name set for a template source.
     *
     * @return array{0:string,1:array<string,bool>}
     */
    private static function templateInfo(string $template): array
    {
        if (isset(self::$templateInfo[$template])) {
            return self::$templateInfo[$template];
        }

        // Operates on template source only, so a data value that happens to
        // contain xon:… can never become a live event handler. Guarded, because
        // most templates carry no event attributes and the guard is far cheaper
        // than discovering that with a regex.
        $rewritten = str_contains($template, 'xon:')
            ? preg_replace_callback(
                '/xon:([a-zA-Z]+)="([^"]+)"/',
                static fn(array $m): string => sprintf('on%s="xhandle(\'%s\')"', ucfirst($m[1]), $m[2]),
                $template
              )
            : $template;

        // Which properties the template renders itself, and which therefore
        // must not ALSO be written to the root element as attributes.
        //
        // This used to ask str_contains($template, '$'.$name) per property per
        // render — a substring test, so a property named `a` was silently
        // suppressed by an unrelated `$abc` in the template. Collecting the
        // actual variable tokens once is both faster and exact.
        $refs = [];
        if (str_contains($template, '$') && preg_match_all('/\$([A-Za-z_]\w*)/', $template, $m)) {
            $refs = array_fill_keys($m[1], true);
        }

        // A component may swap its template at runtime, so this is keyed by
        // content rather than by class. Bound it in case something generates
        // templates dynamically.
        if (count(self::$templateInfo) > 500) self::$templateInfo = [];

        return self::$templateInfo[$template] = [$rewritten, $refs];
    }

    /**
     * process the html structure and fill variables based on this object property
     * this will also include adding new properties on html structure (data-events)
     * this (data-event) will hold information needed for JS to handle user interactions
     * @return $this
     */
    protected function parse():Component{

        [$template, $refs] = self::templateInfo($this->template);

        // get current component properties as array
        $data = get_object_vars($this);

        // Render first, decorate afterwards.
        //
        // Attribute values used to be spliced into the template string before
        // this call, which meant any database text landing in an unreferenced
        // property was handed to the template engine as source — "{% … %}" in a
        // record's description reached eval(). Nothing but the component's own
        // template reaches Template::process now.
        $html = Template::process($template, $data);

        // The rendered string is new, so any cached root-tag offset is stale.
        $this->_rootStart = null;

        // ---- Work out every attribute this element should carry -------------
        //
        // Collected first and written in ONE pass. Each attribute used to be
        // spliced in separately, and every splice re-scanned the open tag and
        // rebuilt the whole string: O(attributes x length) per render, which
        // profiling put at roughly a quarter of the cost of rendering a small
        // component.

        // Appended to whatever the template already has, rather than replacing it.
        $appendable = ['class' => [get_called_class()], 'style' => []];
        foreach ($this->_classes as $class) $appendable['class'][] = $class;
        foreach ($this->_styles as $style)  $appendable['style'][] = $style;

        // Written only when the template does not already set them.
        //
        // id and comp are added here rather than picked up from the property
        // sweep below, because the client needs both to route an interaction
        // back to the right handler — so neither may be suppressed by $expose,
        // and `comp` is no longer a property name at all.
        $plain = ['id' => $this->id, 'comp' => $this->_comp];

        $excluded  = array_flip($this->excluded);
        $restricted = $this->expose !== [];
        // `key` is how the client identifies a list child when patching, so it
        // survives $expose too.
        $exposed = $restricted ? array_flip($this->expose) + ['key' => 0] : [];

        foreach ($data as $name => $value) {
            if (isset($excluded[$name])) continue;
            // When a component declares $expose, only those names reach the DOM.
            if ($restricted && !isset($exposed[$name])) continue;
            // Rendered by the template itself — writing it again would duplicate it.
            if (isset($refs[$name])) continue;
            if (is_array($value) || is_object($value)) continue;
            // strict: an integer 0 used to be emitted as an empty attribute
            $plain[$name] = $value === null ? "" : (string)$value;
        }

        foreach ($this->_actions as $name => $action) {
            $plain[$name] = $action;
        }

        $this->_compiled = $this->decorate($html, $plain, $appendable);

        return $this;
    }

    /**
     * Write the collected attributes onto the root element in a single pass.
     *
     * @param array<string,string>        $plain      set only when absent
     * @param array<string,array<string>> $appendable merged into an existing value
     */
    private function decorate(string $html, array $plain, array $appendable): string
    {
        $root = $this->rootTag($html);
        if ($root === null) return $html;           // nothing sensible to decorate

        [$start, $length] = $root;
        $open    = substr($html, $start, $length);
        $present = self::scanAttributes($open);

        // Edits are recorded as (absolute offset, text) and applied from the
        // highest offset down, so earlier insertions cannot shift later ones.
        $edits = [];
        $fresh = '';

        foreach ($appendable as $name => $values) {
            $values = array_filter($values, static fn($v) => $v !== '' && $v !== null);
            if ($values === []) continue;

            // Reversed because each value used to be spliced in front of the
            // previous one, and the resulting order is what stylesheets and
            // snapshots already expect.
            $merged = implode(' ', array_reverse($values));

            if (array_key_exists($name, $present)) {
                if ($present[$name] === null) continue;   // bare attribute: nothing to merge into
                $edits[] = [$start + $present[$name], $this->escapeAttr($name, $merged) . ' '];
            } else {
                $fresh .= ' ' . $name . '="' . $this->escapeAttr($name, $merged) . '"';
            }
        }

        foreach ($plain as $name => $value) {
            if (array_key_exists($name, $present)) continue;     // the template said it first
            $fresh .= ' ' . $name . '="' . $this->escapeAttr($name, (string)$value) . '"';
        }

        if ($fresh !== '') {
            $edits[] = [$start + 1 + strlen($this->_mainTag), $fresh];
        }

        if (count($edits) > 1) {
            usort($edits, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
        }
        foreach ($edits as [$offset, $text]) {
            $html = substr_replace($html, $text, $offset, 0);
        }

        return $html;
    }

    /**
     * The attributes already present on an opening tag.
     *
     * A scanner rather than a regex, because an opening tag can legitimately
     * carry bare attributes (`<input disabled>`, which is what a template
     * writing `{{$disabled}}` produces) alongside quoted ones, and a value can
     * contain anything at all.
     *
     * @return array<string,int|null> name => offset of its value within $open,
     *                                or null when the attribute is bare
     */
    private static function scanAttributes(string $open): array
    {
        static $space = " \t\n\r\0\x0B";

        $found = [];
        $length = strlen($open);

        // Skip '<' and the tag name.
        $i = 1 + strcspn($open, $space . '>/', 1);

        while ($i < $length) {
            $i += strspn($open, $space . '/', $i);
            if ($i >= $length || $open[$i] === '>') break;

            $nameStart = $i;
            $i += strcspn($open, $space . '=>/', $i);
            $name = strtolower(substr($open, $nameStart, $i - $nameStart));
            if ($name === '') { $i++; continue; }

            $after = $i + strspn($open, $space, $i);
            if ($after >= $length || $open[$after] !== '=') {
                $found[$name] = null;                       // bare attribute
                $i = $after;
                continue;
            }

            $after += 1 + strspn($open, $space, $after + 1);
            if ($after < $length && ($open[$after] === '"' || $open[$after] === "'")) {
                $quote = $open[$after];
                $found[$name] = $after + 1;
                $end = strpos($open, $quote, $after + 1);
                $i = $end === false ? $length : $end + 1;
            } else {
                $found[$name] = $after;                     // unquoted value
                $i = $after + strcspn($open, $space . '>', $after);
            }
        }

        return $found;
    }

    /** Escape an attribute value unless the component declared it as raw markup. */
    private function escapeAttr(string $name, string $value): string
    {
        if ($this->rawAttributes !== [] && in_array($name, $this->rawAttributes, true)) return $value;
        // Most values (ids, dates, status names, class lists) contain nothing
        // that needs escaping, and the check is far cheaper than the conversion.
        return strpbrk($value, "&<>\"'") === false
            ? $value
            : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * overriding toString magic function (echo component object will return its processed html code)
     * @return string
     */
    public function __toString(): string
    {
        $this->parse();
        return $this->_compiled;
    }

    /**
     * Locate the component's root opening tag inside $code.
     * @return array{0:int,1:int}|null [offset, length] of "<tag …>"
     */
    private function rootTag(string $code): ?array
    {
        if ($this->_mainTag === '') return null;

        if ($this->_rootStart === null) {
            $tagLength = strlen($this->_mainTag);

            // The template is trimmed and opens with its own root tag, so the
            // rendered string almost always does too. Confirming that costs two
            // string comparisons; the regex below costs far more, and it was
            // being paid on every render of every component.
            if (strncasecmp($code, '<' . $this->_mainTag, $tagLength + 1) === 0
                && strpbrk($code[$tagLength + 1] ?? '>', " \t\n\r>/") !== false) {
                $this->_rootStart = 0;
            } else {
                // (?=[\s>\/]) so <div> and <div class=…> both match but <divider> does not
                if (!preg_match('/<'.preg_quote($this->_mainTag,'/').'(?=[\s>\/])/i', $code, $m, PREG_OFFSET_CAPTURE)) {
                    return null;
                }
                $this->_rootStart = (int)$m[0][1];
            }
        }
        $start = $this->_rootStart;
        $end = strpos($code, '>', $start);
        if ($end === false) return null;
        return [$start, $end - $start + 1];
    }

    /**
     * Normalise a handler reference into the string the client posts back.
     *
     *   'AppHandler.save()'                 → used as written
     *   [AppHandler::class, 'save']         → 'AppHandler.save()'
     *   [AppHandler::class, 'save', [1, 2]] → 'AppHandler.save(1,2)'
     *
     * Prefer the array form. ::class is resolved by the editor, survives a
     * rename, and — because this checks the method exists while DEBUG_MODE is
     * on — a typo is reported when the page renders rather than when somebody
     * clicks the thing three screens later.
     */
    public static function handler(string|array $handler): string
    {
        if (is_string($handler)) return $handler;

        $class  = $handler[0] ?? '';
        $method = $handler[1] ?? '';
        if (is_object($class)) $class = get_class($class);

        if (!is_string($class) || $class === '' || !is_string($method) || $method === '') {
            Log::warn('ui', 'a handler reference was not [Class::class, "method"]', ['given' => $handler]);
            return '';
        }

        if (function_exists('isDebugMode') && isDebugMode()
            && class_exists($class) && !method_exists($class, $method)) {
            Log::warn('ui', 'handler does not exist', ['class' => $class, 'method' => $method]);
        }

        $params = $handler[2] ?? [];
        return $class . '.' . $method . '(' . implode(',', array_map('strval', (array)$params)) . ')';
    }

    /**
     * Run $handler when $eventType happens on this component.
     *
     * A handler prefixed with "$" is executed by the client and never reaches
     * the server — see the surface actions in the manual.
     */
    public function action(string $eventType, string|array $handler)
    {
        $handler = self::handler($handler);

        if (str_starts_with($handler,"$")) $this->_actions["on".$eventType]= trim($handler,"$");
        else $this->_actions["on".$eventType]= "xhandle('".$handler."')";
        return $this;
    }

    public function on(string $eventType, string|array $handler, $js=false)
    {
        $handler = self::handler($handler);
        return $this->action($eventType,$js?"$".$handler:$handler);
    }

    public function onClick(string|array $handler)
    {
        return $this->action("click",$handler);
    }

    public function addClass($className)
    {
        $this->_classes[]=$className;
        return $this;
    }

    public function removeClass($className)
    {
        $this->_classes=array_diff($this->_classes, array($className));
        $this->template= str_replace($className,"",$this->template);
        return $this;
    }

    public function addStyle($name,$value="")
    {
        if ($value=="") $this->_styles[]=$name.";";
        else $this->_styles[]=$name.":".$value.";";
        return $this;
    }

    public function template()
    {
        return $this->template;
    }
}
