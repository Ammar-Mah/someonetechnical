<?php

class Request
{
    private array $attributes = [];

    public function __construct(array $data = [])
    {
        $this->attributes = $data;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        $hyphenKey = str_replace('_', '-', $name);
        if (array_key_exists($hyphenKey, $this->attributes)) {
            return $this->attributes[$hyphenKey];
        }

        return null;
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        $underscoreKey = str_replace('-', '_', $key);
        if (array_key_exists($underscoreKey, $this->attributes)) {
            return $this->attributes[$underscoreKey];
        }

        return $default;
    }

    public function handler(): string
    {
        return (string)($this->attributes['func'] ?? '');
    }

    public function handlerName(): string
    {
        $handler = $this->handler();
        $pos = strpos($handler, '(');
        if ($pos === false) {
            return trim($handler);
        }
        return trim(substr($handler, 0, $pos));
    }

    public function handlerParams(): array
    {
        $handler = $this->handler();
        $open = strpos($handler, '(');
        $close = strrpos($handler, ')');
        if ($open === false || $close === false || $close <= $open) {
            return [];
        }

        $inside = trim(substr($handler, $open + 1, $close - $open - 1));
        if ($inside === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $inside)), fn($v) => $v !== ''));
    }

    public function all(): array
    {
        return $this->attributes;
    }

    public static function create(array $data): self
    {
        return new self($data);
    }
}
