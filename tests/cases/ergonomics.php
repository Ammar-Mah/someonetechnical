<?php

/**
 * The developer-facing conveniences: referring to a handler by class, and
 * targeting a component instead of writing its selector by hand.
 */

class T_Handlers extends Handler
{
    protected string $template = '<div></div>';
    public function save(Request $r) { return Event::make()->send(); }
    public function remove() { return Event::make()->send(); }
}

group('ergonomics');

test('a handler may be named as [Class::class, method]', function () {
    same('T_Handlers.save()', Component::handler([T_Handlers::class, 'save']));
    same('T_Handlers.save(1,2)', Component::handler([T_Handlers::class, 'save', [1, 2]]));
});

test('a handler string is still accepted unchanged', function () {
    same('AppHandler.save()', Component::handler('AppHandler.save()'));
    same('$toggle(#a,b)', Component::handler('$toggle(#a,b)'));
});

test('both forms produce the same attribute', function () {
    $byString = (string)Label::make('a')->onClick('T_Handlers.save()');
    $byClass  = (string)Label::make('a')->onClick([T_Handlers::class, 'save']);
    same($byString, $byClass);
});

test('the class form works everywhere a handler is taken', function () {
    contains('T_Handlers.save()', (string)Form::make('f')->submit([T_Handlers::class, 'save']));
    contains('T_Handlers.save()', (string)Picker::make('p')->set(['a' => 'A'])->onChange([T_Handlers::class, 'save']));
    contains('T_Handlers.save()', (string)Tab::make('t')->set('T')->onOpen([T_Handlers::class, 'save']));
    contains('T_Handlers.save()', (string)Tab::make('t2')->set('T')->closable([T_Handlers::class, 'save']));
});

test('a malformed handler reference is refused rather than rendered', function () {
    same('', Component::handler([]));
    same('', Component::handler(['OnlyAClass']));
});

test('an Event can target a component instead of a selector string', function () {
    $label = Label::make('greeting');
    $byComponent = Event::make()->inner($label, 'hi')->send();
    $byString    = Event::make()->inner('#greeting', 'hi')->send();
    same($byString, $byComponent);
});

test('component targeting works for every action that takes a selector', function () {
    $c = Container::make('region');
    foreach ([
        fn($t) => Event::make()->inner($t, 'x'),
        fn($t) => Event::make()->outer($t, 'x'),
        fn($t) => Event::make()->append($t, 'x'),
        fn($t) => Event::make()->remove($t),
        fn($t) => Event::make()->add($t, 'c'),
        fn($t) => Event::make()->strip($t, 'c'),
        fn($t) => Event::make()->toggle($t, 'c'),
        fn($t) => Event::make()->focus($t),
        fn($t) => Event::make()->clear($t),
        fn($t) => Event::make()->scrollView($t),
        fn($t) => Event::make()->setValue($t, 'v'),
    ] as $build) {
        same('#region', $build($c)->send()['actions'][0]['target']);
    }
});

test('selectlast still takes a form name, not a selector', function () {
    same('tab-group', Event::make()->selectlast('tab-group')->send()['actions'][0]['target']);
});

test('a hand-written selector is left exactly as written', function () {
    same('#a > .b:not(.c)', Event::make()->inner('#a > .b:not(.c)', 'x')->send()['actions'][0]['target']);
});
