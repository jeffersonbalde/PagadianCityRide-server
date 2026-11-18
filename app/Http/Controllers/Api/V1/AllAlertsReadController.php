<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AllAlertsReadController extends Controller
{
    /**
     * Mark every alert of the signed in person as read.
     */
    public function store(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
