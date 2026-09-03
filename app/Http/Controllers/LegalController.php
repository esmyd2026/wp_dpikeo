<?php

namespace App\Http\Controllers;

use App\Models\WhatsappBusinessProfile;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        $profile = WhatsappBusinessProfile::first();

        return view('legal.privacy', [
            'businessName' => $profile?->business_name ?: 'esta empresa',
            'whatsappNumber' => $profile?->phone_number,
            'contactEmail' => config('mail.from.address'),
        ]);
    }
}
