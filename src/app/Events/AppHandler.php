<?php

/**
 * An Events class: a group of handlers.
 *
 * WHAT MAKES A HANDLER. updater.php will only call a method that is
 *
 *   - public
 *   - NOT static          (a static method needs no instance, and allowing it
 *                          would widen what a POST body can reach)
 *   - not magic (__…)
 *   - declared on YOUR class, not inherited from Component or Handler
 *
 * Anything else is refused with "Unknown action.", which is the framework
 * telling you it will not dispatch to plumbing.
 *
 * HOW ONE IS ADDRESSED. From any element, anywhere:
 *
 *     ->onClick('AppHandler.save()')
 *     xon:click="AppHandler.save()"
 *
 * The "Class.method()" form works from any component, which is why behaviour
 * can be grouped here by what it does rather than scattered across the
 * components it happens to be triggered from.
 *
 * WHAT ONE RECEIVES. The client posts the attributes of the element that was
 * interacted with, plus those of its enclosing component. updater.php rebuilds
 * this class with them, so both of these read the same thing:
 *
 *     $request->get('record')      // explicit, and works for anything posted
 *     $this->record                // the rehydrated property
 *
 * WHAT ONE RETURNS. Always Event::…->send() — a list of DOM instructions.
 * Returning Event::make()->send() with no actions is a valid "do nothing".
 */
class AppHandler extends Handler
{
    /** Which component renders each screen. The key is what the sidebar sends. */
    private const SCREENS = [
        'home'     => Welcome::class,
        'items'    => ItemsScreen::class,
        'settings' => SettingsScreen::class,
    ];

    /**
     * Switch the main region to another screen.
     *
     * One handler for every navigation item, told apart by the `screen`
     * attribute each item carries. Note what comes back: the new content, and
     * the two class changes that move the highlight. Nothing else on the page
     * is re-rendered or even mentioned.
     *
     * The chosen screen is remembered in State, so a reload comes back to it.
     */
    public function navigate(Request $request)
    {
        $screen = (string)$request->get('screen', 'home');
        if (!isset(self::SCREENS[$screen])) {
            $screen = 'home';
        }

        State::set('demo.screen', $screen);

        $component = self::SCREENS[$screen];

        return Event::make()
            ->inner('#app-content', (string)$component::make($screen . '-screen'))
            ->strip('.app-sidebar .list-item', 'is-active')
            ->add('#nav-' . $screen, 'is-active')
            ->send();
    }

    /** The screen to show on a fresh page load. */
    public static function currentScreen(): string
    {
        $screen = (string)State::get('demo.screen', 'home');
        return isset(self::SCREENS[$screen]) ? $screen : 'home';
    }

    /**
     * The screen that is current, as a component, for the main view to print.
     *
     * Returns the COMPONENT, not its rendered string. Templates escape strings —
     * that is the point of them — so a helper that hands a view pre-rendered
     * markup gets it escaped and the page fills with &lt;div&gt;. Return a
     * component (markup by construction) or raw(); never a bare HTML string.
     */
    public static function renderScreen(): Component
    {
        $screen = self::currentScreen();
        $component = self::SCREENS[$screen];
        return $component::make($screen . '-screen');
    }

    /**
     * Add $step to a counter kept in State, and update just the one span.
     *
     * State is per-user server-side interface state: it survives a reload,
     * needs no database, and is not visible to the client.
     */
    public function bump(Request $request)
    {
        $step  = (int)$request->get('step', 0);
        $count = $step === 0 ? 0 : (int)State::get('demo.count', 0) + $step;

        State::set('demo.count', $count);

        // Only the number is sent back, not the card around it.
        return Event::make()
            ->inner('#counter-value', (string)$count)
            ->send();
    }

    /**
     * Echo what is being typed.
     *
     * 'input' is debounced by 300 ms on the client, so this runs once the
     * person pauses rather than on every keystroke.
     *
     * Note the e() — `value` came from the browser, and ->inner() writes HTML.
     */
    public function preview(Request $request)
    {
        $typed = trim((string)$request->get('value'));

        return Event::make()
            ->inner('#echo-output', $typed === '' ? e(trans('…')) : e($typed))
            ->send();
    }

