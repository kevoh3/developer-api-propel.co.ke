<?php
namespace App\Http\Middleware;

use App\Models\DeveloperApiCredential;
use Closure;
use Illuminate\Http\Request;
use App\Models\ApiAccessToken;
use Carbon\Carbon;

class AuthenticateToken
{
public function handle(Request $request, Closure $next)
{
$authHeader = $request->header('Authorization');

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
return response()->json(['status' => false, 'message'=>'Unauthorized','detail' => 'Unauthorized', 'ResponseCode'=>'401'], 401);
}

$accessToken = substr($authHeader, 7);
$token = ApiAccessToken::where('access_token', $accessToken)->first();

if (!$token || $token->expires_at->isPast()) {
return response()->json([
    'status' => false,
    'detail' => 'Unauthorized',
    'message' => 'Invalid Token',
    'ResponseCode'=>'401'
], 401);
}


    // Retrieve full developer credentials
    $developer = DeveloperApiCredential::find($token->developer_api_credential_id);

    if (!$developer) {
        return response()->json([
            'status' => false,
            'detail' => 'Invalid Credential',
            'message' => 'Invalid Credential',
            'ResponseCode'=>'401'
        ], 401);
    }

    // Attach full developer data to request
    $request->attributes->set('data', $developer);
return $next($request);
}
}
