<?php

declare(strict_types=1);

namespace WB\Parser\Model;

class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $sellerId,
        public readonly int $brandId,
        public readonly int $subjectId,
        public readonly ?int $root = null,
        public readonly ?int $kindId = null,
        public readonly ?int $subjectParentId = null,
        public readonly ?int $matchId = null,
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
            'seller_id' => $this->sellerId,
            'brand_id' => $this->brandId,
            'subject_id' => $this->subjectId,
            'root' => $this->root,
            'kind_id' => $this->kindId,
            'subject_parent_id' => $this->subjectParentId,
            'match_id' => $this->matchId,
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
            name: (string) $data['name'],
            sellerId: (int) ($data['seller_id'] ?? 0),
            brandId: (int) $data['brand_id'],
            subjectId: (int) $data['subject_id'],
            root: isset($data['root']) ? (int) $data['root'] : null,
            kindId: isset($data['kind_id']) ? (int) $data['kind_id'] : null,
            subjectParentId: isset($data['subject_parent_id']) ? (int) $data['subject_parent_id'] : null,
            matchId: isset($data['match_id']) ? (int) $data['match_id'] : null
        );
    }
}
