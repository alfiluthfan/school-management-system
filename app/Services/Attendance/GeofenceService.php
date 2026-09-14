<?php

namespace App\Services\Attendance;

use App\Models\Attendance\SchoolLocation;

final class GeofenceService
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function distanceMeters(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB
    ): float {
        $lat1 = deg2rad($latitudeA);
        $lat2 = deg2rad($latitudeB);
        $deltaLat = deg2rad($latitudeB - $latitudeA);
        $deltaLon = deg2rad($longitudeB - $longitudeA);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1)
            * cos($lat2)
            * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    public function distanceFromLocation(
        SchoolLocation $location,
        float $latitude,
        float $longitude
    ): float {
        return $this->distanceMeters(
            (float) $location->latitude,
            (float) $location->longitude,
            $latitude,
            $longitude
        );
    }
}
