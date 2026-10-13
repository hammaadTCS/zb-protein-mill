<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

/**
 * Everything PostingService needs to post one journal (conventions §6).
 */
final readonly class PostingRequest
{
    /**
     * @param  string  $postingDate  Y-m-d
     * @param  string  $sourceType  morph alias of the business record (conventions §7)
     * @param  list<PostingLine>  $lines
     */
    public function __construct(
        public string $postingDate,
        public string $sourceType,
        public int $sourceId,
        public string $description,
        public array $lines,
    ) {}
}
