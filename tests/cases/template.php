<?php

/**
 * The template engine: what each construct does, and — most importantly — that
 * data can never become template syntax.
 */

group('template');

/** Render the same source through both engines and assert they agree. */
function both(string $source, array $data = []): string
{
    Template::$compile = true;
    $compiled = Template::process($source, $data);
    Template::$compile = false;
    $interpreted = Template::process($source, $data);
    Template::$compile = true;

    if ($compiled !== $interpreted) {
        fail("the compiler and the interpreter disagree\ncompiled:    {$compiled}\ninterpreted: {$interpreted}");
    }
    return $compiled;
}

test('{{$var}} prints a value', function () {
    same('<b>hi</b>', both('<b>{{$a}}</b>', ['a' => 'hi']));
});

test('{{ expression }} is evaluated', function () {
    same('<b>HI</b>', both('<b>{{ strtoupper($a) }}</b>', ['a' => 'hi']));
    same('<b>7</b>', both('<b>{{ 3 + 4 }}</b>', []));
});

test('an array is concatenated, null and false print nothing', function () {
    same('<b>ab</b>', both('<b>{{$a}}</b>', ['a' => ['a', 'b']]));
    same('<b></b>', both('<b>{{$a}}</b>', ['a' => null]));
    same('<b></b>', both('<b>{{$a}}</b>', ['a' => false]));
});

test('a variable that was never supplied prints nothing', function () {
    same('<b></b>', Template::process('<b>{{$nope}}</b>', []));
});

test('a component embedded in an expression renders', function () {
    contains('<svg', both('<div>{{ Icon::make("i")->set("user") }}</div>'));
});

test('DATA CANNOT BECOME TEMPLATE SYNTAX', function () {
    // The whole security property of the engine in one test. Each construct
    // must survive INTO THE OUTPUT unchanged, which is the proof it was never
    // treated as source. (Asserting the absence of "PWNED" would be wrong: it
    // is supposed to be there, as inert text.)
    $hostile = '{% echo "PWNED"; %}<x:Label/>{{ 1+1 }}';
    $out = both('<b>{{$a}}</b>', ['a' => $hostile]);

    // Two separate guarantees, and the output shows both:
    //   INERT    the constructs survive into the output instead of running
    //   ESCAPED  and they arrive HTML-escaped, so they are text on the page
    contains('{% echo', $out, 'a statement block in data must not execute');
    contains('&lt;x:Label/&gt;', $out, 'a component tag in data must not instantiate');
    contains('{{ 1+1 }}', $out, 'an expression in data must not be evaluated');
    lacks('<span', $out, 'nothing should have been instantiated');
    lacks('<x:', $out, 'and no raw tag should reach the page at all');
});

test('a plain string is ESCAPED — the default that makes forgetting safe', function () {
    same('<b>&lt;script&gt;alert(1)&lt;/script&gt;</b>',
        both('<b>{{$a}}</b>', ['a' => '<script>alert(1)</script>']));
    same('<b>a &amp; b</b>', both('<b>{{$a}}</b>', ['a' => 'a & b']));
});

test('escaping applies in attribute position too', function () {
    // The value below would otherwise close the attribute and add its own.
    $out = both('<div title="{{$a}}"></div>', ['a' => '" onmouseover="steal()']);
    lacks('onmouseover="steal()"', $out);
    contains('&quot;', $out);
});

test('a Component is markup by construction and is NOT escaped', function () {
    contains('<span', both('<b>{{$a}}</b>', ['a' => Label::make('x')->set('hi')]));
});

test('raw() opts a string out of escaping', function () {
    same('<b><em>markup</em></b>', both('<b>{{$a}}</b>', ['a' => raw('<em>markup</em>')]));
});

test('an array is handled element by element', function () {
    // The component renders, the raw string passes through, the plain string is
    // escaped — in one value.
    $out = both('<b>{{$a}}</b>', ['a' => [raw('<em>'), '<x>', raw('</em>')]]);
    same('<b><em>&lt;x&gt;</em></b>', $out);
});

test('a component setter no longer has to escape by hand', function () {
    contains('&lt;script&gt;', (string)Label::make('l')->set('<script>'));
    contains('<em>ok</em>', (string)Label::make('l')->html('<em>ok</em>'), 'html() still means markup');
});

test('{% %} runs statements and keeps what they echo', function () {
    same('<ul><li>1</li><li>2</li></ul>',
        both('<ul>{% foreach ($xs as $x) { echo "<li>$x</li>"; } %}</ul>', ['xs' => [1, 2]]));
});

test('{% %} no longer strips backslashes', function () {
    same("a\nb", Template::process('{% echo "a\nb"; %}', []));
});

test('control flow may span blocks', function () {
    same('<b>yes</b>', Template::process('<b>{% if ($ok) { %}yes{% } %}</b>', ['ok' => true]));
    same('<b></b>', Template::process('<b>{% if ($ok) { %}yes{% } %}</b>', ['ok' => false]));
});

test('{{-- comments --}} reach neither the output nor the compiled file', function () {
    same('<b>kept</b>', both('<b>{{-- a note --}}kept</b>'));
});

test('<x:Comp/> instantiates, and a slot nests to any depth', function () {
    contains('class="Label', both('<div><x:Label id="l"></x:Label></div>'));
    $nested = both('<div><x:Container id="o"><x:Container id="i">deep</x:Container></x:Container></div>');
    contains('id="o"', $nested);
    contains('id="i"', $nested);
    contains('deep', $nested);
});

test('an unknown <x:> tag is left alone rather than fatal', function () {
    contains('<x:NoSuchThing', both('<div><x:NoSuchThing a="1"/></div>'));
});

test('an attribute on <x:> may itself contain {{ }}', function () {
    $out = both('<div><x:Badge id="b" text="{{$t}}"/></div>', ['t' => 'Live']);
    contains('Live', $out);
});

test('a value passed through an <x:> attribute is never double-escaped', function () {
    // The value is DATA on its way into a component, so the compiler must hand
    // it over unescaped and let the component decide — otherwise the component's
    // own escaping runs over an already-escaped string.
    $out = both('<div><x:Badge id="b" text="{{$t}}"/></div>', ['t' => 'A & B']);
    lacks('&amp;amp;', $out, 'double escaping');
    lacks('&amp;lt;', both('<div><x:Badge id="b2" text="{{$t}}"/></div>', ['t' => '<i>']));
});

test('@css and @js become tags with versioned urls', function () {
    $out = both("@css('css/app.css')\n@js('js/ui.js')");
    contains('<link rel="stylesheet" href=', $out);
    contains('<script src=', $out);
    contains('app.css?v=', $out);
});

test('a template with no placeholders is returned untouched', function () {
    $static = '<div class="a"><span>nothing dynamic</span></div>';
    same($static, Template::process($static, []));
});

test('scripts are deferred to the end of a full document only', function () {
    $doc = '<html><head><script src="a.js"></script></head><body>x</body></html>';
    $out = Template::process($doc, []);
    ok(strpos($out, '<script') > strpos($out, '<body'), 'moved into the body');

    $fragment = '<div><script>void 0;</script></div>';
    same($fragment, Template::process($fragment, []), 'a fragment is left alone');
});

test('a template that cannot compile falls back instead of failing', function () {
    // "$a +" is not a valid expression; the compiler must refuse it and the
    // interpreter must still produce something rather than a fatal.
    $out = Template::process('<b>{{ $a + }}</b>', ['a' => 1]);
    ok(is_string($out), 'still returned a string');
});
