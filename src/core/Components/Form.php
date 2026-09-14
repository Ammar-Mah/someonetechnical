<?php

/**
 * A form.
 *
 *   Form::make('new-item')
 *       ->content([
 *           Field::make()->label('Title')->slot(TextInput::make()->name('title')),
 *           Field::make()->label('Notes')->slot(TextArea::make()->name('notes')),
 *       ])
 *       ->actions(Button::make()->set('Save')->primary()->type('submit'))
 *       ->submit('AppHandler.save()');
 *
 * On submit the client serialises every named field into one JSON object and
 * sends it as `value`, so the handler is:
 *
 *   public function save(Request $request) {
 *       $data = json_decode((string)$request->get('value'), true) ?: [];
 *       Item::create(['title' => trim($data['title'] ?? '')]);
 *       …
 *   }
 *
 * The page never reloads — the client cancels the native submit.
 */
class Form extends Component
{
    public $actions = "";
    public $layout  = "flex-col";

    protected string $template = '
        <form class="form">
            <div class="form-body flex {{$layout}} gap-3 w-full">{{$slot}}</div>
            {{$actions}}
        </form>';

    /** Buttons, shown in a row under the fields. */
    public function actions($buttons): static
    {
        $html = is_array($buttons) ? implode('', array_map('strval', $buttons)) : (string)$buttons;
        $this->actions = $html === "" ? "" : raw('<div class="form-actions">' . $html . '</div>');
        return $this;
    }

    /** 'col' (default) stacks the fields, 'row' lays them out inline. */
    public function layout(string $direction = "col"): static
    {
        $this->layout = $direction === "row" ? 'flex-row flex-wrap' : 'flex-col';
        return $this;
    }

    /** The handler that receives the submitted values. */
    public function submit(string|array $handler): static
    {
        return $this->action("submit", $handler);
    }
}
