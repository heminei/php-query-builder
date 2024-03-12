<?php

namespace HemiFrame\Lib\SQLBuilder\Cache;

class ResultData
{
    private mixed $data;

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return $this
     */
    public function setData(mixed $data): self
    {
        $this->data = $data;

        return $this;
    }
}
