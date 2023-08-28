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

namespace LaravelJsonApi\Core\Bus\Queries\FetchMany;

use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Http\Hooks\IndexImplementation;
use LaravelJsonApi\Core\Bus\Queries\Query\Query;
use LaravelJsonApi\Core\Query\Input\QueryMany;

class FetchManyQuery extends Query
{
    /**
     * @var IndexImplementation|null
     */
    private ?IndexImplementation $hooks = null;

    /**
     * Fluent constructor.
     *
     * @param Request|null $request
     * @param QueryMany $input
     * @return self
     */
    public static function make(?Request $request, QueryMany $input): self
    {
        return new self($request, $input);
    }

    /**
     * FetchManyQuery constructor
     *
     * @param Request|null $request
     * @param QueryMany $input
     */
    public function __construct(
        ?Request $request,
        private readonly QueryMany $input,
    ) {
        parent::__construct($request);
    }

    /**
     * @return QueryMany
     */
    public function input(): QueryMany
    {
        return $this->input;
    }

    /**
     * Set the hooks implementation.
     *
     * @param IndexImplementation|null $hooks
     * @return $this
     */
    public function withHooks(?IndexImplementation $hooks): self
    {
        $copy = clone $this;
        $copy->hooks = $hooks;

        return $copy;
    }

    /**
     * @return IndexImplementation|null
     */
    public function hooks(): ?IndexImplementation
    {
        return $this->hooks;
    }
}
