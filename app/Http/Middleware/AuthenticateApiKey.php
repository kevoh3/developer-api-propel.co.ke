<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class AuthenticateApiKey
{
public function handle(Request $request, Closure $next)
{
$apiKey = $request->header('X-API-KEY');

if (!$apiKey) {
return response()->json(['message' => 'API key missing'], 401);
}

$user = User::where('api_key', $apiKey)->first();

if (!$user) {
return response()->json(['message' => 'Invalid API key'], 401);
}

// You can optionally bind the user to the request
$request->merge(['authenticated_user' => $user]);

return $next($request);
}
}
