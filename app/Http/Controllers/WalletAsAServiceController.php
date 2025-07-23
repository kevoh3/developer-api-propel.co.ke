<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WalletAsAServiceController extends Controller
{
    public function addBeneficiary(Request $request)
    {
        $user = $request->get('authenticated_user'); // From middleware

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'mobile_no' => 'required|string|max:20',
            'request_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => "input error",
                "detail" => $validator->errors(),
                'ResponseCode'=>'400'
            ], 422);
        }
        $validated = $validator->validated();
        //$wallet=UserWallet::AddWallet($user->id,$validated['mobile_no'],$validated['name']);
        $walletData = UserWallet::AddWallet($user->id, $validated['mobile_no'], $validated['name']);

        Log::info('wallet generated', ['wallet' => $walletData['wallet']]);
        return response()->json([
            'message' => 'Beneficiary added successfully',
            'data' => [
                'wallet_name' => $walletData['wallet']->wallet_name,
                'account_number' => $walletData['wallet']->wallet_account,
                'currency' => $walletData['wallet']->currency->code,
                'notification_number' => $walletData['wallet']->notification_number,
                'wallet_type' => $walletData['wallet']->account_type,
                'client_id' => $walletData['client_id'],
                'client_secret' => $walletData['client_secret'],
                'request_id' => $request->request_id,

            ]
        ], 201);
    }
}
