<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlertResource;
use Illuminate\Http\Request;

class AlertReadController extends Controller
{
    /**
     * Mark one alert as read. An alert of someone else is a 404.
     */
    public function store(Request $request, string $alert): AlertResource
    {
        $notification = $request->user()->notifications()->findOrFail($alert);

        $notification->markAsRead();

        return AlertResource::make($notification);
    }
}
