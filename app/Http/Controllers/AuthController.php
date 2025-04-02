<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeveloperApiCredential;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function getToken(Request $request)
    {
        $request->validate([
            'client_id' => 'required',
            'client_secret' => 'required',
        ]);

        $developer = DeveloperApiCredential::where('client_id', $request->client_id)
            ->where('client_secret', $request->client_secret)
            ->where('status', 1)
            ->first();

        if (!$developer) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Generate access token
        $token = $developer->createToken('Developer API Token')->accessToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => now()->addDays(15)->timestamp,
        ]);
    }
}

