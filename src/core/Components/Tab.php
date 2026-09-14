<?php

/**
 * One tab: a label in the strip plus the panel it reveals.
 *
 * Switching tabs is pure CSS — a hidden radio input drives which panel shows —
 * so changing tab costs no request and cannot fail.
 *
 *   Tab::make('overview')->set('Overview', 'home')->content($html)->checked()
 *
 * LAZY LOADING. Pass a handler to onOpen() and the server is asked for the
 * panel's content the first time the tab is selected:
 *
 *   Tab::make('report')->set('Report', 'grid')->onOpen('AppHandler.loadReport()')
 *
 *   public function loadReport(Request $request) {
 *       return Event::make()
 *           ->inner('#panel-' . $request->get('panel'), $this->buildReport())
 *           ->send();
 *   }
 *
 * Tabs must share a group name to be mutually exclusive; the default group is
 * 'tab-group', which is what Tabs::make() sets up.
 */
class Tab extends Component
{
    public $label     = "";
    public $icon      = "";
    public $group     = "tab-group";
    public $checked   = "";
    public $close     = "";
    public $inputAttr = "";

    protected string $template = '
        <div class="tab">
            <input class="tab-input" type="radio" id="tab-{{$id}}" name="{{$group}}"
                   panel="panel-{{$id}}" {{$checked}} {{$inputAttr}}>
            <label class="tab-label" for="tab-{{$id}}">{{$icon}}<span>{{$label}}</span>{{$close}}</label>
            <div class="tab-panel" id="panel-{{$id}}">{{$slot}}</div>
        </div>';

    public function set(string $label, string $icon = "", string $group = ""): static
    {
        $this->label = trans($label);
        $this->icon  = $icon === "" ? "" : Icon::make()->set($icon, 'sm');
        if ($group !== "") $this->group = $group;
        return $this;
    }

    /** Make this the open tab. Exactly one tab per group should be checked. */
    public function checked(bool $on = true): static
    {
        $this->checked = $on ? raw('checked') : '';
        return $this;
    }

    /**
     * Fetch the panel content from the server when this tab is first opened.
     * The handler receives `panel` (the panel's element id) among the payload.
     */
    public function onOpen(string|array $handler): static
    {
        $handler = self::handler($handler);
        $this->inputAttr = raw('actions xonchange="' . e($handler) . '"');
        return $this;
    }

    /**
     * Add a close control.
     *
     * With no argument the tab simply removes itself in the browser. Pass a
     * handler name when the server has to know (to forget the tab in State,
     * release a lock, and so on).
     */
    public function closable(string|array $handler = ""): static
    {
        $icon = (string)Icon::make()->set('x', 'xs');
        $handler = self::handler($handler);

        $this->close = raw($handler === ""
            ? '<span class="tab-close pointer" onclick="event.preventDefault();event.stopPropagation();'
              . 'this.closest(\'.tab\').remove()">' . $icon . '</span>'
            : '<span class="tab-close pointer" record="' . e($this->id) . '"'
              . ' onclick="event.preventDefault();event.stopPropagation();xhandle(\''
              . e($handler) . '\',event)">' . $icon . '</span>');

        return $this;
    }
}
