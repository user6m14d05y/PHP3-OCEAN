<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('admin-notifications', function ($user) {
    return in_array($user->role, ['admin', 'staff']);
}, ['guards' => ['api', 'admin']]);

Broadcast::channel('pos-scanner.{sessionId}', function ($user, $sessionId) {
    return in_array($user->role, ['admin', 'seller', 'staff']); // POS staff
}, ['guards' => ['api', 'admin']]);
