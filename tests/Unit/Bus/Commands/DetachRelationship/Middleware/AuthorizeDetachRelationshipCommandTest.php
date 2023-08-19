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

namespace LaravelJsonApi\Core\Tests\Unit\Bus\Commands\DetachRelationship\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Auth\ResourceAuthorizer;
use LaravelJsonApi\Core\Auth\ResourceAuthorizerFactory;
use LaravelJsonApi\Core\Bus\Commands\DetachRelationship\DetachRelationshipCommand;
use LaravelJsonApi\Core\Bus\Commands\DetachRelationship\Middleware\AuthorizeDetachRelationshipCommand;
use LaravelJsonApi\Core\Bus\Commands\Result;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Core\Document\Input\Values\ListOfResourceIdentifiers;
use LaravelJsonApi\Core\Document\Input\Values\ResourceId;
use LaravelJsonApi\Core\Document\Input\Values\ResourceType;
use LaravelJsonApi\Core\Extensions\Atomic\Operations\UpdateToMany;
use LaravelJsonApi\Core\Extensions\Atomic\Values\OpCodeEnum;
use LaravelJsonApi\Core\Extensions\Atomic\Values\Ref;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class AuthorizeDetachRelationshipCommandTest extends TestCase
{
    /**
     * @var ResourceType
     */
    private ResourceType $type;

    /**
     * @var ResourceAuthorizerFactory&MockObject
     */
    private ResourceAuthorizerFactory&MockObject $authorizerFactory;

    /**
     * @var AuthorizeDetachRelationshipCommand
     */
    private AuthorizeDetachRelationshipCommand $middleware;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->type = new ResourceType('posts');

        $this->middleware = new AuthorizeDetachRelationshipCommand(
            $this->authorizerFactory = $this->createMock(ResourceAuthorizerFactory::class),
        );
    }

    /**
     * @return void
     */
    public function testItPassesAuthorizationWithRequest(): void
    {
        $command = DetachRelationshipCommand::make(
            $request = $this->createMock(Request::class),
            new UpdateToMany(
                OpCodeEnum::Remove,
                new Ref(type: $this->type, id: new ResourceId('123'), relationship: 'tags'),
                new ListOfResourceIdentifiers(),
            ),
        )->withModel($model = new stdClass());

        $this->willAuthorize($request, $model, 'tags', null);

        $expected = Result::ok();

        $actual = $this->middleware->handle($command, function ($cmd) use ($command, $expected): Result {
            $this->assertSame($command, $cmd);
            return $expected;
        });

        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testItPassesAuthorizationWithoutRequest(): void
    {
        $command = DetachRelationshipCommand::make(
            null,
            new UpdateToMany(
                OpCodeEnum::Remove,
                new Ref(type: $this->type, id: new ResourceId('123'), relationship: 'tags'),
                new ListOfResourceIdentifiers(),
            ),
        )->withModel($model = new stdClass());

        $this->willAuthorize(null, $model, 'tags', null);

        $expected = Result::ok();

        $actual = $this->middleware->handle($command, function ($cmd) use ($command, $expected): Result {
            $this->assertSame($command, $cmd);
            return $expected;
        });

        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testItFailsAuthorizationWithException(): void
    {
        $command = DetachRelationshipCommand::make(
            $request = $this->createMock(Request::class),
            new UpdateToMany(
                OpCodeEnum::Remove,
                new Ref(type: $this->type, id: new ResourceId('123'), relationship: 'tags'),
                new ListOfResourceIdentifiers(),
            ),
        )->withModel($model = new stdClass());

        $this->willAuthorizeAndThrow(
            $request,
            $model,
            'tags',
            $expected = new AuthorizationException('Boom!'),
        );

        try {
            $this->middleware->handle(
                $command,
                fn() => $this->fail('Expecting next middleware to not be called.'),
            );
            $this->fail('Middleware did not throw an exception.');
        } catch (AuthorizationException $actual) {
            $this->assertSame($expected, $actual);
        }
    }

    /**
     * @return void
     */
    public function testItFailsAuthorizationWithErrorList(): void
    {
        $command = DetachRelationshipCommand::make(
            $request = $this->createMock(Request::class),
            new UpdateToMany(
                OpCodeEnum::Remove,
                new Ref(type: $this->type, id: new ResourceId('123'), relationship: 'tags'),
                new ListOfResourceIdentifiers(),
            ),
        )->withModel($model = new stdClass());

        $this->willAuthorize($request, $model, 'tags', $expected = new ErrorList());

        $result = $this->middleware->handle(
            $command,
            fn() => $this->fail('Expecting next middleware not to be called.'),
        );

        $this->assertTrue($result->didFail());
        $this->assertSame($expected, $result->errors());
    }

    /**
     * @return void
     */
    public function testItSkipsAuthorization(): void
    {
        $command = DetachRelationshipCommand::make(
            $this->createMock(Request::class),
            new UpdateToMany(
                OpCodeEnum::Remove,
                new Ref(type: $this->type, id: new ResourceId('123'), relationship: 'tags'),
                new ListOfResourceIdentifiers(),
            ),
        )->withModel(new stdClass())->skipAuthorization();


        $this->authorizerFactory
            ->expects($this->never())
            ->method($this->anything());

        $expected = Result::ok();

        $actual = $this->middleware->handle($command, function ($cmd) use ($command, $expected): Result {
            $this->assertSame($command, $cmd);
            return $expected;
        });

        $this->assertSame($expected, $actual);
    }

    /**
     * @param Request|null $request
     * @param stdClass $model
     * @param string $fieldName
     * @param ErrorList|null $expected
     * @return void
     */
    private function willAuthorize(?Request $request, stdClass $model, string $fieldName, ?ErrorList $expected): void
    {
        $this->authorizerFactory
            ->expects($this->once())
            ->method('make')
            ->with($this->identicalTo($this->type))
            ->willReturn($authorizer = $this->createMock(ResourceAuthorizer::class));

        $authorizer
            ->expects($this->once())
            ->method('detachRelationship')
            ->with($this->identicalTo($request), $this->identicalTo($model), $this->identicalTo($fieldName))
            ->willReturn($expected);
    }

    /**
     * @param Request|null $request
     * @param stdClass $model
     * @param string $fieldName
     * @param AuthorizationException $expected
     * @return void
     */
    private function willAuthorizeAndThrow(
        ?Request $request,
        stdClass $model,
        string $fieldName,
        AuthorizationException $expected,
    ): void
    {
        $this->authorizerFactory
            ->expects($this->once())
            ->method('make')
            ->with($this->identicalTo($this->type))
            ->willReturn($authorizer = $this->createMock(ResourceAuthorizer::class));

        $authorizer
            ->expects($this->once())
            ->method('detachRelationship')
            ->with($this->identicalTo($request), $this->identicalTo($model), $this->identicalTo($fieldName))
            ->willThrowException($expected);
    }
}
