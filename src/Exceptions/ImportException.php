<?php

namespace WonderWp\Component\ImportFoundation\Exceptions;

use Exception;

class ImportException extends Exception implements \JsonSerializable
{

    public function __toString(): string
    {
        return json_encode($this);
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this), true);
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
