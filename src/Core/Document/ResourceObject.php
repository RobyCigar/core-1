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

namespace LaravelJsonApi\Core\Document;

use ArrayAccess;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use LaravelJsonApi\Core\Document\Concerns\Serializable;
use LogicException;
use UnexpectedValueException;
use function json_decode;
use function strval;

class ResourceObject implements IteratorAggregate, JsonSerializable, ArrayAccess, Jsonable
{

    use Serializable;

    /**
     * @var string
     */
    private string $type;

    /**
     * @var string|null
     */
    private ?string $id;

    /**
     * @var array
     */
    private array $attributes;

    /**
     * @var array
     */
    private array $relationships;

    /**
     * @var array
     */
    private array $meta;

    /**
     * @var array
     */
    private array $links;

    /**
     * @var Collection
     */
    private Collection $fieldValues;

    /**
     * @var Collection
     */
    private Collection $fieldNames;

    /**
     * @param ResourceObject|Enumerable|array $value
     * @return static
     */
    public static function cast($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value) || $value instanceof Enumerable) {
            return self::fromArray($value);
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        throw new InvalidArgumentException('Unexpected resource object.');
    }

    /**
     * @param string $json
     * @return static
     */
    public static function fromString(string $json): self
    {
        $decoded = json_decode($json, true);

        if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
            return self::fromArray($decoded['data']);
        }

        throw new UnexpectedValueException('Expecting JSON to be a JSON:API document with a top-level data member.');
    }

    /**
     * Create a resource object from the data member of a JSON document.
     *
     * @param array|Enumerable $data
     * @return ResourceObject
     */
    public static function fromArray($data): self
    {
        if ($data instanceof Enumerable) {
            $data = $data->all();
        }

        if (!is_array($data) || !isset($data['type'])) {
            throw new InvalidArgumentException('Expecting an array resource with a type field.');
        }

        return new self(
            $data['type'],
            $data['id'] ?? null,
            $data['attributes'] ?? [],
            $data['relationships'] ?? [],
            $data['meta'] ?? [],
            $data['links'] ?? []
        );
    }

    /**
     * ResourceObject constructor.
     *
     * @param string $type
     * @param string|null $id
     * @param array $attributes
     * @param array $relationships
     * @param array $meta
     * @param array $links
     */
    public function __construct(
        string $type,
        ?string $id,
        array $attributes,
        array $relationships = [],
        array $meta = [],
        array $links = []
    ) {
        if (empty($type)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->type = $type;
        $this->id = $id ?: null;
        $this->attributes = $attributes;
        $this->relationships = $relationships;
        $this->meta = $meta;
        $this->links = $links;
        $this->normalize();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $this->fieldNames = clone $this->fieldNames;
        $this->fieldValues = clone $this->fieldValues;
    }

    /**
     * @param string $field
     * @return mixed
     */
    public function __get($field)
    {
        return $this->offsetGet($field);
    }

    /**
     * @param $field
     * @param $value
     */
    public function __set($field, $value)
    {
        throw new LogicException('Resource object is immutable.');
    }

    /**
     * @param $field
     * @return bool
     */
    public function __isset($field)
    {
        return $this->offsetExists($field);
    }

    /**
     * @param $field
     */
    public function __unset($field)
    {
        throw new LogicException('Resource object is immutable.');
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->fieldValues->offsetGet($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        throw new LogicException('Resource object is immutable.');
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        throw new LogicException('Resource object is immutable.');
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Return a new instance with the specified type.
     *
     * @param string $type
     * @return ResourceObject
     */
    public function withType(string $type): self
    {
        if (empty($type)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $copy = clone $this;
        $copy->type = $type;
        $copy->normalize();

        return $copy;
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Return a new instance with the specified id.
     *
     * @param string|null $id
     * @return ResourceObject
     */
    public function withId(?string $id): self
    {
        $copy = clone $this;
        $copy->id = $id ?: null;
        $copy->normalize();

        return $copy;
    }

    /**
     * Return a new instance without an id.
     *
     * @return ResourceObject
     */
    public function withoutId(): self
    {
        return $this->withId(null);
    }

    /**
     * @return Collection
     */
    public function getAttributes(): Collection
    {
        return collect($this->attributes);
    }

    /**
     * Is the field an attribute?
     *
     * @param string $field
     * @return bool
     */
    public function isAttribute(string $field): bool
    {
        return array_key_exists($field, $this->attributes);
    }

    /**
     * Return a new instance with the provided attributes.
     *
     * @param array|Collection $attributes
     * @return ResourceObject
     */
    public function withAttributes($attributes): self
    {
        $copy = clone $this;
        $copy->attributes = collect($attributes)->all();
        $copy->normalize();

        return $copy;
    }

    /**
     * Return a new instance without attributes.
     *
     * @return ResourceObject
     */
    public function withoutAttributes(): self
    {
        return $this->withAttributes([]);
    }

    /**
     * @return Collection
     */
    public function getRelationships(): Collection
    {
        return collect($this->relationships);
    }

    /**
     * Is the field a relationship?
     *
     * @param string $field
     * @return bool
     */
    public function isRelationship(string $field): bool
    {
        return array_key_exists($field, $this->relationships);
    }

    /**
     * Return a new instance with the provided relationships.
     *
     * @param array|Collection $relationships
     * @return ResourceObject
     */
    public function withRelationships($relationships): self
    {
        $copy = clone $this;
        $copy->relationships = collect($relationships)->all();
        $copy->normalize();

        return $copy;
    }

    /**
     * Return a new instance without relationships.
     *
     * @return ResourceObject
     */
    public function withoutRelationships(): self
    {
        return $this->withRelationships([]);
    }

    /**
     * Get the data value of all relationships.
     *
     * @return Collection
     */
    public function getRelations(): Collection
    {
        return $this->getRelationships()->filter(function (array $relation) {
            return array_key_exists('data', $relation);
        })->map(function (array $relation) {
            return $relation['data'];
        });
    }

    /**
     * @return Collection
     */
    public function getMeta(): Collection
    {
        return collect($this->meta);
    }

    /**
     * Return a new instance with the provided meta.
     *
     * @param array|Collection $meta
     * @return ResourceObject
     */
    public function withMeta($meta): self
    {
        $copy = clone $this;
        $copy->meta = collect($meta)->all();

        return $copy;
    }

    /**
     * Return a new instance with the provided relationship meta.
     *
     * @param string $relation
     * @param array $meta
     * @return $this
     */
    public function withRelationshipMeta(string $relation, array $meta): self
    {
        $copy = clone $this;
        $copy->relationships[$relation] = $copy->relationships[$relation] ?? [];
        $copy->relationships[$relation]['meta'] = $meta;
        $copy->normalize();

        return $copy;
    }

    /**
     * Return a new instance without meta.
     *
     * @return ResourceObject
     */
    public function withoutMeta(): self
    {
        $copy = clone $this;
        $copy->meta = [];
        $copy->relationships = collect($copy->relationships)->map(
            fn(array $relation) => collect($relation)->forget('meta')->all()
        )->all();
        $copy->normalize();

        return $copy;
    }

    /**
     * @return Collection
     */
    public function getLinks(): Collection
    {
        return collect($this->links);
    }

    /**
     * Return a new instance with the provided links.
     *
     * @param $links
     * @return ResourceObject
     */
    public function withLinks($links): self
    {
        $copy = clone $this;
        $copy->links = collect($links)->all();

        return $copy;
    }

    /**
     * Return a new instance without links.
     *
     * @return ResourceObject
     */
    public function withoutLinks(): self
    {
        $copy = clone $this;
        $copy->links = [];
        $copy->relationships = collect($copy->relationships)->map(
            fn(array $relation) => collect($relation)->forget('links')->all()
        )->all();
        $copy->normalize();

        return $copy;
    }

    /**
     * Get all the field names.
     *
     * @return Collection
     */
    public function fields(): Collection
    {
        return $this->fieldNames->values();
    }

    /**
     * Get a field value.
     *
     * @param string $field
     * @param mixed $default
     * @return mixed
     */
    public function get(string $field, $default = null)
    {
        return Arr::get($this->all(), $field, $default);
    }

    /**
     * Do the fields exist?
     *
     * @param string ...$fields
     * @return bool
     */
    public function has(string ...$fields): bool
    {
        return $this->fieldNames->has($fields);
    }

    /**
     * Return a new instance with the supplied attribute/relationship fields removed.
     *
     * @param string ...$fields
     * @return ResourceObject
     */
    public function forget(string ...$fields): self
    {
        $copy = clone $this;
        $copy->attributes = $this->getAttributes()->forget($fields)->all();
        $copy->relationships = $this->getRelationships()->forget($fields)->all();
        $copy->normalize();

        return $copy;
    }

    /**
     * Return a new instance that only has the specified attribute/relationship fields.
     *
     * @param string ...$fields
     * @return ResourceObject
     */
    public function only(string ...$fields): self
    {
        $forget = $this->fields()->reject(function ($value) use ($fields) {
            return in_array($value, $fields, true);
        });

        return $this->forget(...$forget);
    }

    /**
     * Return a new instance with a new attribute/relationship field value.
     *
     * The field must exist, otherwise it cannot be determined whether to replace
     * either an attribute or a relationship.
     *
     * If the field is a relationship, the `data` member of that relationship will
     * be replaced.
     *
     * @param string $field
     * @param $value
     * @return ResourceObject
     * @throws \OutOfBoundsException if the field does not exist.
     */
    public function replace(string $field, $value): self
    {
        if ('type' === $field) {
            return $this->putIdentifier($value, $this->id);
        }

        if ('id' === $field) {
            return $this->putIdentifier($this->type, $value);
        }

        if ($this->isAttribute($field)) {
            return $this->putAttr($field, $value);
        }

        if ($this->isRelationship($field)) {
            return $this->putRelation($field, $value);
        }

        throw new \OutOfBoundsException("Field {$field} is not an attribute or relationship.");
    }

    /**
     * Return a new resource object with the provided one merged.
     *
     * @param mixed $other
     * @return $this
     */
    public function merge($other): self
    {
        $other = self::cast($other);

        $copy = clone $this;

        foreach ($other->attributes as $name => $value) {
            $copy->attributes[$name] = $value;
        }

        foreach ($other->relationships as $name => $relation) {
            $copy->relationships[$name] = array_replace(
                $this->relationships[$name] ?? [],
                $relation,
            );
        }

        $copy->normalize();

        return $copy;
    }

    /**
     * Set a field.
     *
     * Sets the provided value as a relation if it is already defined as a relation.
     * Otherwise, sets it as an attribute.
     *
     * @param string $field
     * @param mixed|null $value
     * @return ResourceObject
     */
    public function put(string $field, $value): self
    {
        if ($this->isRelationship($field)) {
            return $this->putRelation($field, $value);
        }

        return $this->putAttr($field, $value);
    }

    /**
     * Set an attribute.
     *
     * @param string $field
     * @param mixed|null $value
     * @return ResourceObject
     */
    public function putAttr(string $field, $value): self
    {
        $copy = clone $this;
        $copy->attributes[$field] = $value;
        $copy->normalize();

        return $copy;
    }

    /**
     * Set a relation.
     *
     * @param string $field
     * @param array|null $value
     * @return ResourceObject
     */
    public function putRelation(string $field, ?array $value): self
    {
        if (is_array($value) && isset($value['id']) && $value['id'] instanceof UrlRoutable) {
            $value['id'] = strval($value['id']->getRouteKey());
        } else if (!empty($value) && is_array($value) && !Arr::isAssoc($value)) {
            $value = collect($value)->map(function (array $data) {
                if (isset($data['id']) && $data['id'] instanceof UrlRoutable) {
                    $data['id'] = strval($data['id']->getRouteKey());
                }

                return $data;
            })->all();
        }

        $copy = clone $this;
        $copy->relationships[$field] = $copy->relationships[$field] ?? [];
        $copy->relationships[$field]['data'] = $value;
        $copy->normalize();

        return $copy;
    }

    /**
     * Convert a validation key to a JSON pointer.
     *
     * @param string $key
     * @param string $prefix
     * @return string
     */
    public function pointer(string $key, string $prefix = ''): string
    {
        $prefix = rtrim($prefix, '/');

        if ('type' === $key) {
            return $prefix . '/type';
        }

        if ('id' === $key) {
            return $prefix . '/id';
        }

        $parts = collect(explode('.', $key));
        $field = $parts->first();

        if ($this->isAttribute($field)) {
            return $prefix . '/attributes/' . $parts->implode('/');
        }

        if ($this->isRelationship($field)) {
            $name = 1 < $parts->count() ? $field . '/' . $parts->put(0, 'data')->implode('/') : $field;
            return $prefix . "/relationships/{$name}";
        }

        return $prefix ? $prefix : '/';
    }

    /**
     * Convert a validation key to a JSON pointer for a relationship object within the resource.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function pointerForRelationship(string $key, string $default = '/'): string
    {
        $field = collect(explode('.', $key))->first();

        if (!$this->isRelationship($field)) {
            throw new InvalidArgumentException("Field {$field} is not a relationship.");
        }

        $pointer = $this->pointer($key);

        return Str::after($pointer, "relationships/{$field}") ?: $default;
    }

    /**
     * Get the values of all fields.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->fieldValues->all();
    }

    /**
     * Dump the resource object.
     *
     * @return $this
     */
    public function dump(): self
    {
        dump($this->jsonSerialize());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        return $this->fieldValues->getIterator();
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return collect([
            'type' => $this->type,
            'id' => $this->id,
            'attributes' => $this->attributes,
            'relationships' => collect($this->relationships)->filter(
                fn(array $relation) => Arr::hasAny($relation, ['links', 'data', 'meta'])
            )->all(),
            'links' => $this->links,
            'meta' => $this->meta,
        ])->filter()->all();
    }

    /**
     * @return Collection
     */
    private function fieldValues(): Collection
    {
        return collect($this->attributes)->merge($this->getRelations())->merge([
            'type' => $this->type,
            'id' => $this->id,
        ])->sortKeys();
    }

    /**
     * @return Collection
     */
    private function fieldNames(): Collection
    {
        $fields = collect(['type', 'id'])
            ->merge(collect($this->attributes)->keys())
            ->merge(collect($this->relationships)->keys())
            ->sort()
            ->values();

        return $fields->combine($fields);
    }

    /**
     * @return void
     */
    private function normalize(): void
    {
        ksort($this->attributes);
        ksort($this->relationships);

        $this->fieldValues = $this->fieldValues();
        $this->fieldNames = $this->fieldNames();
    }

    /**
     * @param string $type
     * @param string|null $id
     * @return ResourceObject
     */
    private function putIdentifier(string $type, ?string $id): self
    {
        $copy = clone $this;
        $copy->type = $type;
        $copy->id = $id;
        $copy->normalize();

        return $copy;
    }

}
