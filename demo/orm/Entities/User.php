<?php

declare(strict_types=1);

namespace Demo\ORM\Entities;

use HemiFrame\Lib\SQLBuilder\ORM\Mapping\Entity;
use HemiFrame\Lib\SQLBuilder\ORM\Mapping\Id;
use HemiFrame\Lib\SQLBuilder\ORM\Mapping\ManyToOne;

#[Entity(table: 'users')]
class User
{
    #[Id]
    private int $id;
    private string $name;

    private int $addressId;

    #[ManyToOne(targetEntity: Address::class)]
    private Address $address;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the value of addressId.
     */
    public function getAddressId()
    {
        return $this->addressId;
    }

    /**
     * Set the value of addressId.
     *
     * @return self
     */
    public function setAddressId($addressId)
    {
        $this->addressId = $addressId;

        return $this;
    }

    /**
     * Get the value of address.
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set the value of address.
     *
     * @return self
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }
}
