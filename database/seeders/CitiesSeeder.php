<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\CityAlias;
use Illuminate\Database\Seeder;

class CitiesSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Lahore', 'province' => 'Punjab', 'is_active' => true, 'aliases' => ['LHR', 'Lhore', 'Lahor', 'لاہور', 'लाहौर']],
            ['name' => 'Karachi', 'province' => 'Sindh', 'is_active' => true, 'aliases' => ['Khi', 'Karachee', 'KHI']],
            ['name' => 'Islamabad', 'province' => 'ICT', 'is_active' => true, 'aliases' => ['ISB', 'Isloo']],
            ['name' => 'Faisalabad', 'province' => 'Punjab', 'is_active' => true, 'aliases' => ['Faisalbad', 'Lyallpur']],
            ['name' => 'Multan', 'province' => 'Punjab', 'is_active' => false, 'aliases' => ['Multaan']],
            ['name' => 'Peshawar', 'province' => 'KPK', 'is_active' => false, 'aliases' => ['Peshawer']],
        ];

        foreach ($cities as $data) {
            $aliases = $data['aliases'];
            unset($data['aliases']);

            $city = City::firstOrCreate(['name' => $data['name']], $data);

            foreach ($aliases as $alias) {
                CityAlias::firstOrCreate(
                    ['alias_name' => $alias],
                    ['city_id' => $city->id],
                );
            }
        }
    }
}