    /**
     * React to a Picker.
     *
     * The picker sends every selected value joined by "|", so a handler sees
     * the whole selection rather than the single option that was just clicked.
     */
    public function picked(Request $request)
    {
        $selected = array_filter(explode('|', (string)$request->get('value')));

        $message = $selected === []
            ? trans('Nothing selected')
            : trans('Selected') . ': ' . implode(' ', $selected);

        return Event::make()
            ->call('toast', [$message])
            ->send();
    }

    /**
     * Build a dialog on the server and append it to the page.
     *
     * A modal is not shown and hidden — it is rendered when needed and removed
     * when done, so there is no client-side state to get out of step.
     */
    public function openModal()
    {
        $form = Form::make('demo-form')
            ->content([
                Field::make()->label('Title')->hint('Required.')
                    ->slot(TextInput::make('demo-title')->name('title')->set('', 'Something short')),
                Field::make()->label('Notes')
                    ->slot(TextArea::make('demo-notes')->name('notes')->rows(3)),
            ])
            ->submit('AppHandler.saveItem()');

        $modal = Modal::make('demo-modal')
            ->title('New item')
            ->slot($form)
            ->actions([
                Button::make()->set('Cancel')->onClick('$remove(#demo-modal)'),
                Button::make()->set('Save')->primary()->form('demo-form'),
            ]);

        return Event::make()->append('body', (string)$modal)->send();
    }

    /**
     * Receive a submitted form.
     *
     * On submit the client serialises every named field into one JSON object
     * and sends it as `value`, so there is exactly one thing to decode.
     */
    public function saveItem(Request $request)
    {
        $data  = json_decode((string)$request->get('value'), true) ?: [];
        $title = trim((string)($data['title'] ?? ''));

        // Validation is a normal early return: say what is wrong and change
        // nothing else.
        if ($title === '') {
            return Event::make()
                ->add('#demo-title', 'is-invalid')
                ->call('toast', [trans('A title is required') . '.', 'error'])
                ->send();
        }

        // A real handler would persist here — see src/app/Models/Item.php:
        //     Item::create(['title' => $title, 'notes' => $data['notes'] ?? '']);

        return Event::make()
            ->remove('#demo-modal')
            ->call('toast', [trans('Saved') . ': ' . $title, 'success'])
            ->send();
    }

    /** Append a side panel, then ask the client to animate it in. */
    public function openPanel()
    {
        $panel = Slider::make('demo-slider')
            ->title('Side panel')
            ->slot(
                '<p class="text-sm text-muted">'
                . e(trans('Panels are rendered by the server like everything else. '
                        . 'This one closes on Escape, on the backdrop, or when you click anything outside it.'))
                . '</p>'
            );

        return Event::make()
            ->append('#app-content', (string)$panel)
            ->call('Slider.open', ['demo-slider'])
            ->send();
    }

    /**
     * Call a JavaScript function by name.
     *
     * ->call() resolves a (dotted) name against window — it does not eval, so
     * a response cannot smuggle in script. Arguments arrive as strings and are
     * split on commas, so avoid commas inside one.
     */
    public function notify()
    {
        return Event::make()
            ->call('toast', [trans('That came from the server.'), 'success'])
            ->send();
    }

    /**
     * Remove a row.
     *
     * The button carried record and label as attributes, so they are here
     * without any lookup. The confirmation happened on the client (see
     * Button::confirm) and this is only reached if the person said yes.
     */
    public function removePerson(Request $request)
    {
        $id   = (string)$request->get('record');
        $name = (string)$request->get('label');

        if ($id === '') {
            return Event::make()->send();
        }

        // Real code would delete here, e.g. Item::find($id)?->delete();

        return Event::make()
            ->remove('#people tr[key="' . e($id) . '"]')
            ->call('toast', [trans('Removed') . ' ' . $name])
            ->send();
    }

    /**
     * Persist the chosen theme, then apply it without a reload.
     *
     * currentTheme() reads this on the next full page load, so the choice
     * survives — the ->call() is only there to avoid a flash of the old theme.
     */
    public function toggleTheme()
    {
        return $this->applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }

