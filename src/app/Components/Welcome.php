<?php

/**
 * The starter screen — and a worked example of every core idea.
 *
 * Read this next to src/app/Events/AppHandler.php: this file builds the
 * markup, that one answers the interactions. Delete both once your own app has
 * something to show.
 *
 * WHERE COMPONENTS LIVE. This file is in src/app/Components, which the
 * autoloader searches BEFORE src/core/Components. So:
 *
 *   - to EXTEND a core component, subclass it under a new name:
 *         class PrimaryButton extends Button { … }
 *   - to REPLACE one outright, create a file of the same name here
 *         (src/app/Components/Button.php) — yours wins and the core file is
 *         never loaded.
 *
 * Neither needs registering anywhere.
 *
 * Note that this component has no handlers. Anything that responds to a click
 * belongs in an Events class, so components stay reusable presentation and the
 * behaviour is grouped by what it does rather than by what it looks like.
 */
class Welcome extends Component
{
    public $body = "";

    protected string $template = '<div class="flex flex-col gap-4">{{$body}}</div>';

    public function mount()
    {
        $this->body = raw(implode('', [
            $this->intro(),
            $this->counter(),
            $this->liveInput(),
            $this->choices(),
            $this->table(),
            $this->dialogs(),
        ]));
    }

    /** A plain card. Nothing interactive. */
    private function intro(): string
    {
        return (string)Card::make('intro')
            ->title(trans('Welcome to') . ' ' . Logo::appName())
            ->slot(
                '<p class="text-sm text-muted">'
                . e(trans('Every panel below is rendered by PHP and updated by the server. '
                        . 'Open the network tab and watch: each interaction is one POST to updater.php '
                        . 'that returns a list of DOM instructions.'))
                . '</p>'
            );
    }

    /**
     * A counter whose value lives in State, so it survives a page reload.
     * See AppHandler::bump().
     */
    private function counter(): string
    {
        $count = (int)State::get('demo.count', 0);

        return (string)Card::make('counter-card')
            ->title('Server-held state')
            ->slot(
                Container::make()->addClass('flex items-center gap-3')->slot([
                    Button::make()->set('', 'minus')->with(['step' => -1])->onClick('AppHandler.bump()'),
                    Label::make('counter-value')->set($count)->addClass('text-2xl font-bold'),
                    Button::make()->set('', 'plus')->with(['step' => 1])->onClick('AppHandler.bump()'),
                    Button::make()->set('Reset')->ghost()->with(['step' => 0])->onClick('AppHandler.bump()'),
                    Label::make()->set(trans('Reload the page — the value stays.'))->muted()->small(),
                ])
            );
    }

    /** An input that posts on every keystroke (debounced). See AppHandler::preview(). */
    private function liveInput(): string
    {
        return (string)Card::make('echo-card')
            ->title('Live input')
            ->slot(
                Container::make()->addClass('flex flex-col gap-2')->slot([
                    TextInput::make('echo-input')
                        ->set('', 'Type something…')
                        ->on('input', 'AppHandler.preview()'),
                    Label::make('echo-output')->muted()->small()->set(trans('…')),
                ])
            );
    }

    /** A multi-choice dropdown. See AppHandler::picked(). */
    private function choices(): string
    {
        return (string)Card::make('picker-card')
            ->title('Picker')
            ->slot(
                Picker::make('demo-picker')
                    ->name('flavours')
                    ->placeholder('Choose one or more')
                    ->searchable()
                    ->set(
                        ['vanilla' => 'Vanilla', 'chocolate' => 'Chocolate', 'pistachio' => 'Pistachio'],
                        ['vanilla']
                    )
                    ->onChange('AppHandler.picked()')
            );
    }

    /** A table. `key` on each row is what lets the client move rows instead of rebuilding them. */
    private function table(): string
    {
        $rows = [
            ['id' => 1, 'name' => 'Ada Lovelace',  'role' => 'Owner',  'status' => 'active'],
            ['id' => 2, 'name' => 'Alan Turing',   'role' => 'Member', 'status' => 'active'],
            ['id' => 3, 'name' => 'Grace Hopper',  'role' => 'Member', 'status' => 'away'],
        ];

        $table = Table::make('people')->set(
            ['', 'Name', 'Role', 'Status', ''],
            array_map(fn(array $row) => [
                'key'   => $row['id'],
                'cells' => [
                    ['html' => (string)Avatar::make()->set($row['name'], 'sm'), 'class' => 'w-fit'],
                    e($row['name']),
                    e($row['role']),
                    (string)Badge::make()
                        ->set(ucfirst($row['status']))
                        ->tone($row['status'] === 'active' ? 'success' : 'warning'),
                    ['html' => (string)IconButton::make()->set('trash')->danger()
                        ->tooltip('Remove')
                        ->confirm('Remove this person?')
                        ->with(['record' => $row['id'], 'label' => $row['name']])
                        ->onClick('AppHandler.removePerson()'), 'class' => 'is-numeric'],
                ],
            ], $rows)
        );

        return (string)Card::make('table-card')->title('Table')->flush()->slot($table);
    }

    /** Modal, slider and toast, each opened by the server. */
    private function dialogs(): string
    {
        return (string)Card::make('dialog-card')
            ->title('Dialogs')
            ->slot(
                Container::make()->addClass('flex flex-wrap gap-2')->slot([
                    Button::make()->set('Open a modal', 'plus')->primary()->onClick('AppHandler.openModal()'),
                    Button::make()->set('Open a panel', 'arrow-right')->onClick('AppHandler.openPanel()'),
                    Button::make()->set('Show a toast', 'bell')->onClick('AppHandler.notify()'),
                ])
            );
    }
}
