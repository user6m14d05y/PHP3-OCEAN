<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->user_id === (int) $userId;
}, ['guards' => ['api']]);
Broadcast::channel('admin-notifications', function ($user) {
    return in_array($user->role, ['admin', 'staff']);
}, ['guards' => ['api', 'admin']]);

Broadcast::channel('pos-scanner.{sessionId}', function ($user, $sessionId) {
    return in_array($user->role, ['admin', 'seller', 'staff']); // POS staff
}, ['guards' => ['api', 'admin']]);
