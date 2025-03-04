<?php

namespace Dustin\ShopwareUtils\Core\Framework\Struct;

use Shopware\Core\Framework\Util\Json;
use Traversable;

class Encapsulation implements \IteratorAggregate, \JsonSerializable, \Stringable
{

    public function __construct(private readonly array $data = []) {}

    public function get(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    /**
     * @param string[] $fields
     */
    public function getList(array $fields): array
    {
        $result = [];

        foreach($fields as $field) {
            $result[$field] = $this->get($field);
        }

        return $result;
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->data);
    }

    public function getFields(): array
    {
        return \array_keys($this->data);
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function merge(array $data): self
    {
        return new self(\array_merge($this->data, $data));
    }

    public function getIterator(): Traversable
    {
        yield from $this->data;
    }

    public function __toString(): string
    {
        return Json::encode($this);
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }
}