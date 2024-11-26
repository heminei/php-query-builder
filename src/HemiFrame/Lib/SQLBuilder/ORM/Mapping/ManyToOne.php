<?php

declare(strict_types=1);

namespace HemiFrame\Lib\SQLBuilder\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ManyToOne implements MappingAttribute
{
    /**
     * @param class-string|null $targetEntity
     */
    public function __construct(
        public ?string $targetEntity = null,
    ) {
    }
}
