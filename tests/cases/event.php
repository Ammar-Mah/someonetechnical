<?php

/**
 * The Event object: the payload a handler returns.
 */

group('event');

/** The actions list from an Event, for brevity below. */
function actions(Event $e): array
{
    return $e->send()['actions'];
}

test('send() has the shape the client expects', function () {
    $payload = Event::make()->inner('#x', 'html')->send();
    same(['status', 'message', 'actions'], array_keys($payload));
    same('ok', $payload['status']);
    same([['name' => 'inner', 'target' => '#x', 'code' => 'html', 'more' => '']], $payload['actions']);
});

test('an empty Event is a valid "do nothing"', function () {
    same([], actions(Event::make()));
});

test('actions chain in order', function () {
    $list = actions(Event::make()->inner('#a', '1')->add('#b', 'c')->remove('#c'));
    same(['inner', 'add', 'remove'], array_column($list, 'name'));
});

test('status and message carry a failure back', function () {
    $payload = Event::make()->status('error')->message('nope')->send();
    same('error', $payload['status']);
    same('nope', $payload['message']);
});

test('remove() can be marked optional, and is not by default', function () {
    same(false, isset(actions(Event::make()->remove('#x'))[0]['optional']));
    same(true, actions(Event::make()->remove('#x', true))[0]['optional']);
});

test('call() names a function and joins its parameters', function () {
    $one = actions(Event::make()->call('toast', ['Saved', 'success']))[0];
    same('call', $one['name']);
    same('body', $one['target']);
    same('toast|Saved,success', $one['code']);
    same(0, $one['delay']);

    same(250, actions(Event::make()->call('toast', ['x'], 250))[0]['delay']);
});

test('setValue carries the event to dispatch, and value() does not', function () {
    same('change', actions(Event::make()->setValue('#x', 'v'))[0]['type']);
    same('', actions(Event::make()->setValue('#x', 'v', ''))[0]['type']);
    same('value', actions(Event::make()->value('#x', 'v'))[0]['name']);
});

test('more() attaches a template to the previous action', function () {
    $one = actions(Event::make()->inner('#x', '[]')->more('<li>{{$a}}</li>'))[0];
    same('<li>{{$a}}</li>', $one['more']);
});

test('more() on an empty Event does nothing rather than erroring', function () {
    same([], actions(Event::make()->more('<li></li>')));
});

test('the actions with no client implementation are gone', function () {
    foreach (['filter', 'filterValues', 'goto', 'transfer', 'submit'] as $dead) {
        not_ok(method_exists(Event::class, $dead), $dead . '() should not exist — it never worked');
    }
});

test('refresh carries an empty target, which the client must special-case', function () {
    same('refresh', actions(Event::make()->refresh())[0]['name']);
    same('', actions(Event::make()->refresh())[0]['target']);
});
