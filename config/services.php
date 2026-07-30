<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (push notifications)
    |--------------------------------------------------------------------------
    |
    | Values come from the Firebase service account JSON:
    | Firebase Console > Project Settings > Service Accounts > Generate new
    | private key. Copy project_id / client_email / private_key into .env.
    | FCM_PRIVATE_KEY must keep its "\n" sequences (wrap the value in quotes).
    |
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'client_email' => env('FCM_CLIENT_EMAIL'),
        'private_key' => env('FCM_PRIVATE_KEY'),
    ],

];
