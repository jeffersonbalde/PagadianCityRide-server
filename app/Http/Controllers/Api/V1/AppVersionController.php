<?php

namespace App\Http\Controllers\Api\V1;

use App\AppPlatform;
use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppVersionController extends Controller
{
    /**
     * Whether the app on the phone is too old to be used, and where to update it.
     * The app asks before anybody signs in, so this is open to everyone.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::enum(AppPlatform::class)],
            'version' => ['required', 'string', 'regex:'.AppVersion::PATTERN],
        ]);

        $platform = AppPlatform::from($validated['platform']);

        return response()->json([
            'update_required' => AppVersion::requiresUpdate($platform, $validated['version']),
            'store_url' => $platform->storeUrl(),
        ]);
    }
}
