<?php

/**
 * A working list — add, complete, filter, delete.
 *
 * Backed by the Item MODEL, and therefore by a real database — which with
 * DB_ENGINE set to 'file' means a JSON file that creates itself on the first
 * insert. No schema, no server, no configuration, and the same code runs
 * unchanged against MySQL by changing that one setting.
 *
 * Everything else is how a real screen is built: the server owns the data,
 * handlers return Events, and only the regions that changed are sent back.
 *
 * The three ids below are the contract with AppHandler. A handler re-renders a
 * region by targeting one of them, which is why they are constants rather than
 * strings repeated in six places.
 *
 * Note what is in the database and what is not: the ITEMS are shared data, so
 * they live in a table; the chosen FILTER is one person's view of them, so it
 * lives in State. Putting the second in a table would make your filter change
 * everyone else's.
 */
class ItemsScreen extends Component
{
    public const LIST_ID    = 'items-list';
    public const SUMMARY_ID = 'items-summary';
    public const INPUT_ID   = 'items-input';

    public $body = "";

    protected string $template = '<div class="flex flex-col gap-4">{{$body}}</div>';

    public function mount()
    {
        $this->body = raw(implode('', [
            $this->intro(),
            $this->toolbar(),
            (string)Container::make(self::SUMMARY_ID)->slot(self::summaryHtml()),
            (string)Container::make(self::LIST_ID)->slot(self::listHtml()),
        ]));
    }

    // -------------------------------------------------------------------------
    // Data — ordinary Model calls, whichever engine is configured
    // -------------------------------------------------------------------------

    /** Every item, oldest first. Seeds the table the first time it is asked. */
    public static function items(): Collection
    {
        self::seedOnce();
        return Item::query()->orderBy('id')->get();
    }

    /** Just the ones the current filter admits. */
    public static function visible(): Collection
    {
        self::seedOnce();

        $query = Item::query()->orderBy('id');
        $filter = self::filter();

        // The filter becomes a WHERE — the engine decides whether that is SQL
        // or a pass over a JSON file, and this code never finds out.
        if ($filter !== 'all') {
            $query->where('status', $filter === 'done' ? 'done' : 'open');
        }

        return $query->get();
    }

    /**
     * Put something in the table the first time anyone looks.
     *
     * Counted withTrashed(), so emptying the list does not bring the examples
     * back — a soft-deleted row still means "this table has been used".
     */
    private static function seedOnce(): void
    {
        if (Item::query()->withTrashed()->count() > 0) return;

        foreach ([
            ['Read LLM.txt', 'done'],
            ['Rename the app', 'open'],
            ['Delete the demo screens', 'open'],
        ] as [$title, $status]) {
            Item::add($title, ['status' => $status]);
        }
    }

    /** 'all' | 'open' | 'done' — a view preference, so State rather than a table. */
    public static function filter(): string
    {
        $filter = (string)State::get('demo.filter', 'all');
        return in_array($filter, ['all', 'open', 'done'], true) ? $filter : 'all';
    }

    // -------------------------------------------------------------------------
    // Regions — public so a handler can re-render one without rebuilding the page
    // -------------------------------------------------------------------------

    public static function summaryHtml(): string
    {
        self::seedOnce();

        // Counted in the engine rather than by fetching rows to count them.
        $total = Item::query()->count();
        $done  = Item::query()->where('status', 'done')->count();

        if ($total === 0) {
            return (string)Alert::make()->info('Nothing here yet — add something above.');
        }

        return (string)Progress::make()
            ->set($done, $total)
            ->caption($done . ' ' . trans('of') . ' ' . $total . ' ' . trans('done'));
    }

    public static function listHtml(): string
    {
        $items = self::visible();

        if ($items->count() === 0) {
            return (string)Alert::make()->info('No items match this filter.');
        }

        $rows = [];
        foreach ($items as $item) {
            $id    = (int)$item->id;
            $done  = $item->status === 'done';
            $title = (string)$item->title;

            $rows[] = [
                // The key is what lets the client MOVE this row on the next
                // update instead of rebuilding it — see the patcher in §8.5.
                'key'   => $id,
                'cells' => [
                    ['html' => (string)IconButton::make('toggle-' . $id)
                        ->set($done ? 'check-circle' : 'circle')
                        ->tooltip($done ? 'Mark as open' : 'Mark as done')
                        ->addClass($done ? 'text-success' : '')
                        ->with(['record' => $id])
                        ->onClick('AppHandler.toggleItem()'), 'class' => 'w-fit'],

                    '<span class="' . ($done ? 'text-muted' : '') . '">' . e($title) . '</span>',

                    (string)Badge::make()
                        ->set($done ? 'Done' : 'Open')
                        ->tone($done ? 'success' : 'brand'),

                    ['html' => (string)IconButton::make('del-' . $id)
                        ->set('trash')->danger()->tooltip('Delete')
                        ->confirm('Delete "' . $title . '"?')
                        ->with(['record' => $id])
                        ->onClick('AppHandler.deleteItem()'), 'class' => 'is-numeric'],
                ],
            ];
        }

        return (string)Table::make('items-table')->set(['', 'Item', 'Status', ''], $rows);
    }

    // -------------------------------------------------------------------------
    // Layout
    // -------------------------------------------------------------------------

    private function intro(): string
    {
        return (string)Card::make('items-intro')
            ->title('Items')
            ->slot('<p class="text-sm text-muted">'
                . e(trans('A working list, kept in State — so it survives a reload with no database behind it. '
                        . 'Adding, completing and deleting each update only the part of the page that changed.'))
                . '</p>');
    }

    private function toolbar(): string
    {
        // A Form rather than a loose input and button: on submit the client
        // serialises every NAMED field into one JSON object, so the handler gets
        // the value whether the person pressed Enter or clicked Add. A bare
        // button would only ever post its own attributes, never the input's.
        $add = Form::make('items-add')
            ->layout('row')
            ->content([
                TextInput::make(self::INPUT_ID)->name('title')->set('', 'Add an item…'),
                Button::make()->set('Add', 'plus')->primary()->type('submit'),
            ])
            ->submit('AppHandler.addItem()');

        $filter = Picker::make('items-filter')->single()
            ->placeholder('All items')
            ->set(['all' => 'All items', 'open' => 'Open only', 'done' => 'Done only'], self::filter())
            ->onChange('AppHandler.filterItems()');

        // No min-w-0 on the form's wrapper on purpose: that would let it shrink
        // past its own contents, crushing the input instead of letting this row
        // wrap. Floored at its min-content width, the filter and Clear done drop
        // to a second line on a narrow screen, which is the right trade.
        return (string)Card::make('items-toolbar')->slot(
            Container::make()->addClass('flex flex-wrap gap-3 items-end')->slot([
                Container::make()->addClass('flex-1')->slot($add),
                Container::make()->addClass('w-fit')->slot($filter),
                Button::make()->set('Clear done', 'check')->onClick('AppHandler.clearDone()'),
            ])
        );
    }
}
