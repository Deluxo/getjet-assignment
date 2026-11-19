<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Validation;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use App\Shared\Validation\ValidationRule;
use App\Shared\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testPassesTheSubjectThroughWhenEveryRuleSucceeds(): void
    {
        $subject = new \stdClass();

        $result = Validator::loadRules(
            $this->rule(static fn (mixed $subject): TaskEither => TaskEither::right($subject)),
            $this->rule(static fn (mixed $subject): TaskEither => TaskEither::right($subject)),
        )->validate($subject)->run();

        self::assertSame($subject, self::right($result));
    }

    public function testStopsWithTheRuleErrorsWhenARuleJams(): void
    {
        $error = Error::forField('date', 'is a public holiday.');

        $result = Validator::loadRules(
            $this->rule(static fn (mixed $subject): TaskEither => TaskEither::left($error)),
        )->validate(new \stdClass())->run();

        self::assertSame([$error], self::left($result));
    }

    public function testReportsOnlyTheFirstJam(): void
    {
        $first = Error::forField('first', 'failed.');
        $second = Error::forField('second', 'failed.');

        $result = Validator::loadRules(
            $this->rule(static fn (mixed $subject): TaskEither => TaskEither::left($first)),
            $this->rule(static fn (mixed $subject): TaskEither => TaskEither::left($second)),
        )->validate(new \stdClass())->run();

        self::assertSame([$first], self::left($result));
    }

    public function testDoesNotRunLaterRulesOnceOneJams(): void
    {
        $spy = new SpyRule();

        Validator::loadRules(
            $this->rule(static fn (mixed $subject): TaskEither => TaskEither::left(Error::forField('first', 'failed.'))),
            $spy,
        )->validate(new \stdClass())->run();

        self::assertSame(0, $spy->calls);
    }

    public function testNothingRunsUntilTheTaskIsExecuted(): void
    {
        $spy = new SpyRule();
        $validator = Validator::loadRules($spy);

        $validator->validate(new \stdClass());
        self::assertSame(0, $spy->calls);

        $validator->validate(new \stdClass())->run();
        self::assertSame(1, $spy->calls);
    }

    public function testLaterRulesReceiveTheValueProducedByEarlierOnes(): void
    {
        $validator = Validator::loadRules(
            $this->rule(static fn (mixed $subject): TaskEither => TaskEither::right(new \ArrayObject())),
            $this->rule(static fn (mixed $subject): TaskEither => $subject instanceof \ArrayObject
                ? TaskEither::right($subject)
                : TaskEither::left(Error::forField('subject', 'is not an ArrayObject.'))),
        );

        self::assertInstanceOf(\ArrayObject::class, self::right($validator->validate(new \stdClass())->run()));
    }

    private function rule(\Closure $apply): ValidationRule
    {
        return new class($apply) implements ValidationRule {
            /**
             * @param \Closure(mixed): TaskEither<Error, mixed> $apply
             */
            public function __construct(private readonly \Closure $apply)
            {
            }

            /**
             * @return TaskEither<Error, mixed>
             */
            public function apply(mixed $subject): TaskEither
            {
                return ($this->apply)($subject);
            }
        };
    }

    /**
     * @template T
     *
     * @param Either<list<Error>, T> $either
     *
     * @return list<Error>
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $errors): mixed => $errors,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }

    /**
     * @template T
     *
     * @param Either<list<Error>, T> $either
     *
     * @return T
     */
    private static function right(Either $either): mixed
    {
        return $either->fold(
            static fn (): never => throw new \LogicException('Expected a right value.'),
            static fn (mixed $value): mixed => $value,
        );
    }
}

final class SpyRule implements ValidationRule
{
    public int $calls = 0;

    /**
     * @return TaskEither<Error, mixed>
     */
    /**
     * @return TaskEither<Error, mixed>
     */
    public function apply(mixed $subject): TaskEither
    {
        ++$this->calls;

        /** @var TaskEither<Error, mixed> $result */
        $result = TaskEither::right($subject);

        return $result;
    }
}
