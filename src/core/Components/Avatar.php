<?php

/**
 * A person, as initials or a picture.
 *
 *   Avatar::make()->set('Ada Lovelace')            // AL, colour derived from the name
 *   Avatar::make()->set('Ada Lovelace', 'lg')
 *   Avatar::make()->set('Ada Lovelace')->image('img/users/ada.jpg')
 *
 * The colour comes from a hash of the name, so the same person is the same
 * colour everywhere without anything being stored — and the ink is chosen for
 * contrast rather than assumed to be white.
 */
class Avatar extends Component
{
    public $initials = "";
    public $size     = "md";     // xs | sm | md | lg | xl
    public $tint     = "";
    public $tooltip  = "";

    protected string $template =
        '<span class="avatar avatar-{{$size}} tip" style="{{$tint}}" data-tooltip="{{$tooltip}}">{{$initials}}</span>';

    public function set(string $name, string $size = ""): static
    {
        $name = trim($name);
        if ($size !== "") $this->size = $size;

        $this->tooltip  = $name;
        $this->initials = self::initials($name);

        $colour = self::colourFor($name);
        $ink    = function_exists('readableInk') ? readableInk($colour) : '#fff';
        $this->tint = 'background:' . $colour . ';color:' . $ink . ';';

        return $this;
    }

    /** Show a picture instead of initials. The initials stay as the alt text. */
    public function image(string $url): static
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $this->initials = raw('<img src="' . $safeUrl . '" alt="' . e($this->tooltip) . '" loading="lazy">');
        return $this;
    }

    public function size(string $size): static
    {
        $this->size = $size;
        return $this;
    }

    /** "Ada Lovelace" -> "AL"; "ada" -> "AD"; "" -> "?" */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) return '?';

        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2, 'UTF-8'), 'UTF-8');
        }
        return mb_strtoupper(
            mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[count($words) - 1], 0, 1, 'UTF-8'),
            'UTF-8'
        );
    }

    /** A stable, reasonably distinct colour for a name. */
    public static function colourFor(string $name): string
    {
        if ($name === '') return '#9ca3af';
        // Hue from the hash; fixed saturation and lightness so every avatar sits
        // at the same weight and none of them fight the interface.
        $hue = hexdec(substr(md5(mb_strtolower($name, 'UTF-8')), 0, 4)) % 360;
        return 'hsl(' . $hue . ' 52% 46%)';
    }
}
