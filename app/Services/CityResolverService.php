<?php

namespace App\Services;

use App\Exceptions\CityNotServiceableException;
use App\Models\City;
use App\Models\CityAlias;
use Illuminate\Support\Str;

class CityResolverService
{
    public function resolve(string $rawCityName): City
    {
        $normalized = $this->normalize($rawCityName);

        if ($normalized === '') {
            throw new CityNotServiceableException('City name is required.');
        }

        $city = City::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();

        if (! $city) {
            $alias = CityAlias::query()
                ->whereRaw('LOWER(alias_name) = ?', [$normalized])
                ->with('city')
                ->first();

            $city = $alias?->city;
        }

        if (! $city) {
            throw new CityNotServiceableException("City '{$rawCityName}' could not be resolved.");
        }

        if (! $city->is_active) {
            throw new CityNotServiceableException("City '{$city->name}' is not currently serviceable.");
        }

        return $city;
    }

    public function normalize(string $value): string
    {
        $value = trim($value);
        $value = Str::ascii($value);

        return Str::lower($value);
    }
}
