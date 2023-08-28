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

namespace LaravelJsonApi\Core\Tests\Unit\Bus\Queries\FetchOne\Middleware;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Validation\Container as ValidatorContainer;
use LaravelJsonApi\Contracts\Validation\Factory;
use LaravelJsonApi\Contracts\Validation\QueryErrorFactory;
use LaravelJsonApi\Contracts\Validation\QueryOneValidator;
use LaravelJsonApi\Core\Bus\Queries\FetchOne\FetchOneQuery;
use LaravelJsonApi\Core\Bus\Queries\FetchOne\Middleware\ValidateFetchOneQuery;
use LaravelJsonApi\Core\Bus\Queries\Result;
use LaravelJsonApi\Core\Document\ErrorList;
use LaravelJsonApi\Core\Extensions\Atomic\Results\Result as Payload;
use LaravelJsonApi\Core\Query\Input\QueryOne;
use LaravelJsonApi\Core\Values\ResourceId;
use LaravelJsonApi\Core\Values\ResourceType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidateFetchOneQueryTest extends TestCase
{
    /**
     * @var ResourceType
     */
    private ResourceType $type;

    /**
     * @var ValidatorContainer&MockObject
     */
    private ValidatorContainer&MockObject $validators;

    /**
     * @var QueryErrorFactory&MockObject
     */
    private QueryErrorFactory&MockObject $errorFactory;

    /**
     * @var ValidateFetchOneQuery
     */
    private ValidateFetchOneQuery $middleware;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->type = new ResourceType('posts');

        $this->middleware = new ValidateFetchOneQuery(
            $this->validators = $this->createMock(ValidatorContainer::class),
            $this->errorFactory = $this->createMock(QueryErrorFactory::class),
        );
    }

    /**
     * @return void
     */
    public function testItPassesValidation(): void
    {
        $query = FetchOneQuery::make(
            $request = $this->createMock(Request::class),
            $input = new QueryOne($this->type, new ResourceId('123'), ['foo' => 'bar']),
        );

        $queryValidator = $this->willValidate($request);

        $queryValidator
            ->expects($this->once())
            ->method('make')
            ->with($this->identicalTo($input))
            ->willReturn($validator = $this->createMock(Validator::class));

        $validator
            ->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $validator
            ->expects($this->once())
            ->method('validated')
            ->willReturn($validated = ['baz' => 'bat']);

        $expected = Result::ok(
            new Payload(null, true),
        );

        $actual = $this->middleware->handle(
            $query,
            function (FetchOneQuery $passed) use ($query, $validated, $expected): Result {
                $this->assertNotSame($query, $passed);
                $this->assertSame($validated, $passed->validated());
                return $expected;
            },
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testItFailsValidation(): void
    {
        $query = FetchOneQuery::make(
            $request = $this->createMock(Request::class),
            $input = new QueryOne($this->type, new ResourceId('123'), ['foo' => 'bar']),
        );

        $queryValidator = $this->willValidate($request);

        $queryValidator
            ->expects($this->once())
            ->method('make')
            ->with($this->identicalTo($input))
            ->willReturn($validator = $this->createMock(Validator::class));

        $validator
            ->expects($this->once())
            ->method('fails')
            ->willReturn(true);

        $this->errorFactory
            ->expects($this->once())
            ->method('make')
            ->with($this->identicalTo($validator))
            ->willReturn($errors = new ErrorList());

        $actual = $this->middleware->handle(
            $query,
            fn() => $this->fail('Not expecting next middleware to be called.'),
        );

        $this->assertTrue($actual->didFail());
        $this->assertSame($errors, $actual->errors());
    }

    /**
     * @return void
     */
    public function testItSetsValidatedDataIfNotValidating(): void
    {
        $request = $this->createMock(Request::class);

        $input = new QueryOne($this->type, new ResourceId('123'), $params = ['foo' => 'bar']);
        $query = FetchOneQuery::make($request, $input)
            ->skipValidation();

        $this->validators
            ->expects($this->never())
            ->method($this->anything());

        $expected = Result::ok(new Payload(null, true));

        $actual = $this->middleware->handle(
            $query,
            function (FetchOneQuery $passed) use ($query, $params, $expected): Result {
                $this->assertNotSame($query, $passed);
                $this->assertSame($params, $passed->validated());
                return $expected;
            },
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @return void
     */
    public function testItDoesNotValidateIfAlreadyValidated(): void
    {
        $request = $this->createMock(Request::class);

        $input = new QueryOne($this->type, new ResourceId('123'), ['blah' => 'blah']);
        $query = FetchOneQuery::make($request, $input)
            ->withValidated($validated = ['foo' => 'bar']);

        $this->validators
            ->expects($this->never())
            ->method($this->anything());

        $expected = Result::ok(new Payload(null, false));

        $actual = $this->middleware->handle(
            $query,
            function (FetchOneQuery $passed) use ($query, $validated, $expected): Result {
                $this->assertSame($query, $passed);
                $this->assertSame($validated, $passed->validated());
                return $expected;
            },
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @param Request|null $request
     * @return QueryOneValidator&MockObject
     */
    private function willValidate(?Request $request): QueryOneValidator&MockObject
    {
        $this->validators
            ->method('validatorsFor')
            ->with($this->identicalTo($this->type))
            ->willReturn($factory = $this->createMock(Factory::class));

        $factory
            ->expects($this->once())
            ->method('withRequest')
            ->with($this->identicalTo($request))
            ->willReturnSelf();

        $factory
            ->method('queryOne')
            ->willReturn($validator = $this->createMock(QueryOneValidator::class));

        return $validator;
    }
}
