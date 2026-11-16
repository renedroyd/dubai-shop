<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SocialPublicatorInstallationIdentityController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $identity = DB::table('integration_installation_identity')
            ->where('id', 1)
            ->first(['installation_id']);

        if (! $identity) {
            return response()->json(['message' => 'Installation identity is not configured.'], 503);
        }

        return response()->json([
            'contract_version' => 'v1',
            'installation_id' => $identity->installation_id,
        ]);
    }
}
