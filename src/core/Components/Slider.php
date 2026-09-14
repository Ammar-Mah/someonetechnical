<?php

/**
 * A panel that slides in from the side — details, filters, a quick form.
 *
 *   public function openDetails(Request $request) {
 *       $panel = Slider::make('details')->title('Item')->slot($html);
 *       return Event::make()
 *           ->append('#app-content', (string)$panel)
 *           ->call('Slider.open', ['details'])        // triggers the animation
 *           ->send();
 *   }
 *
 * It is positioned against the nearest positioned ancestor, so append it into
 * the region it belongs to rather than into <body> if you want it to cover
 * only that region.
 *
 * The client closes any open slider automatically when a click or change
 * happens outside one, so a stale panel cannot be left behind after the user
 * has navigated on.
 */
class Slider extends Component
{
    public $title   = "";
    public $width   = "min(480px, 90vw)";
    public $side    = "is-end";
    public $close   = "";

    protected string $template = '
        <div class="slider hidden">
            <div class="slider-overlay" onclick="Slider.close(\'{{$id}}\')"></div>
            <div class="slider-content {{$side}}" style="width:{{$width}}">
                <div class="slider-header">
                    <span class="text-lg font-semibold">{{$title}}</span>
                    {{$close}}
                </div>
                <div class="slider-body">{{$slot}}</div>
            </div>
        </div>';

    public function mount()
    {
        $this->close = raw('<button type="button" class="icon-btn" onclick="Slider.close(\''
            . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '\')">'
            . (string)Icon::make()->set('x', 'sm') . '</button>');
    }

    public function title(string $title): static
    {
        $this->title = trans($title);
        return $this;
    }

    /** Any CSS width. */
    public function width(string $width): static
    {
        $this->width = $width;
        return $this;
    }

    /** Slide in from the start (left in LTR) instead of the end. */
    public function fromStart(bool $on = true): static
    {
        $this->side = $on ? 'is-start' : 'is-end';
        return $this;
    }
}
