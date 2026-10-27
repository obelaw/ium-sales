<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Data;

use InvalidArgumentException;

final readonly class SplitOrderDto
{
    /**
     * @param array<int, SplitBatchDto> $batches
     */
    public function __construct(
        public array $batches
    ) {
        if (empty($this->batches)) {
            throw new InvalidArgumentException('Split order must contain at least one fulfillment batch.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        $rawBatches = (array) ($data['batches'] ?? []);
        $batches = array_map(
            fn(mixed $b) => $b instanceof SplitBatchDto ? $b : SplitBatchDto::from((array) $b),
            $rawBatches
        );

        return new self($batches);
    }
}
