<?php

/**
 * A progress bar — one value, or several segments side by side.
 *
 *   Progress::make()->set(7, 10)                         // 70%
 *   Progress::make()->set(7, 10)->caption('7 of 10 done')
 *
 *   Progress::make()->segments([
 *       ['value' => 5, 'color' => 'var(--success)', 'label' => 'Done'],
 *       ['value' => 3, 'color' => 'var(--warning)', 'label' => 'In progress'],
 *       ['value' => 2, 'color' => 'var(--border-strong)', 'label' => 'To do'],
 *   ]);
 *
 * Segments are sized as percentages of their own total, so the caller does not
 * have to work out the ratios.
 */
class Progress extends Component
{
    public $bars    = "";
    public $caption = "";

    protected string $template = '
        <div class="w-full">
            {{$caption}}
            <div class="progress">{{$bars}}</div>
        </div>';

    public function set(float $value, float $total = 100): static
    {
        $percent = $total > 0 ? max(0, min(100, ($value / $total) * 100)) : 0;
        $this->bars = raw('<div class="progress-bar" style="width:' . round($percent, 2) . '%"></div>');
        return $this;
    }

    /** @param array $segments each: ['value' => float, 'color' => string, 'label' => string] */
    public function segments(array $segments): static
    {
        $total = 0.0;
        foreach ($segments as $segment) $total += (float)($segment['value'] ?? 0);
        if ($total <= 0) {
            $this->bars = '';
            return $this;
        }

        $html = "";
        foreach ($segments as $segment) {
            $value = (float)($segment['value'] ?? 0);
            if ($value <= 0) continue;
            $percent = round(($value / $total) * 100, 2);
            $colour  = htmlspecialchars((string)($segment['color'] ?? 'var(--brand)'), ENT_QUOTES, 'UTF-8');
            $label   = htmlspecialchars((string)($segment['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html   .= '<div class="progress-bar tip" data-tooltip="' . $label . '"'
                     . ' style="width:' . $percent . '%;background:' . $colour . '"></div>';
        }
        $this->bars = raw($html);
        return $this;
    }

    public function caption(string $text): static
    {
        $this->caption = $text === "" ? ""
            : raw('<div class="text-xs text-muted mb-1">' . e(trans($text)) . '</div>');
        return $this;
    }
}
