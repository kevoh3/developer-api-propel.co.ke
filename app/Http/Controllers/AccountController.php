<?php
namespace App\Http\Controllers;

use App\Services\DeveloperAuthService;
use App\Services\TransactionTransformer;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;

class AccountController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }
/**
* Get account balance
*/
public function getBalance(Request $request)
{
    $data = $request->get('data');
    $accountNumber = $request->input('AccountNumber');
    if (!DeveloperAuthService::validateAccountNumber($data, $accountNumber)) {
        return response()->json([
            'status' => false,
            'detail' => 'Invalid Account Number',
            'balance'=>"",
        ], 403);
    }
    $walletId=DeveloperAuthService::validateAccountNumber($data, $accountNumber);
    if (!$walletId) {
        return response()->json([
            'status' => false,
            'detail' => 'Invalid Account Number',
            'balance' => "",
        ], 403);
    }
   // $balance = WalletService::getWalletBalance($accountNumber);
    $balance=$this->walletService->getWalletBalance($accountNumber);


    if ($balance === null) {
        return response()->json([
            'status' => false,
            'detail' => 'Wallet not found'
        ], 404);
    }
return response()->json([
'status' => true,
'balance' => $balance,
]);
}

/**
* Get account transactions with optional date range filtering
*/
//public function getTransactions(Request $request)
//{
//    $data = $request->get('data');
//    $accountNumber = $request->input('AccountNumber');
//    if (!DeveloperAuthService::validateAccountNumber($data, $accountNumber)) {
//        return response()->json([
//            'status' => false,
//            'detail' => 'Invalid Account Number',
//            'balance'=>"",
//        ], 403);
//    }
//
//$query = Transaction::query();
//
//// Apply date range filter if provided
//if ($request->filled('start_date') && $request->filled('end_date')) {
//$query->whereBetween('created_at', [$request->start_date, $request->end_date]);
//}
//
//// Fetch transactions
//$transactions = $query->get();
//
//return response()->json([
//'status' => true,
//'transactions' => $transactions
//]);
//}
    public function getTransactions(Request $request)
    {
        $data = $request->get('data'); // Retrieved from middleware
        $accountNumber = $request->input('AccountNumber');
//        if (!DeveloperAuthService::validateAccountNumber($data, $accountNumber)) {
//            return response()->json([
//                'status' => false,
//                'detail' => 'Invalid Account Number',
//                'transactions' => [],
//            ], 403);
//        }
        $walletId=DeveloperAuthService::validateAccountNumber($data, $accountNumber);
        if (!$walletId) {
            return response()->json([
                'status' => false,
                'detail' => 'Invalid Account Number',
                'balance' => "",
            ], 403);
        }

        // Start building the query
        $query = Transaction::where('wallet_id', $walletId);

        // Filter by transaction ID
        if ($request->filled('transaction_id')) {
            $query->where('transaction_id', $request->input('transaction_id'));
        }

        // Filter by specific date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->input('start_date'), $request->input('end_date')]);
        }

        // Additional filters (if needed)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type')); // e.g., "credit" or "debit"
        }

        // Paginate the results (default: 10 per page)
        $transactions = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 10))->appends($request->query());
        $transformedData = $transactions->through(fn ($transaction) => TransactionTransformer::transform($transaction));
        return response()->json([
            'status' => true,
            'transactions' => $transformedData,
        ]);
    }
}
