<?php

/**
 * Preferences, and a tour of the rest of the component kit.
 *
 * The two settings at the top are real: both are stored in State, so both
 * survive a reload, and both show a different way of applying a change.
 *
 *   Theme     changes instantly with no reload — the handler saves the choice
 *             and asks the client to swap one attribute on <html>.
 *   Language  reloads, because the document's lang and dir attributes and every
 *             translated string are decided while the page is being rendered.
 */
class SettingsScreen extends Component
{
    public $body = "";

    protected string $template = '<div class="flex flex-col gap-4">{{$body}}</div>';

    public function mount()
    {
        $this->body = raw(implode('', [
            $this->appearance(),
            $this->language(),
            $this->components(),
        ]));
    }

    private function appearance(): string
    {
        $theme = currentTheme();

        return (string)Card::make('settings-theme')
            ->title('Appearance')
            ->slot(
                Container::make()->addClass('flex flex-col gap-3')->slot([
                    '<p class="text-sm text-muted">'
                        . e(trans('Both themes are one attribute on the html element; every colour in the '
                                . 'stylesheet is a token that follows it.')) . '</p>',
                    Container::make()->addClass('flex gap-2')->slot([
                        Button::make('theme-light')->set('Light', 'sun')
                            ->addClass($theme === 'light' ? 'btn-primary' : '')
                            ->with(['theme' => 'light'])->onClick('AppHandler.setTheme()'),
                        Button::make('theme-dark')->set('Dark', 'moon')
                            ->addClass($theme === 'dark' ? 'btn-primary' : '')
                            ->with(['theme' => 'dark'])->onClick('AppHandler.setTheme()'),
                    ]),
                ])
            );
    }

    private function language(): string
    {
        $languages = availableLanguages();
        $current   = currentLanguage();

        return (string)Card::make('settings-language')
            ->title('Language')
            ->slot(
                Container::make()->addClass('flex flex-col gap-3')->slot([
                    '<p class="text-sm text-muted">'
                        . e(trans('Languages are discovered from the files in src/app/Translations. '
                                . 'Arabic and Urdu also switch the page to right-to-left.')) . '</p>',
                    Container::make()->addClass('max-w-sm')->slot(
                        Field::make()->label('Interface language')->slot(
                            Select::make('language-select')->set($languages, $current)
                                ->on('change', 'AppHandler.setLanguage()')
                        )
                    ),
                ])
            );
    }

    /** Everything else in the kit, so there is one place to see it all. */
    private function components(): string
    {
        $tabs = Tabs::make('kit')->set([
            Tab::make('kit-inputs')->set('Inputs', 'edit')->checked()->content($this->inputs()),
            Tab::make('kit-display')->set('Display', 'grid')->content($this->display()),
            Tab::make('kit-feedback')->set('Feedback', 'bell')->content($this->feedback()),
        ]);

        return (string)Card::make('settings-kit')
            ->title('Component kit')
            ->flush()
            ->slot('<div style="height:420px">' . $tabs . '</div>');
    }

    private function inputs(): string
    {
        return (string)Form::make('kit-form')
            ->content([
                Field::make()->label('Name')->hint('An ordinary text field.')
                    ->slot(TextInput::make('kit-name')->name('name')->set('', 'Ada Lovelace')),
                Field::make()->label('Role')
                    ->slot(Select::make('kit-role')->name('role')
                        ->set(['owner' => 'Owner', 'member' => 'Member', 'viewer' => 'Viewer'], 'member')),
                Field::make()->label('Tags')->hint('Multiple choice, with search.')
                    ->slot(Picker::make('kit-tags')->name('tags')->searchable()
                        ->set(['a' => 'Design', 'b' => 'Backend', 'c' => 'Docs', 'd' => 'Infra'], ['b'])),
                Field::make()->label('Notes')
                    ->slot(TextArea::make('kit-notes')->name('notes')->rows(3)),
                CheckBox::make('kit-agree')->set('Send me a summary', true)->name('summary'),
            ])
            ->actions([
                Button::make()->set('Reset')->type('reset'),
                Button::make()->set('Submit', 'check')->primary()->type('submit'),
            ])
            ->submit('AppHandler.submitDemo()');
    }

    private function display(): string
    {
        return (string)Container::make()->addClass('flex flex-col gap-4')->slot([
            Container::make()->addClass('flex flex-wrap gap-2 items-center')->slot([
                Badge::make()->set('Neutral'),
                Badge::make()->set('Brand')->tone('brand'),
                Badge::make()->set('Success')->tone('success')->dot(),
                Badge::make()->set('Warning')->tone('warning'),
                Badge::make()->set('Danger')->tone('danger'),
                Badge::make()->set('Custom')->color('#7c3aed'),
            ]),
            Container::make()->addClass('flex items-center gap-4')->slot([
                Avatars::make()->set(['Ada Lovelace', 'Alan Turing', 'Grace Hopper', 'Edsger Dijkstra'], 'md', 3),
                Avatar::make()->set('Katherine Johnson', 'lg'),
            ]),
            Progress::make()->segments([
                ['value' => 5, 'color' => 'var(--success)', 'label' => 'Done'],
                ['value' => 3, 'color' => 'var(--warning)', 'label' => 'In progress'],
                ['value' => 2, 'color' => 'var(--border-strong)', 'label' => 'To do'],
            ]),
            Expander::make('kit-expander')->title('An expandable group', 'folder')
                ->tools(IconButton::make()->set('plus')->tooltip('Add'))
                ->slot('<p class="text-sm text-muted">'
                    . e(trans('Built on details, so it opens with no JavaScript at all.')) . '</p>'),
        ]);
    }

    private function feedback(): string
    {
        return (string)Container::make()->addClass('flex flex-col gap-3')->slot([
            Alert::make()->info('An informational message.'),
            Alert::make()->success('Something worked.'),
            Alert::make()->warning('Something needs attention.'),
            Alert::make()->danger('Something failed.'),
            Container::make()->addClass('flex flex-wrap gap-2')->slot([
                Button::make()->set('Toast', 'bell')->onClick('AppHandler.notify()'),
                Button::make()->set('Modal', 'plus')->onClick('AppHandler.openModal()'),
                Button::make()->set('Panel', 'arrow-right')->onClick('AppHandler.openPanel()'),
                Button::make()->set('Confirm then act', 'trash')->danger()
                    ->confirm('This asks before it runs. Continue?')
                    ->onClick('AppHandler.notify()'),
            ]),
            Loading::make()->message('A loading placeholder'),
        ]);
    }
}
