<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\DeveloperApiCredential;
use App\Models\ApiAccessToken;

class  AccessTokenController extends Controller
{
    public function getToken(Request $request)
    {
// Validate grant type
        if ($request->query('grant_type') !== 'client_credentials') {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid grant type',
                'message' => 'Invalid grant type',
                'ResponseCode'=>'400'
            ], 400);
        }

// Extract and validate Authorization header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return response()->json([
                'status' => false,
                'message' => 'Missing or invalid Authorization header',
                'detail' => 'Missing or invalid Authorization header',
                'ResponseCode'=>'400'
            ], 401);
        }

// Decode Basic Auth credentials
        $decoded = base64_decode(substr($authHeader, 6));
        if (!$decoded || !str_contains($decoded, ':')) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Authorization format',
                'detail' => 'Invalid Authorization format',
                'ResponseCode'=>'400'
            ], 401);
        }

        [$clientId, $clientSecret] = explode(':', $decoded, 2);

// Validate client credentials
        $client = DeveloperApiCredential::where('client_id', $clientId)->first();
        if (!$client) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid credentials',
                'message' => 'Invalid credentials',
             'ResponseCode'=>'401'
            ], 401);
        }
        if ($clientSecret !==$client->client_secret) {
            return response()->json([
                'status' => false,
                'detail' => 'Credentials mismatch',
                'message' => 'Credentials mismatch',
                'ResponseCode'=>'401'
            ], 401);
        }

// Generate new tokens
        $accessToken = Str::random(60);
        $refreshToken = Str::random(80);
        $expiresAt = Carbon::now()->addHours(1); // Token expires in 1 hour

// Save tokens to database
        ApiAccessToken::create([
            'developer_api_credential_id' => $client->id,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'status' => true,
            'detail' => 'SUCCESS',
            'message' => 'SUCCESS',
            'access_token' => $accessToken,
            'expires_in' => 3600, // 1 hour
            'token_type' => 'Bearer',
            'refresh_token' => $refreshToken,
            'scope' => 'C2B/B2B/B2C',
             'ResponseCode'=>'0'
        ]);
    }
//    public function getToken(Request $request)
//    {
//        // Get the Authorization header
//        $authHeader = $request->header('Authorization');
//
//        // Check if the header is missing
//        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
//            return response()->json([
//                'status' => false,
//                'detail' => 'Invalid Authorization format'
//            ], 401);
//        }
//
//        // Decode Base64 credentials
//        $encodedCredentials = substr($authHeader, 6);
//        $decodedCredentials = base64_decode($encodedCredentials);
//
//        if (!$decodedCredentials || !str_contains($decodedCredentials, ':')) {
//            return response()->json([
//                'status' => false,
//                'detail' => 'Invalid Authorization header'
//            ], 401);
//        }
//
//        // Split into client_id and client_secret
//        [$clientId, $clientSecret] = explode(':', $decodedCredentials, 2);
//
//        // Verify credentials from the `developer_api_credentials` table
//        $client = DeveloperApiCredential::where('client_id', $clientId)
//            ->where('client_secret', $clientSecret)
//            ->first();
//
//        if (!$client) {
//            return response()->json([
//                'status' => false,
//                'detail' => 'Invalid credentials'
//            ], 401);
//        }
//
//        // Generate an access token
//        $accessToken = Str::random(40);
//        $expiresAt = now()->addHours(1);
//
//        // Store the token in `api_access_tokens`
//        ApiAccessToken::create([
//            'developer_api_credential_id' => $client->id,
//            'access_token' => $accessToken,
//            'refresh_token' => Str::random(40),
//            'expires_at' => $expiresAt,
//        ]);
//
//        return response()->json([
//            'status' => true,
//            'detail' => 'SUCCESS',
//            'access_token' => $accessToken,
//            'expires_in' => 3600,
//            'token_type' => 'Bearer'
//        ]);
//    }
    public function refreshToken(Request $request)
    {
        $refreshToken = $request->input('refresh_token');

// Find valid refresh token
        $token = ApiAccessToken::where('refresh_token', $refreshToken)->first();

        if (!$token) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid refresh token'
            ], 401);
        }

// Generate new tokens
        $newAccessToken = Str::random(60);
        $newRefreshToken = Str::random(80);
        $expiresAt = Carbon::now()->addHours(1);

// Update token in database
        $token->update([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_at' => $expiresAt
        ]);

        return response()->json([
            'status' => true,
            'detail' => 'SUCCESS',
            'access_token' => $newAccessToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'refresh_token' => $newRefreshToken,
            'scope' => 'merchants C2B/B2B/B2C'
        ]);
    }
}
