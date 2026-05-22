<?php

namespace App\Http\Controllers;

use App\Models\ExternCr;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternCrVerificationController extends Controller
{
    public function show(Request $request, ExternCr $externCr): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $purpose = (string) $request->query('purpose', '');
        if (! in_array($purpose, ['creator', 'approver'], true)) {
            abort(403, 'Parameter tidak valid.');
        }

        $externCr->load(['creator', 'division']);

        return view('pages.extern-cr-verify', [
            'cr' => $externCr,
            'purpose' => $purpose,
        ]);
    }
}
