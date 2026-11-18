<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlertResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    /**
     * The alerts of the signed in person, newest first, 20 to a page. The
     * unread count is for the badge on the Alerts tab.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return AlertResource::collection($user->notifications()->latest()->paginate(20))
            ->additional(['unread_count' => $user->unreadNotifications()->count()]);
    }
}
