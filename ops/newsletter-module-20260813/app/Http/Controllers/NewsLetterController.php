<?php

namespace Modules\NewsLetter\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\MailSenderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\NewsLetter\app\Models\NewsLetter;

class NewsLetterController extends Controller
{
    use MailSenderTrait;

    public function newsletter_request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ], [
            'email.required' => __('Email is required'),
            'email.email' => __('Please enter a valid email address'),
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $email = strtolower(trim($request->email));
        $newsletter = NewsLetter::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($newsletter && $newsletter->status === 'verified') {
            return response()->json(['status' => false, 'message' => __('Email already exist')], 422);
        }

        $newsletter = $newsletter ?: new NewsLetter();
        $newsletter->email = $email;
        $newsletter->status = null;
        $newsletter->verify_token = Str::random(100);
        $newsletter->save();

        $this->sendNewsLetterMailFromTrait($newsletter);

        return response()->json(['status' => 'success', 'message' => __('A verification link has been send to your email, please verify it and getting our newsletter')]);
    }

    public function newsletter_verification($token)
    {
        $newsletter = NewsLetter::where('verify_token', $token)->first();
        if ($newsletter) {
            $newsletter->status = 'verified';
            $newsletter->save();

            return redirect()->route('website.home')->with([
                'message' => __('Newsletter verification successfully'),
                'alert-type' => 'success',
            ]);
        }

        return redirect()->route('website.home')->with([
            'message' => __('Newsletter verification failed for invalid token'),
            'alert-type' => 'error',
        ]);
    }

    public function newsletter_unsubscribe($token)
    {
        $newsletter = NewsLetter::where('verify_token', $token)->first();
        if ($newsletter) {
            $newsletter->status = 'unsubscribed';
            $newsletter->save();
            $newsletter->mailingLists()->detach();

            return redirect()->route('website.home')->with([
                'message' => 'Votre désabonnement a été enregistré.',
                'alert-type' => 'success',
            ]);
        }

        return redirect()->route('website.home')->with([
            'message' => 'Ce lien de désabonnement est invalide ou expiré.',
            'alert-type' => 'error',
        ]);
    }
}
