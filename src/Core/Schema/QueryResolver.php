<?php
/*
 * Copyright 2023 Cloud Creativity Limited
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

namespace LaravelJsonApi\Core\Schema;

use LaravelJsonApi\Contracts\Schema\Query as QueryContract;
use LaravelJsonApi\Contracts\Schema\Schema as SchemaContract;
use LaravelJsonApi\Core\Support\Str;
use function class_exists;

final class QueryResolver
{
    /**
     * @var string
     */
    private static string $defaultQuery = Query::class;

    /**
     * @var array<class-string<SchemaContract>,class-string<QueryContract>>
     */
    private static array $cache = [];

    /**
     * @var callable(class-string<SchemaContract>): class-string<QueryContract>|null
     */
    private static $instance = null;

    /**
     * @return callable(class-string<SchemaContract>): class-string<QueryContract>
     */
    public static function getInstance(): callable
    {
        if (self::$instance) {
            return self::$instance;
        }

        return self::$instance = new self();
    }

    /**
     * @param callable(class-string<SchemaContract>): class-string<QueryContract>|null $instance
     * @return void
     */
    public static function setInstance(?callable $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Manually register the query class to use for a resource schema.
     *
     * @param class-string<SchemaContract> $schemaClass
     * @param class-string<QueryContract> $queryClass
     * @return void
     */
    public static function register(string $schemaClass, string $queryClass): void
    {
        self::$cache[$schemaClass] = $queryClass;
    }

    /**
     * Set the default query class.
     *
     * @param class-string<QueryContract> $queryClass
     * @return void
     */
    public static function useDefault(string $queryClass): void
    {
        assert(class_exists($queryClass), 'Expecting a default query class that exists.');

        self::$defaultQuery = $queryClass;
    }

    /**
     * Get the default query class.
     *
     * @return class-string<QueryContract>
     */
    public static function defaultResource(): string
    {
        return self::$defaultQuery;
    }

    /**
     * QueryResolver constructor
     */
    private function __construct()
    {
    }

    /**
     * Resolve the fully-qualified query class from the fully-qualified schema class.
     *
     * @param class-string<SchemaContract> $schemaClass
     * @return class-string<QueryContract>
     */
    public function __invoke(string $schemaClass): string
    {
        if (isset(self::$cache[$schemaClass])) {
            return self::$cache[$schemaClass];
        }

        $guess = Str::replaceLast('Schema', 'Resource', $schemaClass);

        if (class_exists($guess)) {
            return self::$cache[$schemaClass] = $guess;
        }

        return self::$cache[$schemaClass] = self::$defaultQuery;
    }
}
