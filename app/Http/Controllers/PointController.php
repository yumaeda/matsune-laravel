<?php
namespace App\Http\Controllers;

use App\User;
use App\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 * Controller for point management
 *
 */
class PointController extends Controller
{
    /*
     * Show point index page
     *
     * @access public
     * @return void
     *
     */
    public function index()
    {
        $users = User::select('id', 'first_name', 'last_name')
            ->where('id', '!=', Auth::id())
            ->get();

        return view('points/index', [ 'users' => $users ]);
    }

    /*
     * Donate point from the current user to the specified user
     *
     * @access public
     * @param Request $request
     * @return void
     *
     */
    public function donate(Request $request)
    {
        $donner = auth()->user();
        $point = $request->input('point');
        $comment = $request->input('comment');
        $user_id = $request->input('user_id');

        DB::beginTransaction();
        $recipient = User::whereId($user_id)->first();
        $recipient_name = ($recipient->first_name . ' ' . $recipient->last_name);
        $transactioins = [];

        try {
            $transaction = new PointTransaction;
            $transaction->donner_id    = $donner->id;
            $transaction->recipient_id = $user_id;
            $transaction->amount       = $point;
            $transaction->comment      = $comment;
            $transaction->completed    = 1;
            $transaction->save();

            $donner->point = ($donner->point - $point);
            $donner->save();

            $recipient->point = ($recipient->point + $point);
            $recipient->save();

            $this->sendDonationMail($donner, $recipient, $point);

            $transactions[] = [
                'point' => $point,
                'user_name' => $recipient_name,
            ];

            DB::commit();
        } catch (Exception $ex) {
            DB::rollBack();
        }

        return redirect()->back()->with('transactions', $transactions);
    }

    /*
     * Send payment mail to the specified user
     *
     * @access private
     * @param \App\User $donner
     * @param \App\User $recipient
     * @param int $point
     * @return int
    */
    private function sendDonationMail(User $donner, User $recipient, int $point)
    {
        Mail::to($recipient->email)
            ->send(new \App\Mail\Donation($donner, $recipient, $point));

        Mail::to($donner->email)
            ->send(new \App\Mail\Donation($donner, $recipient, $point));
    }
}

