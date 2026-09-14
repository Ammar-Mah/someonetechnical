<?php

/**
 * A dropdown of options — single or multiple choice, with optional search.
 *
 *   // multiple
 *   Picker::make('tags')->set($tags, $selected)->name('tags')
 *       ->searchable()->onChange('AppHandler.filter()')
 *
 *   // single
 *   Picker::make('owner')->single()->set($users, $ownerId)->name('owner_id')
 *       ->placeholder('Unassigned')->onChange('AppHandler.assign()')
 *
 * Built on <details>, so it opens and closes without JavaScript. ui.js adds
 * the rest: it keeps the summary pills and the hidden state input in sync,
 * filters on search, and closes the panel on an outside click.
 *
 * Two ways to read the selection, each the natural shape for its context:
 *   - inside a Form  the option inputs are named "<name>[]", so the submitted
 *                    value is an array
 *   - via onChange   the hidden state input carries every selected value
 *                    joined by "|", and that is what arrives as `value`
 *                    (explode('|', …) it, dropping empties)
 */
class Picker extends Component
{
    public $options     = "";
    public $summary     = "";
    public $name        = "";
    public $value       = "";
    public $multi       = "1";
    public $placeholder = "";
    public $search      = "";
    public $changeAttr  = "";
    public $caret       = "";
    public $record      = "";

    protected string $template = '
        <details class="picker" data-multi="{{$multi}}">
            <summary>
                <span class="picker-value">{{$summary}}</span>
                <span class="picker-caret">{{$caret}}</span>
            </summary>
            <div class="picker-panel">
                <input type="hidden" class="picker-state" value="{{$value}}" {{$changeAttr}}>
                {{$search}}
                {{$options}}
            </div>
        </details>';

    public function mount()
    {
        $this->caret       = Icon::make()->set('chevron-down', 'xs');
        $this->placeholder = trans('Select…');
    }

    /**
     * @param array $items    [value => label]
     * @param mixed $selected one value, or an array of values
     */
    public function set(array $items, $selected = []): static
    {
        $selected = is_array($selected) ? $selected : ($selected === null || $selected === '' ? [] : [$selected]);
        $selected = array_map('strval', $selected);

        $isMulti   = $this->multi === "1";
        $inputType = $isMulti ? 'checkbox' : 'radio';

        // Radios are only mutually exclusive if they share a name, so a
        // single-choice picker always gets one — falling back to its own id when
        // the caller did not name a form field. Checkboxes need no such crutch.
        $fieldName = $this->name !== ""
            ? $this->name . ($isMulti ? '[]' : '')
            : ($isMulti ? "" : 'picker-' . $this->id);

        $options = "";
        $pills   = "";
        $chosen  = [];

        foreach ($items as $value => $label) {
            $value    = (string)$value;
            $isOn     = in_array($value, $selected, true);
            $safeVal  = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $safeText = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');

            $options .= '<label class="picker-option" data-label="' . $safeText . '">'
                      . '<input type="' . $inputType . '"'
                      . ($fieldName === "" ? "" : ' name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '"')
                      . ' value="' . $safeVal . '"' . ($isOn ? ' checked' : '') . '>'
                      . '<span>' . $safeText . '</span></label>';

            if ($isOn) {
                $chosen[] = $value;
                $pills   .= '<span class="picker-pill">' . $safeText . '</span>';
            }
        }

        $this->options = raw($options);
        $this->value   = implode('|', $chosen);
        $this->summary = raw($pills !== ""
            ? $pills
            : '<span class="picker-placeholder">' . e($this->placeholder) . '</span>');

        return $this;
    }

    /** One choice instead of many. Call before set(). */
    public function single(bool $single = true): static
    {
        $this->multi = $single ? "0" : "1";
        return $this;
    }

    /** Add a filter box above the options. */
    public function searchable(bool $on = true): static
    {
        $this->search = $on
            ? raw('<input type="text" class="input picker-search" placeholder="'
                  . e(trans('Search…')) . '">')
            : '';
        return $this;
    }

    /** Shown when nothing is selected. Call before set(). */
    public function placeholder(string $text): static
    {
        $this->placeholder = trans($text);
        return $this;
    }

    /** The form field name. Call before set(). */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Handler to run when the selection changes.
     * Takes 'AppHandler.filter()' or [AppHandler::class, 'filter'].
     */
    public function onChange(string|array $handler): static
    {
        $handler = self::handler($handler);
        $this->changeAttr = raw('actions xonchange="' . e($handler) . '"');
        return $this;
    }
}
