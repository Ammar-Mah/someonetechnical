<?php

/**
 * A tab strip.
 *
 *   Tabs::make('main')->set([
 *       Tab::make('overview')->set('Overview', 'home')->content($overview)->checked(),
 *       Tab::make('items')->set('Items', 'list')->onOpen('AppHandler.loadItems()'),
 *       Tab::make('settings')->set('Settings', 'settings')->content($settings),
 *   ]);
 *
 * The component is only the frame — Tab does the work. Adding a tab later is
 * an append from a handler:
 *
 *   Event::make()->append('#main .tabs-bar', (string)Tab::make(…)->checked())->send();
 */
class Tabs extends Component
{
    protected string $template = '
        <div class="tabs">
            <div class="tabs-bar">{{$slot}}</div>
        </div>';

    /** @param array|string $tabs Tab components, or prerendered markup */
    public function set(string|array|Component $tabs): static
    {
        return $this->slot($tabs);
    }
}
