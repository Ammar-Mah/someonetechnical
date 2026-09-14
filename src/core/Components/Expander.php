<?php

/**
 * A collapsible group, built on <details> so it opens without JavaScript.
 *
 *   Expander::make('grp-'.$id)->title('Archive', 'folder')
 *       ->tools(IconButton::make()->set('plus')->tooltip('Add'))
 *       ->slot($rows)
 *       ->open($wasOpen);
 *
 * Controls passed to tools() sit at the end of the summary row and swallow the
 * click, so pressing one does not also toggle the group.
 *
 * To remember the open state across reloads, store it per user:
 *   State::set(['groups', $id, 'open'], true)   // in the handler
 *   ->open(State::get(['groups', $id, 'open'], false))
 */
class Expander extends Component
{
    public $title    = "";
    public $icon     = "";
    public $chevron  = "";
    public $tools    = "";
    public $isOpen   = "";
    public $record   = "";

    protected string $template = '
        <details class="expander" {{$isOpen}}>
            <summary>
                {{$chevron}}
                {{$icon}}
                <span class="flex-1 min-w-0 truncate">{{$title}}</span>
                {{$tools}}
            </summary>
            <div class="expander-body">{{$slot}}</div>
        </details>';

    public function mount()
    {
        $this->chevron = Icon::make()->set('chevron-right', 'xs')->addClass('expander-chevron');
    }

    public function title(string $title, string $icon = ""): static
    {
        $this->title = trans($title);
        if ($icon !== "") $this->icon = Icon::make()->set($icon, 'sm')->addClass('text-muted');
        return $this;
    }

    /**
     * Buttons pinned to the right of the summary.
     *
     * Named tools() rather than actions() on purpose: Component keeps its
     * registered event handlers in a private $actions, and a subclass property
     * of the same name would shadow it silently.
     *
     * @param string|array|Component $content
     */
    public function tools($content): static
    {
        $html = is_array($content) ? implode('', array_map('strval', $content)) : (string)$content;
        $this->tools = $html === "" ? ""
            : raw('<span class="expander-tools" onclick="event.stopPropagation()">' . $html . '</span>');
        return $this;
    }

    public function open(bool $open = true): static
    {
        $this->isOpen = $open ? raw('open') : '';
        return $this;
    }
}
