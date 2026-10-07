<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QrController extends Controller
{
    public function show(): View
    {
        return view('member.qr');
    }

    public function image(Request $request): Response
    {
        $token = DB::transaction(function () use ($request): string {
            $member = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($member->is_active, 403);
            if (! $member->qr_token) {
                $member->qr_token = (string) Str::uuid();
                $member->save();
            }

            return $member->qr_token;
        }, 3);
        $result = (new SvgWriter)->write(new QrCode(data: $token, size: 300, margin: 16));

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(), 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
