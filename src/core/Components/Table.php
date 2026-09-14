<?php

/**
 * A data table.
 *
 *   Table::make('items')->set(
 *       ['Title', 'Owner', ['Amount', 'is-numeric'], ''],
 *       array_map(fn($row) => [
 *           'key'   => $row['id'],                       // stable identity
 *           'cells' => [
 *               htmlspecialchars($row['title']),
 *               (string)Avatar::make()->set($row['owner']),
 *               ['html' => number_format($row['amount']), 'class' => 'is-numeric'],
 *               (string)IconButton::make()->set('trash')->danger()
 *                   ->with(['record' => $row['id']])->onClick('AppHandler.delete()'),
 *           ],
 *       ], $rows)
 *   )->empty('Nothing here yet.');
 *
 * WHY `key` MATTERS: the client reconciles an ->inner() update against the
 * existing DOM and matches children by id, then by key, then by position.
 * With keys, re-sorting a table MOVES the existing <tr> nodes instead of
 * rebuilding them — so scroll position, focus and any open editor inside a
 * cell survive the update. Without keys it still works, just positionally.
 *
 * Cell content is NOT escaped, because cells routinely hold rendered
 * components. Escape your own text, or wrap it in a Label, which escapes.
 *
 * Note the $expose line: without it every scalar property of a component is
 * written to its root element and posted back on each interaction. A table
 * has nothing a handler needs, so it declares the short list instead.
 */
class Table extends Component
{
    public $head   = "";
    public $body   = "";
    public $blank  = "";
    public $record = "";

    protected array $expose = ['record'];

    protected string $template = '
        <div class="table-wrap">
            <table class="table">
                <thead>{{$head}}</thead>
                <tbody>{{$body}}</tbody>
            </table>
            {{$blank}}
        </div>';

    /** Held privately so it is invisible to the attribute pass. */
    private string $emptyText = "";

    /**
     * @param array $headers each entry: 'Label' or ['Label', 'css-class']
     * @param array $rows    each entry: a list of cells, or
     *                       ['key' => mixed, 'class' => string, 'cells' => [...]]
     *                       each cell: string, or ['html' => string, 'class' => string]
     */
    public function set(array $headers, array $rows): static
    {
        $head = '<tr>';
        foreach ($headers as $header) {
            $label = is_array($header) ? ($header[0] ?? '') : $header;
            $class = is_array($header) ? ($header[1] ?? '') : '';
            $head .= '<th class="' . htmlspecialchars((string)$class, ENT_QUOTES, 'UTF-8') . '">'
                   . htmlspecialchars(trans((string)$label), ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $this->head = raw($head . '</tr>');

        $body = "";
        foreach ($rows as $row) {
            $cells = $row;
            $attrs = "";

            if (is_array($row) && array_key_exists('cells', $row)) {
                $cells = $row['cells'];
                if (isset($row['key']) && $row['key'] !== '') {
                    $attrs .= ' key="' . htmlspecialchars((string)$row['key'], ENT_QUOTES, 'UTF-8') . '"';
                }
                if (!empty($row['class'])) {
                    $attrs .= ' class="' . htmlspecialchars((string)$row['class'], ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            $body .= '<tr' . $attrs . '>';
            foreach ((array)$cells as $cell) {
                $html  = is_array($cell) ? ($cell['html'] ?? '') : $cell;
                $class = is_array($cell) ? ($cell['class'] ?? '') : '';
                $body .= '<td class="' . htmlspecialchars((string)$class, ENT_QUOTES, 'UTF-8') . '">' . $html . '</td>';
            }
            $body .= '</tr>';
        }

        $this->body = raw($body);
        $this->blank = $rows === [] ? $this->emptyBlock() : "";
        return $this;
    }

    /** Message shown when there are no rows. May be called before or after set(). */
    public function empty(string $message): static
    {
        $this->emptyText = trans($message);
        // (string) because $body holds markup, not a bare string, once set() has run.
        if ((string)$this->body === "") $this->blank = $this->emptyBlock();
        return $this;
    }

    private function emptyBlock(): Html
    {
        $message = $this->emptyText !== "" ? $this->emptyText : trans('Nothing to show.');
        return raw('<div class="table-empty">' . e($message) . '</div>');
    }
}
