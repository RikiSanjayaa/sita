<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PhoneConfirmationRequest;
use Illuminate\Http\RedirectResponse;

class PhoneConfirmationController extends Controller
{
    /**
     * Save the phone number entered in the phone prompt and mark it as confirmed.
     */
    public function update(PhoneConfirmationRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->phone_number = $request->validated('phone_number');
        $user->phone_number_confirmed_at = now();
        $user->save();

        return back();
    }
}
