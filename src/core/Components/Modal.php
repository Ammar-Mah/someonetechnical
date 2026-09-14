<?php

/**
 * A centred dialog.
 *
 * A modal is not "shown" and "hidden" — it is rendered when needed and removed
 * when done, which keeps the truth on the server:
 *
 *   public function newItem() {
 *       $form = Form::make('item-form')
 *           ->content(Field::make()->label('Title')->slot(TextInput::make()->name('title')))
 *           ->submit('AppHandler.save()');
 *
 *       return Event::make()
 *           ->append('body', (string)Modal::make('item-modal')
 *               ->title('New item')->slot($form)
 *               ->actions([
 *                   Button::make()->set('Cancel')->onClick('$remove(#item-modal)'),
 *                   // form() binds a footer button to a form it is not inside
 *                   Button::make()->set('Save')->primary()->form('item-form'),
 *               ]))
 *           ->send();
 *   }
 *
 *   // and to dismiss it, from any handler:
 *   Event::make()->remove('#item-modal')->send();
 *
 * The backdrop and the X both remove the element without a round trip.
 */
class Modal extends Component
{
    public $heading = "";
    public $foot    = "";
    public $width   = "480px";

    protected string $template = '
        <div class="modal-overlay" onclick="if(event.target===this)this.remove()">
            <div class="modal" style="max-width:{{$width}}">
                {{$heading}}
                <div class="modal-body">{{$slot}}</div>
                {{$foot}}
            </div>
        </div>';

    public function title(string $title, bool $closable = true): static
    {
        $close = $closable
            ? '<button type="button" class="icon-btn" onclick="this.closest(\'.modal-overlay\').remove()">'
              . (string)Icon::make()->set('x', 'sm') . '</button>'
            : '';

        $this->heading = raw('<div class="modal-header">'
            . '<span class="modal-title">' . htmlspecialchars(trans($title), ENT_QUOTES, 'UTF-8') . '</span>'
            . $close . '</div>');
        return $this;
    }

    /** Buttons along the bottom. @param string|array|Component $buttons */
    public function actions($buttons): static
    {
        $html = is_array($buttons) ? implode('', array_map('strval', $buttons)) : (string)$buttons;
        $this->foot = $html === "" ? "" : raw('<div class="modal-footer">' . $html . '</div>');
        return $this;
    }

    /** Any CSS length: '480px' (default), '90vw', '32rem'. */
    public function width(string $width): static
    {
        $this->width = $width;
        return $this;
    }
}
