<?php

namespace App\Http\Controllers;

use App\Mail\TransactionSuccess;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransController extends Controller
{
    public function notificationHandler(Request $request)
    {
        // set konfigurasi midtrans
        Config::$serverKey = config('midtrans.serverkey');
        Config::$isProduction = config('midtrans.isProduction');
        Config::$isSanitized = config('midtrans.isSanitized');
        Config::$is3ds = config('midtrans.is3ds');

        // Buat instance midtrans notifications
        notification = new Notification();

        //pecah order ID agar bisa disimpan database
        $order = explode('-', $notification->order_id);

        //assign ke variabel untuk memudahkan coding
        $status = $notification->transaction_status;
        $type = $notification->payment_type;
        $fraud = $notification->fraud_status;
        $order_id = $order[1]; 

        //Cari transaksi berdasar ID
        $transaction = Transaction::findorFail($order_id);

        //Handle notification status midtrans
        if($status == 'capture'){
            if($type == 'credit_card'){
                if($fraud == 'challenge'){
                    $transaction->transaction_status = 'CHALLENGE';
                } else {
                    $transaction->transaction_status = 'SUCCESS'
                }
            }
        }
        else if($status == 'settlement'){
            $transaction->transaction_status = 'SUCCESS';
        }
        else if($status == 'pending'){
            $transaction->transaction_status = 'PENDING';
        }
        else if($status == 'deny'){
            $transaction->transaction_status = 'FAILED';
        }
        else if($status == 'expire'){
            $transaction->transaction_status = 'EXPIRED';
        }
        else if($status == 'cancel'){
            $transaction->transaction_status = 'FAILED';
        }

        //Simpan transaksi
        $transaction->save();

        //kirim email
        if($transaction)
        {
            if($status == 'capture' && $fraud == 'accept')
            {
                Mail::to($transaction->user)->send(
                    new TransactionSuccess($transaction)
                );
            }
            else if($status == 'settlement')
            {
                Mail::to($transaction->user)->send(
                    new TransactionSuccess($transaction)
                );
            }
            else if($status == 'success')
            {
                Mail::to($transaction->user)->send(
                    new TransactionSuccess($transaction)
                );
            }
            else if($status == 'capture' && $fraud == 'challenge')
            {
                return response()->json([
                    'meta' => [
                        'code' => 200,
                        'message' => 'Midtrans Payment Challenge'
                    ]
                ]);
            }
            else
            {
                return response()->json([
                    'meta' => [
                        'code' => 200,
                        'message' => 'Midtrans Payment not settlement'
                    ]
                ]);
            }

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'message' => 'Midtrans notification success'
                ]
            ]);
        }
    }


    public function finishRedirect(Request $request)
    {
        return view('pages.success');
    }

    public function unfinishRedirect(Request $request)
    {
        return view('pages.unfinish');
    }

    public function errorRedirect(Request $request)
    {
        return view('pages.failed');
    }
}
