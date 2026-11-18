<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AllAlertsDestroyController extends Controller
{
    /**
     * Delete every alert of the signed in person, and nobody else's.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->notifications()->delete();

        return response()->noContent();
    }
}
