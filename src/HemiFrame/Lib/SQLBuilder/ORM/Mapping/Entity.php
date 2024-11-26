<?php

declare(strict_types=1);

namespace HemiFrame\Lib\SQLBuilder\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Entity implements MappingAttribute
{
    public function __construct(
        public ?string $table = null,
    ) {
    }
}
