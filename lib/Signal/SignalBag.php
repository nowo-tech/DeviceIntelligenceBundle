<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Signal;

/**
 * Unique map of signal name → Signal.
 *
 * @implements \IteratorAggregate<string, Signal>
 */
final readonly class SignalBag implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string, Signal> $signals
     */
    public function __construct(private array $signals = [])
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function with(Signal $signal): self
    {
        $signals = $this->signals;
        $signals[$signal->name->value] = $signal;

        return new self($signals);
    }

    /**
     * @param iterable<Signal> $signals
     */
    public function merge(iterable $signals): self
    {
        $bag = $this;
        foreach ($signals as $signal) {
            $bag = $bag->with($signal);
        }

        return $bag;
    }

    public function get(SignalName $name): ?Signal
    {
        return $this->signals[$name->value] ?? null;
    }

    public function has(SignalName $name): bool
    {
        return isset($this->signals[$name->value]);
    }

    public function without(SignalName $name): self
    {
        $signals = $this->signals;
        unset($signals[$name->value]);

        return new self($signals);
    }

    /**
     * @return array<string, Signal>
     */
    public function all(): array
    {
        return $this->signals;
    }

    public function count(): int
    {
        return \count($this->signals);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->signals);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [];
        foreach ($this->signals as $name => $signal) {
            $out[$name] = [
                'value' => $signal->value,
                'normalizedValue' => $signal->normalizedValue,
                'quality' => $signal->quality->value,
                'stability' => $signal->stability,
                'entropyCategory' => $signal->entropyCategory->value,
                'collectedAt' => $signal->collectedAt->format(\DATE_ATOM),
                'source' => $signal->source->value,
            ];
        }

        return $out;
    }
}
