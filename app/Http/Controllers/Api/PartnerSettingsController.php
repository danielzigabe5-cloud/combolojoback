<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PartnerSettingsController extends Controller
{
    // 1. ፕሮፋይል ማዘመን
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'fullName' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'businessName' => 'nullable|string|max:255',
        ]);

        $user->update([
            'name' => $request->fullName,
            'phone' => $request->phone,
            'business_name' => $request->businessName,
        ]);

        return response()->json(['message' => 'የፕሮፋይል መረጃዎ በስኬት ተዘምኗል!']);
    }

    // 2. የባንክ መረጃ ማዘመን
    public function updateBank(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'primaryBank' => 'required|string',
            'accountName' => 'required|string|max:255',
            'accountNumber' => 'required|string|max:50',
            'telebirrPhone' => 'nullable|string|max:20',
        ]);

        $user->update([
            'bank_name' => $request->primaryBank,
            'account_name' => $request->accountName,
            'account_number' => $request->accountNumber,
            'telebirr_phone' => $request->telebirrPhone,
        ]);

        return response()->json(['message' => 'የባንክ መረጃዎ በስኬት ተቀምጧል!']);
    }

    // 3. የይለፍ ቃል መቀየር
    public function updatePassword(Request $request)
    {
        $request->validate([
            'currentPassword' => 'required',
            'newPassword' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = Auth::user();

        if (!Hash::check($request->currentPassword, $user->password)) {
            return response()->json(['message' => 'የቆየው የይለፍ ቃል ትክክል አይደለም!'], 422);
        }

        $user->update([
            'password' => Hash::make($request->newPassword)
        ]);

        return response()->json(['message' => 'የይለፍ ቃልዎ በስኬት ተቀይሯል!']);
    }
}