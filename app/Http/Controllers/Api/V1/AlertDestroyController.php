<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AlertDestroyController extends Controller
{
    /**
     * Delete one alert. An alert of someone else is a 404.
     */
    public function destroy(Request $request, string $alert): Response
    {
        $request->user()->notifications()->findOrFail($alert)->delete();

        return response()->noContent();
    }
}
