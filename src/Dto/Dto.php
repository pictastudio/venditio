<?php

namespace PictaStudio\Venditio\Dto;

use Illuminate\Contracts\Support\{Arrayable, Jsonable};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Str};
use JsonSerializable;
use PictaStudio\Venditio\Dto\Contracts\Dto as DtoContract;
use ReflectionClass;

class Dto implements Arrayable, DtoContract, Jsonable, JsonSerializable
{
    public static function fromArray(array $data): static
    {
        /** @var static $dto */
        $dto = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        return $dto->fill($data);
    }

    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            $key = Str::camel($key);

            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        $data = [];

        foreach ($this as $key => $value) {
            if ($value instanceof Model) {
                continue;
            }

            $key = Str::snake($key);

            $data[$key] = $value;
        }

        return $data;
    }

    public function toCollection(): Collection
    {
        return collect($this->toArray());
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
