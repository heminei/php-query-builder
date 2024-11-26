<?php

declare(strict_types=1);

namespace HemiFrame\Lib\SQLBuilder\ORM;

class EntityHydrator
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private string $entityClass,
        private array $row,
    ) {
    }

    public function hydrate(): void
    {
        if (!class_exists($this->entityClass)) {
            throw new \InvalidArgumentException("Hydration class ($this->entityClass) not found");
        }
        $reflectionClass = new \ReflectionClass($this->entityClass);

        // $entity = new $this->entityClass();
    }
}
