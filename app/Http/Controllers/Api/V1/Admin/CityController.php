<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Models\CityAlias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(): JsonResponse
    {
        $cities = City::with('aliases')
            ->withCount(['orders'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => CityResource::collection($cities)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|unique:cities,name',
            'province' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $city = City::create($data);

        return response()->json(['data' => new CityResource($city)], 201);
    }

    public function update(Request $request, City $city): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|unique:cities,name,'.$city->id,
            'province' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $city->update($data);

        return response()->json(['data' => new CityResource($city->load('aliases'))]);
    }

    public function storeAlias(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city_id' => 'required|exists:cities,id',
            'alias_name' => 'required|string|unique:city_aliases,alias_name',
        ]);

        $alias = CityAlias::create($data);

        return response()->json(['data' => $alias], 201);
    }

    public function aliases(): JsonResponse
    {
        $aliases = CityAlias::with('city')->get()->map(fn ($a) => [
            'id' => $a->id,
            'typo' => $a->alias_name,
            'maps' => $a->city->name,
            'city_id' => $a->city_id,
        ]);

        return response()->json(['data' => $aliases]);
    }
}
