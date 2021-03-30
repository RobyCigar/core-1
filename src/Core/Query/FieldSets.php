<?php
/*
 * Copyright 2021 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Query;

use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use IteratorAggregate;
use UnexpectedValueException;
use function array_map;
use function collect;
use function count;
use function is_array;

class FieldSets implements Arrayable, IteratorAggregate, Countable
{

    /**
     * @var array
     */
    private array $stack;

    /**
     * @param FieldSets|FieldSet|Enumerable|array|null $value
     * @return FieldSets
     */
    public static function cast($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof FieldSet) {
            return new self($value);
        }

        if (is_array($value) || $value instanceof Enumerable) {
            return self::fromArray($value);
        }

        if (is_null($value)) {
            return new self();
        }

        throw new UnexpectedValueException('Unexpected field sets value.');
    }

    /**
     * @param array|Enumerable $value
     * @return FieldSets
     */
    public static function fromArray($value): self
    {
        if (!is_array($value) && !$value instanceof Enumerable) {
            throw new \InvalidArgumentException('Expecting an array or enumerable object.');
        }

        return new self(...collect($value)->map(function ($fields, string $resourceType) {
            return FieldSet::cast($resourceType, $fields);
        })->values());
    }

    /**
     * @param FieldSets|FieldSet|array|null $value
     * @return FieldSets|null
     */
    public static function nullable($value): ?self
    {
        if (is_null($value)) {
            return null;
        }

        return self::cast($value);
    }

    /**
     * FieldSets constructor.
     *
     * @param FieldSet ...$fieldSets
     */
    public function __construct(FieldSet ...$fieldSets)
    {
        $this->stack = [];
        $this->push(...$fieldSets);
    }

    /**
     * @param FieldSet ...$fieldSets
     * @return $this
     */
    public function push(FieldSet ...$fieldSets)
    {
        foreach ($fieldSets as $fieldSet) {
            $this->stack[$fieldSet->type()] = $fieldSet;
        }

        return $this;
    }

    /**
     * @param string ...$resourceTypes
     * @return $this
     */
    public function forget(string ...$resourceTypes): self
    {
        foreach ($resourceTypes as $key) {
            unset($this->stack[$key]);
        }

        return $this;
    }

    /**
     * Get a field set by resource type.
     *
     * @param string $resourceType
     * @return FieldSet|null
     */
    public function get(string $resourceType): ?FieldSet
    {
        return $this->stack[$resourceType] ?? null;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @return array
     */
    public function fields(): array
    {
        return array_map(
            static fn (FieldSet $value) => $value->fields(),
            $this->stack,
        );
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->stack;
    }

    /**
     * @return Collection
     */
    public function collect(): Collection
    {
        return collect($this->stack);
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return array_map(
            static fn(FieldSet $fieldSet) => $fieldSet->toString(),
            $this->stack,
        );
    }

    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        yield from $this->stack;
    }

    /**
     * @inheritDoc
     */
    public function count()
    {
        return count($this->stack);
    }

}
