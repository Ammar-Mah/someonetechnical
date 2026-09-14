<?php

class Event{
    public $id="";
    public $events=[];
    public $status = 'ok';
    public $message = '';

    public static function make(): static
    {
        return new static();
    }

    public function status($status) {
        $this->status = $status;
        return $this;
    }

    public function message($message) {
        $this->message = $message;
        return $this;
    }

    /**
     * Every action funnels through here, which is where a Component is turned
     * into the selector that finds it.
     *
     * Passing the component itself is worth preferring: it removes a whole
     * class of stale-selector bug, and it stops screens having to export id
     * constants purely so a handler can rebuild '#' . SOME::ID.
     */
    private function addEvent($name, $target, $code = "", $extra = []) {
        $event = [
            "name" => $name,
            "target" => self::selector($target),
            "code" => $code,
            "more" => ""
        ];
        $this->events[] = array_merge($event, $extra);
        return $this;
    }

    /** '#id' for a component, unchanged for a string already written by hand. */
    public static function selector(Component|string $target): string
    {
        return $target instanceof Component ? '#' . $target->id : $target;
    }

    public function more($html) {
        if (!empty($this->events)) {
            $lastIndex = count($this->events) - 1;
            $this->events[$lastIndex]['more'] = $html;
        }
        return $this;
    }

    public function inner(Component|string $selector, string $html){
        return $this->addEvent("inner", $selector, $html);
    }

    /**
     * Replace the contents of $selector by reconciling against what is already
     * there, instead of assigning innerHTML.
     *
     * Same end state, but nodes that did not change are reused — so scroll
     * position, focus, text selection, open <details> and live editor instances
     * survive the update. Children are matched by id, then by a "key" attribute,
     * then positionally, so a reorder moves nodes rather than rebuilding them.
     *
     * inner() already routes through the same code on the client; this exists
     * for callers that want to be explicit.
     */
    public function patch(Component|string $selector, string $html){
        return $this->addEvent("patch", $selector, $html);
    }

    public function child(Component|string $selector, string $html)
    {
        return $this->addEvent("child", $selector, $html);
    }

    public function outer(Component|string $selector, string $html){
        return $this->addEvent("outer", $selector, $html);
    }

    public function toggle(Component|string $selector, string $class){
        return $this->addEvent("toggle", $selector, $class);
    }

    public function add(Component|string $selector, string $class){
        return $this->addEvent("add", $selector, $class);
    }

    public function strip(Component|string $selector, string $class){
        return $this->addEvent("strip", $selector, $class);
    }

    /**
     * Remove whatever matches, if anything does.
     *
     * Pass $optional = true for a *sweep*: an action whose job is "make sure none of
     * these are left", where matching nothing is the ordinary case rather than a
     * mistake. Closing any open side panel before switching tab is the example — most
     * of the time no panel is open, and the client would otherwise warn on every
     * single tab change that it found nothing to close.
     *
     * The default stays noisy on purpose. A remove that names one specific element and
     * silently hits nothing is usually a stale selector, and that warning is how you
     * find out.
     */
    public function remove(Component|string $selector, bool $optional = false){
        return $this->addEvent("remove", $selector, "", $optional ? ["optional" => true] : []);
    }

    public function append(Component|string $selector, string $html){
        return $this->addEvent("append", $selector, $html);
    }

    public function before(Component|string $selector, string $html){
        return $this->addEvent("before", $selector, $html);
    }

    public function parent(Component|string $selector, string $html)
    {
        return $this->addEvent("parent", $selector, $html);
    }

    public function grandparent(Component|string $selector, string $html)
    {
        return $this->addEvent("grand", $selector, $html);
    }

    public function clear(Component|string $selector)
    {
        return $this->addEvent("clear", $selector);
    }

    public function focus(Component|string $selector)
    {
        return $this->addEvent("focus", $selector);
    }

    public function refresh()
    {
        return $this->addEvent("refresh", "");
    }

    /*
     * goto(), transfer(), submit(), filter() and filterValues() used to live
     * here. None of them had a client implementation — the runtime has no branch
     * for any of those names — so every one was a silent no-op that read like a
     * working feature.
     *
     * For navigation, call the browser's own function:
     *     Event::make()->call('location.assign', ['https://example.test'])
     * To submit a form, click its submit control:
     *     Event::make()->click('#my-form button[type=submit]')
     */

    public function dynamic($name, $details): static
    {
        $this->events[]= [
            "name"=>$name,
            "details"=>$details,
            "more"=>""
        ];
        return $this;
    }

    public function call($func, $params = [], $delay = 0)
    {
        return $this->addEvent("call", "body", $func . "|" . implode(',', $params), ["delay" => intval($delay)]);
    }

    public function value(Component|string $selector, $value)
    {
        return $this->addEvent("value", $selector, $value);
    }

    public function open(Component|string $selector)
    {
        return $this->addEvent("open", $selector, "open");
    }

    public function plant(Component|string $selector, $code)
    {
        return $this->addEvent("plant", $selector, $code);
    }

    public function click(Component|string $selector, $delay = 0)
    {
        return $this->addEvent("click", $selector, "", ["delay" => $delay]);
    }

    /** Check the last radio input with this NAME — a form name, not a selector. */
    public function selectlast(string $name)
    {
        return $this->addEvent("selectlast", $name);
    }

    public function setValue(Component|string $selector, $attribute, $evt = "change")
    {
        return $this->addEvent("setval", $selector, $attribute, ["type" => $evt]);
    }

    public function setAttribute(Component|string $selector, $attribute)
    {
        return $this->addEvent("setAttribute", $selector, $attribute);
    }

    public function toggleAttribute(Component|string $selector, $attribute)
    {
        return $this->addEvent("toggleAttribute", $selector, $attribute);
    }

    public function removeAttribute(Component|string $selector, $attribute)
    {
        return $this->addEvent("removeAttribute", $selector, $attribute);
    }

    public function toggleValue(Component|string $selector, $attribute)
    {
        return $this->addEvent("toggleval", $selector, $attribute);
    }

    public function absolute(Component|string $selector, $height = 0)
    {
        return $this->addEvent("absolute", $selector, "", ["height" => $height]);
    }

    public function scrollView(Component|string $selector)
    {
        return $this->addEvent("scrollView", $selector);
    }

    public function send(){
        return [
            'status' => $this->status,
            'message' => $this->message,
            'actions' => $this->events
        ];
    }
}
