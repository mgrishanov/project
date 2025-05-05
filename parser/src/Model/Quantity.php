<?php

declare(strict_types=1);

namespace WB\Parser\Model;

class Quantity
{
    public function __construct(
        public readonly int $productId,
        public readonly int $optionId,
        public readonly int $storeId,
        public readonly int $quantity,
        public readonly \DateTimeImmutable $date = new \DateTimeImmutable(),
        public readonly \DateTimeImmutable $time = new \DateTimeImmutable(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'option_id' => $this->optionId,
            'store_id' => $this->storeId,
            'quantity' => $this->quantity,
            'date' => $this->date->format('Y-m-d'),
            'time' => $this->time->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            optionId: (int) $data['option_id'],
            storeId: (int) $data['store_id'],
            quantity: (int) $data['quantity'],
            date: isset($data['date']) ? new \DateTimeImmutable($data['date']) : new \DateTimeImmutable(),
            time: isset($data['time']) ? new \DateTimeImmutable($data['time']) : new \DateTimeImmutable()
        );
    }
}
