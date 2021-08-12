<?php

namespace Zenstruck\Messenger\Test;

use Symfony\Component\Messenger\Envelope;
use Zenstruck\Assert;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class EnvelopeCollection implements \IteratorAggregate, \Countable
{
    private array $envelopes;

    /**
     * @internal
     */
    public function __construct(Envelope ...$envelopes)
    {
        $this->envelopes = $envelopes;
    }

    public function assertEmpty(): self
    {
        return $this->assertCount(0);
    }

    public function assertNotEmpty(): self
    {
        Assert::that($this)->isNotEmpty('Expected some messages but found none.');

        return $this;
    }

    public function assertCount(int $count): self
    {
        Assert::that($this->envelopes)->hasCount($count, 'Expected {expected} messages but {actual} messages found.');

        return $this;
    }

    public function assertContains(string $messageClass, ?int $times = null): self
    {
        $messages = $this->messages($messageClass);

        Assert::that($messages)->isNotEmpty('Message "{message}" not found.', ['message' => $messageClass]);

        if (null !== $times) {
            Assert::that($messages)->hasCount(
                $times,
                'Expected to find "{message}" {expected} times but found {actual} times.',
                ['message' => $messageClass]
            );
        }

        return $this;
    }

    public function assertNotContains(string $messageClass): self
    {
        Assert::that($this->messages($messageClass))->isEmpty(
            'Found message "{message}" but should not.',
            ['message' => $messageClass]
        );

        return $this;
    }

    /**
     * @param string|callable|null $filter
     */
    public function first($filter = null): TestEnvelope
    {
        if (null === $filter) {
            // just the first envelope
            return $this->first(fn() => true);
        }

        if (!\is_callable($filter)) {
            // first envelope for message class
            return $this->first(fn(Envelope $e) => $filter === \get_class($e->getMessage()));
        }

        $filter = self::normalizeFilter($filter);

        foreach ($this->envelopes as $envelope) {
            if ($filter($envelope)) {
                return new TestEnvelope($envelope);
            }
        }

        throw new \RuntimeException('No envelopes found.');
    }

    /**
     * The messages extracted from envelopes.
     *
     * @param string|null $class Only messages of this class
     *
     * @return object[]
     */
    public function messages(?string $class = null): array
    {
        $messages = \array_map(static fn(Envelope $envelope) => $envelope->getMessage(), $this->envelopes);

        if (!$class) {
            return $messages;
        }

        return \array_values(\array_filter($messages, static fn(object $message) => $class === \get_class($message)));
    }

    /**
     * @return TestEnvelope[]
     */
    public function all(): array
    {
        return \iterator_to_array($this);
    }

    public function getIterator(): \Iterator
    {
        foreach ($this->envelopes as $envelope) {
            yield new TestEnvelope($envelope);
        }
    }

    public function count(): int
    {
        return \count($this->envelopes);
    }

    private static function normalizeFilter(callable $filter): callable
    {
        $function = new \ReflectionFunction(\Closure::fromCallable($filter));

        if (!$parameter = $function->getParameters()[0] ?? null) {
            return $filter;
        }

        if (!$type = $parameter->getType()) {
            return $filter;
        }

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin() || Envelope::class === $type->getName()) {
            return $filter;
        }

        // user used message class name as type-hint
        return function(Envelope $envelope) use ($filter, $type) {
            if ($type->getName() !== \get_class($envelope->getMessage())) {
                return false;
            }

            return $filter($envelope->getMessage());
        };
    }
}
