<?php
namespace App\Http\Controllers;

use App\User;
use App\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    const ADMIN_USER_ID = 0;
    const CONVERSION_RATE = 1000;

    /*
     * Constructor
     *
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function admin()
    {
        $users = User::select('id', 'first_name', 'last_name')
            ->where('id', '!=', Auth::id())
            ->get();

        return view('admin.index', [ 'users' => $users ]);
    }

    /*
     * Return view for payment
     *
     * @access public
     * @return void
    */
    public function payment()
    {
        $users = User::select('id', 'first_name', 'last_name')
            ->get();

        return view('admin.payment', [ 'users' => $users ]);
    }

    /*
     * Make a deposit for the specified user
     *
     * @access public
     * @param Request $request
     * @return void
    */
    public function deposit(Request $request)
    {
        $point = $this->convertToPoint($request->input('yen'));
        $user_id = $request->input('user_id');

        DB::beginTransaction();
        $user = User::whereId($user_id)->first();
        $user_name = ($user->first_name . ' ' . $user->last_name);

        try {
            $transaction = new PointTransaction;
            $transaction->donner_id    = self::ADMIN_USER_ID;
            $transaction->recipient_id = $user_id;
            $transaction->amount       = $point;
            $transaction->completed    = 1;
            $transaction->save();

            $user->point = ($user->point + $point);
            $user->save();

            DB::commit();
        } catch (Exception $ex) {
            DB::rollBack();
        }

        return view('admin.deposit', [ 'point' => $point, 'user_name' => $user_name ]);
    }

    /*
     * Withdraw points from the specified users
     *
     * @access public
     * @param Request $request
     * @return void
    */
    public function withdraw(Request $request)
    {
        $this->validate($request, [
            'store_name' => 'required|max:500',
            'selected_users' => 'required',
            'yen' => 'required|numeric|min:1000',
        ]);

        $store_name = $request->input('store_name');
        $user_ids = $request->input('selected_users');
        $total_point = $this->convertToPoint($request->input('yen'));
        $point = ceil($total_point / count($user_ids));

        $transactioins = [];
        DB::beginTransaction();

        try {
            foreach ($user_ids as $user_id) {
                $user = User::whereId($user_id)->first();
                $user_name = ($user->first_name . ' ' . $user->last_name);

                $transaction = new PointTransaction;
                $transaction->donner_id    = $user_id;
                $transaction->recipient_id = self::ADMIN_USER_ID;
                $transaction->amount       = $point;
                $transaction->completed    = 1;
                $transaction->save();

                $user->point = ($user->point - $point);
                $user->save();
                $this->sendMail($user, $point);

                $transactions[] = [
                    'point' => $point,
                    'user_name' => $user_name,
                ];
            }

            DB::commit();
        } catch (Exception $ex) {
            DB::rollBack();
        }

        return redirect()->back()->with('transactions', $transactions);
    }

    /*
     * Convert JPY to point
     *
     * @access private
     * @param int $yen
     * @return int
    */
    private function convertToPoint(int $yen): int
    {
        return floor($yen / self::CONVERSION_RATE);
    }

    private function sendMail($user, int $point)
    {
        Mail::to($user->email)
            ->send(new App\Mail\Payment($user, $point));
    }
}
