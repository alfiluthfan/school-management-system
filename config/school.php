<?php

return [
    'attendance' => [
        'max_location_accuracy_meters' => env(
            'SCHOOL_MAX_LOCATION_ACCURACY_METERS',
            100
        ),
    ],
];
