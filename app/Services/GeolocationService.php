<?php

namespace App\Services;

use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\RideRequest;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeolocationService
{
    /**
     * Usa OpenStreetMap Nominatim para geocodificación inversa
     * Obtiene país, estado y ciudad REALES de las coordenadas
     */
    public function findLocationByCoordinates(float $latitude, float $longitude): array
    {
        $cacheKey = "geo_{$latitude}_{$longitude}";

        return Cache::remember($cacheKey, 86400, function () use ($latitude, $longitude) {
            try {
                // Rate limiting: 1 request per second (Nominatim policy)
                sleep(1);

                $response = Http::withHeaders([
                    'User-Agent' => 'MarketplaceViajeServicio/1.0 (contacto@hectorvilla.dev)'
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'json',
                    'addressdetails' => 1,
                ]);

                if (!$response->successful()) {
                    Log::error("Error en Nominatim: " . $response->body());
                    return $this->fallbackToDistanceCalculation($latitude, $longitude);
                }

                $data = $response->json();

                if (!isset($data['address'])) {
                    return $this->fallbackToDistanceCalculation($latitude, $longitude);
                }

                $address = $data['address'];

                $countryName = $address['country'] ?? null;
                $stateName = $address['state']
                    ?? $address['region']
                    ?? $address['county']
                    ?? null;
                $cityName = $address['county']
                    ?? $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['municipality']
                    ?? null;

                Log::info("Nominatim encontró: {$countryName}, {$stateName}, {$cityName}");

                return $this->findInLocalDatabase($countryName, $stateName, $cityName);
            } catch (\Exception $e) {
                Log::error("Error en geocodificación: " . $e->getMessage());
                return $this->fallbackToDistanceCalculation($latitude, $longitude);
            }
        });
    }

    /**
     * Busca los IDs en la base de datos local de laravel-world
     */
    private function findInLocalDatabase(?string $countryName, ?string $stateName, ?string $cityName): array
    {
        $countryId = null;
        $stateId = null;
        $cityId = null;

        // 1. Buscar país por nombre (o nombre alternativo)
        if ($countryName) {
            $country = Country::where('name', 'LIKE', "%{$countryName}%")
                ->orWhere('name', $countryName)
                ->first();
            
            if (!$country) {
                // Intentar con iso2 si está disponible
                $country = Country::where('iso2', strtoupper($countryName))->first();
            }
            
            $countryId = $country?->id;
        }

        // 2. Buscar estado
        if ($countryId && $stateName) {
            $state = State::where('country_id', $countryId)
                ->where(function ($query) use ($stateName) {
                    $query->where('name', 'LIKE', "%{$stateName}%")
                          ->orWhere('name', $stateName);
                })
                ->first();
            
            $stateId = $state?->id;
        }

        // 3. Buscar ciudad
        if ($stateId && $cityName) {
            $city = City::where('state_id', $stateId)
                ->where(function ($query) use ($cityName) {
                    $query->where('name', 'LIKE', "%{$cityName}%")
                          ->orWhere('name', $cityName);
                })
                ->first();
            
            $cityId = $city?->id;
        }

        return [
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'country_name' => $countryName,
            'state_name' => $stateName,
            'city_name' => $cityName,
        ];
    }

    /**
     * Fallback al método anterior por distancia (menos preciso)
     */
    private function fallbackToDistanceCalculation(float $latitude, float $longitude): array
    {
        Log::warning("Usando fallback por distancia para: {$latitude}, {$longitude}");
        
        $country = $this->findNearestCountry($latitude, $longitude);
        
        if (!$country) {
            return [
                'country_id' => null,
                'state_id' => null,
                'city_id' => null,
                'country_name' => null,
                'state_name' => null,
                'city_name' => null,
            ];
        }

        $state = $this->findNearestState($latitude, $longitude, $country->id);
        
        $city = null;
        if ($state) {
            $city = $this->findNearestCity($latitude, $longitude, $state->id);
        } else {
            $city = $this->findNearestCityInCountry($latitude, $longitude, $country->id);
        }

        return [
            'country_id' => $country?->id,
            'state_id' => $state?->id,
            'city_id' => $city?->id,
            'country_name' => $country?->name,
            'state_name' => $state?->name,
            'city_name' => $city?->name,
        ];
    }

    /**
     * Asigna la ubicación geográfica a un ServiceRequest
     */
    public function assignLocationToServiceRequest(ServiceRequest $serviceRequest): void
    {
        if (!$serviceRequest->latitude || !$serviceRequest->longitude) {
            return;
        }

        // EVITAR BUCLE: Verificar si ya tiene asignada la ubicación
        if ($serviceRequest->country_id && $serviceRequest->state_id && $serviceRequest->city_id) {
            Log::info("Ubicación ya asignada para ServiceRequest: {$serviceRequest->id}");
            return;
        }

        $location = $this->findLocationByCoordinates(
            $serviceRequest->latitude,
            $serviceRequest->longitude
        );

        Log::info("Asignando ubicación: País={$location['country_name']} ({$location['country_id']}), Estado={$location['state_name']} ({$location['state_id']}), Ciudad={$location['city_name']} ({$location['city_id']})", ['service_request_id' => $serviceRequest->id]);

        // USAR updateQuietly() para no disparar el Observer
        $serviceRequest->updateQuietly([
            'country_id' => $location['country_id'],
            'state_id' => $location['state_id'],
            'city_id' => $location['city_id'],
        ]);
    }

    /**
     * Asigna ubicación a RideRequest (origen y destino)
     */
    public function assignLocationToRideRequest(RideRequest $rideRequest): void
    {
        // Procesar origen
        if ($rideRequest->origin_lat && $rideRequest->origin_lng) {
            if (!$rideRequest->origin_country_id) {
                $originLocation = $this->findLocationByCoordinates(
                    $rideRequest->origin_lat,
                    $rideRequest->origin_lng
                );

                $rideRequest->updateQuietly([
                    'origin_country_id' => $originLocation['country_id'],
                    'origin_state_id' => $originLocation['state_id'],
                    'origin_city_id' => $originLocation['city_id'],
                ]);
            }
        }

        // Procesar destino
        if ($rideRequest->destination_lat && $rideRequest->destination_lng) {
            if (!$rideRequest->destination_country_id) {
                $destinationLocation = $this->findLocationByCoordinates(
                    $rideRequest->destination_lat,
                    $rideRequest->destination_lng
                );

                $rideRequest->updateQuietly([
                    'destination_country_id' => $destinationLocation['country_id'],
                    'destination_state_id' => $destinationLocation['state_id'],
                    'destination_city_id' => $destinationLocation['city_id'],
                ]);
            }
        }
    }

    // === MÉTODOS PRIVADOS DEL FALLBACK ===

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function findNearestCountry(float $latitude, float $longitude): ?Country
    {
        $cacheKey = "nearest_country_{$latitude}_{$longitude}";
        return Cache::remember($cacheKey, 604800, function () use ($latitude, $longitude) {
            return Country::active()
                ->get()
                ->map(function ($country) use ($latitude, $longitude) {
                    $country->distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $country->latitude,
                        $country->longitude
                    );
                    return $country;
                })
                ->sortBy('distance')
                ->first();
        });
    }

    private function findNearestState(float $latitude, float $longitude, int $countryId): ?State
    {
        $cacheKey = "nearest_state_{$latitude}_{$longitude}_{$countryId}";
        return Cache::remember($cacheKey, 604800, function () use ($latitude, $longitude, $countryId) {
            return State::where('country_id', $countryId)
                ->get()
                ->map(function ($state) use ($latitude, $longitude) {
                    $state->distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $state->latitude,
                        $state->longitude
                    );
                    return $state;
                })
                ->sortBy('distance')
                ->first();
        });
    }

    private function findNearestCity(float $latitude, float $longitude, int $stateId): ?City
    {
        $cacheKey = "nearest_city_{$latitude}_{$longitude}_{$stateId}";
        return Cache::remember($cacheKey, 604800, function () use ($latitude, $longitude, $stateId) {
            return City::where('state_id', $stateId)
                ->get()
                ->map(function ($city) use ($latitude, $longitude) {
                    $city->distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $city->latitude,
                        $city->longitude
                    );
                    return $city;
                })
                ->sortBy('distance')
                ->first();
        });
    }

    private function findNearestCityInCountry(float $latitude, float $longitude, int $countryId): ?City
    {
        $cacheKey = "nearest_city_country_{$latitude}_{$longitude}_{$countryId}";
        return Cache::remember($cacheKey, 604800, function () use ($latitude, $longitude, $countryId) {
            return City::whereHas('state', function ($query) use ($countryId) {
                    $query->where('country_id', $countryId);
                })
                ->get()
                ->map(function ($city) use ($latitude, $longitude) {
                    $city->distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $city->latitude,
                        $city->longitude
                    );
                    return $city;
                })
                ->sortBy('distance')
                ->first();
        });
    }
}