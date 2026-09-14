<?php

/**
 * Component rendering: which properties reach the DOM, how they are escaped,
 * and how the root element is decorated.
 */

class T_Plain extends Component
{
    public $shown = "in the template";
    public $hidden = "not in the template";
    protected string $template = '<div class="base">{{$shown}}</div>';
}

class T_Exposed extends Component
{
    public $a = "1";
    public $b = "2";
    public $key = "k";
    protected array $expose = ['a'];
    protected string $template = '<div></div>';
}

class T_Raw extends Component
{
    public $markup = '<b>bold</b>';
    protected array $rawAttributes = ['markup'];
    protected string $template = '<div></div>';
}

class T_Private extends Component
{
    private string $secret = "never emitted";
    public $visible = "yes";
    protected string $template = '<div></div>';
    public function secret(): string { return $this->secret; }
}

/** The shape that used to break: a public property named like a framework internal. */
class T_Shadow extends Component
{
    public $actions = "MY OWN VALUE";
    public $classes = "also mine";
    public $styles  = "and mine";
    protected string $template = '<div>{{$actions}}|{{$classes}}|{{$styles}}</div>';
}

class T_SelfClosing extends Component
{
    public $value = "";
    public $extra = "";
    protected string $template = '<input class="in" value="{{$value}}" {{$extra}}>';
}

group('component');

test('make() gives a random id, or the one you name', function () {
    ok(preg_match('/^t_plain-\d{7}$/', T_Plain::make()->id) === 1, 'random id shape');
    same('mine', T_Plain::make('mine')->id);
    same('T_Plain-mine', T_Plain::make('mine', true)->id);
});

test('the class name is always on the root element', function () {
    contains('class="T_Plain base"', (string)T_Plain::make('x'));
});

test('a property the template renders is NOT also written as an attribute', function () {
    $html = (string)T_Plain::make('x');
    contains('in the template', $html);
    lacks('shown=', $html, 'the template already printed it');
});

test('a property the template ignores IS written as an attribute', function () {
    contains('hidden="not in the template"', (string)T_Plain::make('x'));
});

test('id and comp are always present — the client routes on them', function () {
    $html = (string)T_Plain::make('x');
    contains('id="x"', $html);
    contains('comp="T_Plain"', $html);
});

test('$expose narrows what reaches the DOM, but never id, comp or key', function () {
    $html = (string)T_Exposed::make('x');
    contains('a="1"', $html);
    lacks('b="2"', $html, 'b is not exposed');
    contains('key="k"', $html);
    contains('comp="T_Exposed"', $html);
    contains('id="x"', $html);
});

test('attribute values are escaped', function () {
    $html = (string)T_Plain::make('x')->with(['hidden' => 'a"b<c&d']);
    contains('hidden="a&quot;b&lt;c&amp;d"', $html);
});

test('$rawAttributes opts a property out of escaping', function () {
    contains('markup="<b>bold</b>"', (string)T_Raw::make('x'));
});

test('a private subclass property is invisible to the attribute pass', function () {
    $c = T_Private::make('x');
    same('never emitted', $c->secret(), 'still readable inside the class');
    lacks('secret=', (string)$c, 'but never written to the DOM');
});

test('a property named like a framework internal belongs to the subclass', function () {
    // Component keeps its own state in _actions/_classes/_styles precisely so
    // that this works. It used to render the event-handler array instead.
    $html = (string)T_Shadow::make('x')->onClick('X.y()');
    contains('MY OWN VALUE|also mine|and mine', $html);
    lacks('xhandle(\'X.y()\')</div>', $html, 'the handler must not leak into the body');
    contains('onclick="xhandle(&#039;X.y()&#039;)"', $html, 'it belongs in the attribute');
});

test('addClass and addStyle merge into what the template already set', function () {
    $html = (string)T_Plain::make('x')->addClass('one')->addClass('two')->addStyle('color', 'red');
    contains('class="two one T_Plain base"', $html);
    contains('style="color:red;"', $html);
});

test('a bare attribute in the template survives decoration', function () {
    $html = (string)T_SelfClosing::make('x')->with(['extra' => 'disabled']);
    contains('disabled', $html);
    contains('id="x"', $html);
    contains('class="T_SelfClosing in"', $html);
});

test('slot content is never written as an attribute', function () {
    $html = (string)T_Plain::make('x')->slot('<p>a very large slot</p>');
    lacks('slot=', $html);
});

test('an event handler becomes an inline attribute', function () {
    contains('onclick="xhandle(&#039;App.save()&#039;)"', (string)T_Plain::make('x')->onClick('App.save()'));
    contains('onchange="xhandle(&#039;App.pick()&#039;)"', (string)T_Plain::make('x')->on('change', 'App.pick()'));
});

test('a $-prefixed handler stays client-side', function () {
    contains('onclick="toggle(#menu,hidden)"', (string)T_Plain::make('x')->onClick('$toggle(#menu,hidden)'));
});
