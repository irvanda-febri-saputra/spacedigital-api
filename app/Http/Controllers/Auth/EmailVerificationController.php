<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\SendVerificationOtp;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailVerificationController extends Controller
{
    /**
     * Show OTP input page
     */
    public function show(Request $request)
    {
        $email = $request->session()->get('verification_email');
        
        if (!$email) {
            return redirect('/login');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $email,
        ]);
    }

    /**
     * Send/Resend OTP code
     */
    public function sendCode(Request $request)
    {
        $email = $request->session()->get('verification_email') ?? $request->user()?->email;
        
        if (!$email) {
            return back()->withErrors(['email' => 'No email found']);
        }

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return back()->withErrors(['email' => 'User not found']);
        }

        // Generate and send OTP
        $otpRecord = EmailVerificationCode::generateFor($user);
        $user->notify(new SendVerificationOtp($otpRecord->code));

        return back()->with('message', 'Verification code sent to your email!');
    }

    /**
     * Verify OTP code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = $request->session()->get('verification_email');
        
        if (!$email) {
            return redirect('/login')->withErrors(['email' => 'Session expired. Please login again.']);
        }

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return back()->withErrors(['code' => 'User not found']);
        }

        if (EmailVerificationCode::verify($user, $request->code)) {
            // Clear session
            $request->session()->forget('verification_email');
            
            return redirect('/login')->with('success', 'Email verified successfully! You can now login.');
        }

        return back()->withErrors(['code' => 'Invalid or expired code. Please try again.']);
    }
}