    /** Set a specific theme — the two buttons on the settings screen. */
    public function setTheme(Request $request)
    {
        return $this->applyTheme((string)$request->get('theme') === 'dark' ? 'dark' : 'light');
    }

    private function applyTheme(string $theme)
    {
        State::set('theme', $theme);

        return Event::make()
            ->call('applyTheme', [$theme])
            // Move the highlight onto whichever button is now current.
            ->strip('#theme-light, #theme-dark', 'btn-primary')
            ->add('#theme-' . $theme, 'btn-primary')
            ->send();
    }

    /**
     * Change the interface language.
     *
     * This one reloads. The document's lang and dir attributes and every
     * translated string are decided while the page renders, so there is no
     * subset of the DOM that could be patched to switch language honestly.
     */
    public function setLanguage(Request $request)
    {
        $code = (string)$request->get('value');
        if (!isset(availableLanguages()[$code])) {
            return Event::make()->send();
        }

        State::set('language', $code);

        return Event::make()->refresh()->send();
    }

    // -------------------------------------------------------------------------
    // The items screen
    // -------------------------------------------------------------------------

    /** Add an item. The Form sends every named field as one JSON object. */
    public function addItem(Request $request)
    {
        $data  = json_decode((string)$request->get('value'), true) ?: [];
        $title = trim((string)($data['title'] ?? ''));

        if ($title === '') {
            return Event::make()
                ->add('#' . ItemsScreen::INPUT_ID, 'is-invalid')
                ->call('toast', [trans('Type something first') . '.', 'warning'])
                ->send();
        }

        // One line, and it is a real row in a real table. With DB_ENGINE on
        // 'file' that table is a JSON file that creates itself; on 'sql' it is
        // an INSERT. This code cannot tell, which is the point.
        Item::add($title);

        return $this->refreshItems()
            ->strip('#' . ItemsScreen::INPUT_ID, 'is-invalid')
            ->setValue('#' . ItemsScreen::INPUT_ID, '', '')
            ->focus('#' . ItemsScreen::INPUT_ID)
            ->send();
    }

    public function toggleItem(Request $request)
    {
        $item = Item::find($request->get('record'));
        if (!$item) return Event::make()->send();

        $item->status = $item->status === 'done' ? 'open' : 'done';
        $item->touchNow();                       // save() writes only what moved

        return $this->refreshItems()->send();
    }

    public function deleteItem(Request $request)
    {
        $item = Item::find($request->get('record'));
        if (!$item) return Event::make()->send();

        // Soft: the row keeps its place in the table with deleted_at stamped,
        // and every ordinary query stops seeing it.
        $item->delete();

        return $this->refreshItems()
            ->call('toast', [trans('Item deleted')])
            ->send();
    }

    public function clearDone()
    {
        // One statement for the whole set, rather than a fetch-and-loop.
        $removed = Item::query()->where('status', 'done')->deleteAll();

        if ($removed === 0) {
            return Event::make()->call('toast', [trans('Nothing to clear')])->send();
        }

        return $this->refreshItems()
            ->call('toast', [$removed . ' ' . trans('cleared'), 'success'])
            ->send();
    }

    public function filterItems(Request $request)
    {
        State::set('demo.filter', (string)$request->get('value'));
        return $this->refreshItems()->send();
    }

    /**
     * The two regions of the items screen that any change can affect.
     *
     * Returned unfinished on purpose — callers chain their own actions on and
     * call send() themselves, so each handler sends exactly one response.
     */
    private function refreshItems(): Event
    {
        return Event::make()
            ->inner('#' . ItemsScreen::SUMMARY_ID, ItemsScreen::summaryHtml())
            ->inner('#' . ItemsScreen::LIST_ID, ItemsScreen::listHtml());
    }

    /** The kit's example form. Shows what a submitted payload looks like. */
    public function submitDemo(Request $request)
    {
        $data = json_decode((string)$request->get('value'), true) ?: [];

        $summary = [];
        foreach ($data as $field => $value) {
            $summary[] = $field . ': ' . (is_array($value) ? implode(' / ', $value) : $value);
        }

        return Event::make()
            ->call('toast', [trans('Submitted') . ' — ' . implode('; ', $summary), 'success'])
            ->send();
    }
}
