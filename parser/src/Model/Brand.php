<?php

declare(strict_types=1);

namespace WB\Parser\Model;

class Brand
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public readonly \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->createdAt->format('Y-m-d'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'], 
            name: (string) $data['name']
        );
    }
}
