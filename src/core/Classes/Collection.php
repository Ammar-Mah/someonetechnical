<?php


class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable {
    protected $items = [];

    public function __construct($items = []) {
        $this->items = is_array($items) ? $items : [$items];
    }

    public static function make($items = []) {
        return new static($items);
    }

    public function all() {
        return $this->items;
    }

    public function first() {
        return $this->items[0] ?? null;
    }

    public function last() {
        return end($this->items);
    }

    public function map(callable $callback) {
        return new static(array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null) {
        if ($callback) {
            return new static(array_values(array_filter($this->items, $callback)));
        }
        return new static(array_values(array_filter($this->items)));
    }

    public function whereIn($column, array $values) {
        $valuesSet = array_flip($values);
        return new static(array_values(array_filter($this->items, function ($item) use ($column, $valuesSet) {
            $attr = $this->getAttribute($item, $column);
            return isset($valuesSet[$attr]);
        })));
    }

    public function whereNotIn($column, array $values) {
        $valuesSet = array_flip($values);
        return new static(array_values(array_filter($this->items, function ($item) use ($column, $valuesSet) {
            $attr = $this->getAttribute($item, $column);
            return !isset($valuesSet[$attr]);
        })));
    }

    public function each(callable $callback) {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    public function pluck($valueKey, $keyColumn = null) {
        $valueKeyResolved = (stripos($valueKey, ' AS ') !== false)
            ? trim(substr($valueKey, stripos($valueKey, ' AS ') + 4))
            : $valueKey;
        $keyColumnResolved = $keyColumn !== null
            ? ((stripos($keyColumn, ' AS ') !== false) ? trim(substr($keyColumn, stripos($keyColumn, ' AS ') + 4)) : $keyColumn)
            : null;
        $arr = [];
        foreach ($this->items as $item) {
            $value = $this->getAttribute($item, $valueKeyResolved);
            if ($keyColumnResolved !== null) {
                $arr[$this->getAttribute($item, $keyColumnResolved)] = $value;
            } else {
                $arr[] = $value;
            }
        }
        return $arr;
    }

    protected function getAttribute($item, $key) {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }
        if ($item instanceof Model) {
            return $item->attributes[$key] ?? null;
        }
        return $item->$key ?? null;
    }

    public function toArray() {
        return array_map(function ($value) {
            if ($value instanceof Model || $value instanceof Collection) {
                return $value->toArray();
            }
            return $value;
        }, $this->items);
    }

    public function jsonSerialize(): mixed {
        return $this->toArray();
    }

    public function getIterator(): \Traversable {
        return new ArrayIterator($this->items);
    }

    public function offsetExists($offset): bool {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void {
        unset($this->items[$offset]);
    }

    public function count(): int {
        return count($this->items);
    }
}
