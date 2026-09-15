<?php

/**
 * A rendering snapshot of the whole component kit.
 *
 * The point is not that any particular byte is correct — it is that a change
 * to the framework does not silently change what components produce. Every
 * case here is deliberately awkward: quotes and angle brackets in values,
 * empty states, nesting, disabled and invalid states.
 *
 * Attribute order and random ids are normalised away by the runner, so this
 * only fails when something meaningful moves.
 *
 *   php tests/run.php --update   after an intended change
 */

group('render');

$cases = [
    'Label'         => fn() => Label::make('l1')->set('Hello & <world>')->icon('user')->muted(),
    'Label-html'    => fn() => Label::make('l2')->html('<em>markup</em>'),
    'Label-trans'   => fn() => Label::make('l3')->t('Save')->bold()->small(),
    'Badge'         => fn() => Badge::make('b1')->set('Open')->tone('success')->dot(),
    'Badge-colour'  => fn() => Badge::make('b2')->set('Tag')->color('#7c3aed'),
    'Icon'          => fn() => Icon::make('i1')->set('trash', 'lg')->tooltip('Delete'),
    'Icon-unknown'  => fn() => Icon::make('i2')->set('no-such-icon'),
    'Button'        => fn() => Button::make('bt1')->set('Save', 'save')->primary()->confirm('Sure?'),
    'Button-plain'  => fn() => Button::make('bt2')->set('Go'),
    'Button-form'   => fn() => Button::make('bt3')->set('Save')->form('f1')->disable(),
    'Button-danger' => fn() => Button::make('bt4')->set('Delete', 'trash')->danger()->small()->block(),
    'IconButton'    => fn() => IconButton::make('ib1')->set('trash')->danger()->tooltip('Remove')
                                 ->with(['record' => 7])->onClick('AppHandler.x()'),
    'TextInput'     => fn() => TextInput::make('ti1')->set('a "quoted" value', 'Type…', '9')
                                 ->type('number')->min(0)->max(10)->required(),
    'TextInput-2'   => fn() => TextInput::make('ti2')->set('')->disable()->plain()->invalid(),
    'TextArea'      => fn() => TextArea::make('ta1')->set("line\nline", 'Notes')->rows(6)->name('n'),
    'CheckBox'      => fn() => CheckBox::make('cb1')->set('Done & dusted', true)->name('done'),
    'Select'        => fn() => Select::make('s1')->set(['a' => 'A & B', 'b' => 'B'], 'b', 'Pick')->name('s'),
    'Select-empty'  => fn() => Select::make('s2')->set([], null),
    'Picker'        => fn() => Picker::make('p1')->name('tags')->searchable()
                                 ->set(['x' => 'X & Y', 'y' => 'Y'], ['x'])->onChange('A.b()'),
    'Picker-single' => fn() => Picker::make('p2')->single()->placeholder('Pick one')->set(['x' => 'X'], 'x'),
    'Picker-none'   => fn() => Picker::make('p3')->set(['x' => 'X'], []),
    'Field'         => fn() => Field::make('f1')->label('Email')->hint('h')->error('bad')
                                 ->slot(TextInput::make('in')->name('email')),
    'Field-clean'   => fn() => Field::make('f2')->label('Email')->error('')
                                 ->slot(TextInput::make('in2')->name('email')),
    'Form'          => fn() => Form::make('fo1')->content([TextInput::make('q')->name('q')])
                                 ->actions(Button::make('sb')->set('Go')->primary())->submit('A.b()'),
    'Form-row'      => fn() => Form::make('fo2')->layout('row')
                                 ->content([TextInput::make('q2')->name('q'), Button::make('sb2')->set('Go')]),
    'Container'     => fn() => Container::make('c1')->stack('row', 3)->scrollable()->slot('inner'),
    'Card'          => fn() => Card::make('cd1')->title('Team & co', 'aside')->slot('body')->footer('foot')->flush(),
    'Card-bare'     => fn() => Card::make('cd2')->slot('just a body'),
    'Table'         => fn() => Table::make('t1')->set(['A', ['B', 'is-numeric']], [
                                 ['key' => 1, 'cells' => ['x', ['html' => 'y', 'class' => 'z']]],
                                 ['key' => 2, 'class' => 'r', 'cells' => ['p', 'q']],
                               ]),
    'Table-empty'   => fn() => Table::make('t2')->set(['A'], [])->empty('None'),
    'ListItem'      => fn() => ListItem::make('li1')->set('Items', 'list')
                                 ->badge(Badge::make('bg')->set('3'))->active(),
    'Tabs'          => fn() => Tabs::make('tb1')->set([
                                 Tab::make('t-a')->set('A', 'home')->content('AA')->checked(),
                                 Tab::make('t-b')->set('B')->onOpen('A.b()')->closable(),
                                 Tab::make('t-c')->set('C')->closable('A.close()'),
                               ]),
    'Modal'         => fn() => Modal::make('m1')->title('T')->slot('body')
                                 ->actions(Button::make('mb')->set('OK'))->width('600px'),
    'Modal-noclose' => fn() => Modal::make('m2')->title('T', false)->slot('body'),
    'Slider'        => fn() => Slider::make('sl1')->title('Panel')->slot('body')->fromStart(),
    'Alert'         => fn() => Alert::make('a1')->danger('Nope & nope'),
    'Alert-html'    => fn() => Alert::make('a2')->html('<b>markup</b>'),
    'Loading'       => fn() => Loading::make('lo1')->message('Loading…'),
    'Loading-bar'   => fn() => Loading::make('lo2')->bar(),
    'Avatar'        => fn() => Avatar::make('av1')->set('Ada Lovelace', 'lg'),
    'Avatar-img'    => fn() => Avatar::make('av2')->set('Bob')->image('img/x.png'),
    'Avatar-empty'  => fn() => Avatar::make('av3')->set(''),
    'Avatars'       => fn() => Avatars::make('avs')->set(['A B', 'C D', 'E F', 'G H'], 'sm', 2),
    'Progress'      => fn() => Progress::make('pr1')->set(7, 10)->caption('7 of 10'),
    'Progress-seg'  => fn() => Progress::make('pr2')->segments([
                                 ['value' => 5, 'color' => 'var(--success)', 'label' => 'Done'],
                                 ['value' => 3, 'color' => '#d97706', 'label' => 'Doing'],
                               ]),
    'Progress-zero' => fn() => Progress::make('pr3')->set(0, 0),
    'Expander'      => fn() => Expander::make('ex1')->title('Group', 'folder')
                                 ->tools(IconButton::make('et')->set('plus'))->slot('rows')->open(),
    'Logo'          => fn() => Logo::make('lg1'),
    'Logo-mark'     => fn() => Logo::make('lg2')->markOnly(),
    'AppHeader'     => fn() => AppHeader::make('ah1')->left('L')->slot('M')->right('R'),
];

test('the component kit renders as it did', function () use ($cases) {
    $out = '';
    foreach ($cases as $name => $make) {
        $out .= "### {$name}\n" . (string)$make() . "\n\n";
    }
    snapshot('render', $out);
});

test('no component renders framework internals into its text', function () use ($cases) {
    foreach ($cases as $name => $make) {
        $text = preg_replace('/<[^>]*>/', '', (string)$make());
        foreach (['xhandle(', '_compiled', '_mainTag', '_actions', '_rootStart', 'Array'] as $leak) {
            lacks($leak, $text, $name . ' leaked "' . $leak . '" into its visible text');
        }
    }
});

test('every core component survives being rendered bare', function () {
    foreach (glob(dirname(__DIR__, 2) . '/src/core/Components/*.php') as $file) {
        $class = basename($file, '.php');
        $html = (string)$class::make('probe')->addClass('z')->addStyle('color', 'red');
        contains('id="probe"', $html, $class . ' must carry its id');
        contains('comp="' . $class . '"', $html, $class . ' must carry its comp');
    }
});
