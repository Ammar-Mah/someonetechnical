<?php

/**
 * The sidebar.
 *
 * Every item is wired to the SAME handler and tells it apart with a `screen`
 * attribute — every scalar property of a component is written to the DOM and
 * posted back, so that one attribute is the whole of the wiring.
 *
 * WHY THIS IS A COMPONENT rather than a loop in the view: a view file's PHP
 * runs first and its output is what gets templated, so a variable it sets is
 * long gone by the time {{ }} is evaluated — those expressions only see the
 * data array passed to the view. Building markup in mount() is both the way
 * that works and the faster one, since it runs as PHP instead of as a
 * template expression.
 */
class SideNav extends Component
{
    public $links = "";

    /** screen key => [label, icon] */
    private const ITEMS = [
        'home'     => ['Home',     'home'],
        'items'    => ['Items',    'list'],
        'settings' => ['Settings', 'settings'],
    ];

    protected string $template = '<nav class="flex flex-col gap-1">{{$links}}</nav>';

    public function mount()
    {
        $current = AppHandler::currentScreen();

        $html = '';
        foreach (self::ITEMS as $screen => [$label, $icon]) {
            $html .= (string)ListItem::make('nav-' . $screen)
                ->set($label, $icon)
                ->active($screen === $current)
                ->with(['screen' => $screen])
                ->onClick('AppHandler.navigate()');
        }

        $this->links = raw($html);
    }
}
