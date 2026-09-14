<?php

/**
 * One row in a list or sidebar: icon, label, optional trailing badge.
 *
 *   ListItem::make('nav-items')->set('Items', 'list')->active()
 *       ->onClick('AppHandler.openItems()')
 *
 *   ListItem::make('row-'.$id)->set($row['title'], 'file')
 *       ->badge(Badge::make()->set($row['count']))
 *       ->with(['record' => $id])->onClick('AppHandler.open()')
 *
 * Presentation only — what a row does belongs in a handler, not here.
 */
class ListItem extends Component
{
    public $label  = "";
    public $icon   = "";
    public $badge  = "";
    public $record = "";

    protected string $template = '
        <div class="list-item">
            {{$icon}}
            <span class="list-item-label">{{$label}}</span>
            {{$badge}}
        </div>';

    public function set(string $label, string $icon = ""): static
    {
        $this->label = trans($label);
        $this->icon  = $icon === "" ? "" : Icon::make()->set($icon, 'sm')->addClass('text-muted');
        return $this;
    }

    /** @param string|Component $badge */
    public function badge($badge): static
    {
        $this->badge = raw((string)$badge);
        return $this;
    }

    /** Highlight this row as the current one. */
    public function active(bool $on = true): static
    {
        return $on ? $this->addClass('is-active') : $this;
    }
}
