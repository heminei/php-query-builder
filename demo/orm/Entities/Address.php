<?php

declare(strict_types=1);

namespace Demo\ORM\Entities;

use HemiFrame\Lib\SQLBuilder\ORM\Mapping\Entity;
use HemiFrame\Lib\SQLBuilder\ORM\Mapping\Id;

#[Entity(table: 'addresses')]
class Address
{
    #[Id]
    private int $id;
    private string $city;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }
}
