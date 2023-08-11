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

namespace LaravelJsonApi\Core\Http\Actions\Update\Middleware;

use Closure;
use LaravelJsonApi\Core\Auth\ResourceAuthorizerFactory;
use LaravelJsonApi\Core\Http\Actions\Update\HandlesUpdateActions;
use LaravelJsonApi\Core\Http\Actions\Update\UpdateActionInput;
use LaravelJsonApi\Core\Responses\DataResponse;

class AuthorizeUpdateAction implements HandlesUpdateActions
{
    /**
     * AuthorizeUpdateAction constructor
     *
     * @param ResourceAuthorizerFactory $authorizerFactory
     */
    public function __construct(private readonly ResourceAuthorizerFactory $authorizerFactory)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(UpdateActionInput $action, Closure $next): DataResponse
    {
        $this->authorizerFactory
            ->make($action->type())
            ->updateOrFail($action->request(), $action->modelOrFail());

        return $next($action);
    }
}
