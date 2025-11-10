<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $participant = \App\Models\Participant::first();
    if($participant) {
        \Illuminate\Support\Facades\Mail::to('test@example.com')->send(new \App\Mail\ParticipantAccessMail($participant));
        echo 'Email sent successfully';
    } else {
        echo 'No participant found';
    }
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
