<?php

/**
 * A plain box to group other components.
 *
 * Its whole purpose is to be a stable, addressable region: give it an id and
 * a handler can replace everything inside it with one action.
 *
 *   Container::make('results')->slot($rows)
 *   // later, from a handler:
 *   Event::make()->inner('#results', $newRows)->send();
 *
 * Reach for the utility classes for anything visual:
 *   Container::make('board')->addClass('flex gap-3 overflow-x-auto p-2')
 */
class Container extends Component
{
    protected string $template = '<div>{{$slot}}</div>';

    /** Scroll on overflow instead of growing. */
    public function scrollable(bool $on = true): static
    {
        return $on ? $this->addClass('overflow-auto min-h-0') : $this;
    }

    /** Lay the children out in a row or column with a gap. */
    public function stack(string $direction = "col", int $gap = 2): static
    {
        return $this->addClass('flex ' . ($direction === 'row' ? 'flex-row' : 'flex-col') . ' gap-' . $gap);
    }
}
