<?php

/**
 * The bar across the top of the application.
 *
 *   AppHeader::make('header')
 *       ->left(Logo::make())
 *       ->slot(TextInput::make('search')->type('search')->set('', 'Search…'))
 *       ->right([
 *           IconButton::make()->set('bell')->tooltip('Notifications'),
 *           IconButton::make('theme')->set('moon')->tooltip('Theme')
 *               ->onClick('AppHandler.toggleTheme()'),
 *       ]);
 *
 * The middle is the slot, so it stretches and the two ends stay put.
 */
class AppHeader extends Component
{
    public $left  = "";
    public $right = "";

    protected string $template = '
        <header class="app-header">
            <div class="flex items-center gap-3 flex-none">{{$left}}</div>
            <div class="flex-1 min-w-0">{{$slot}}</div>
            <div class="flex items-center gap-1 flex-none">{{$right}}</div>
        </header>';

    /** @param string|array|Component $content */
    public function left($content): static
    {
        $this->left = raw(is_array($content) ? implode('', array_map('strval', $content)) : (string)$content);
        return $this;
    }

    /** @param string|array|Component $content */
    public function right($content): static
    {
        $this->right = raw(is_array($content) ? implode('', array_map('strval', $content)) : (string)$content);
        return $this;
    }
}
