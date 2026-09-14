<?php

/**
 * A row of overlapping avatars, with a "+N" when the list is long.
 *
 *   Avatars::make()->set(['Ada Lovelace', 'Alan Turing', 'Grace Hopper'])
 *   Avatars::make()->set($names, 'sm', 4)
 */
class Avatars extends Component
{
    public $people = "";

    protected string $template = '<span class="avatar-stack">{{$people}}</span>';

    /**
     * @param array  $names people's names
     * @param string $size  xs | sm | md | lg | xl
     * @param int    $max   how many to show before collapsing into "+N"
     */
    public function set(array $names, string $size = "sm", int $max = 5): static
    {
        $names = array_values(array_filter(array_map('trim', $names), fn($n) => $n !== ''));

        if ($names === []) {
            $this->people = (string)Avatar::make()->set('', $size);
            return $this;
        }

        $shown  = array_slice($names, 0, max(1, $max));
        $hidden = count($names) - count($shown);

        $html = "";
        foreach ($shown as $name) {
            $html .= (string)Avatar::make()->set($name, $size);
        }

        if ($hidden > 0) {
            $html .= (string)Avatar::make()
                ->set('+' . $hidden, $size)
                ->with(['tooltip' => htmlspecialchars(implode(', ', array_slice($names, $max)), ENT_QUOTES, 'UTF-8')]);
        }

        $this->people = raw($html);
        return $this;
    }
}
