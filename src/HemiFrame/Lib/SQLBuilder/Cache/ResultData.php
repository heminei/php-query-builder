<?php

namespace HemiFrame\Lib\SQLBuilder\Cache;

class ResultData
{
    /**
     * @var mixed
     */
    private $data = null;

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }
}
