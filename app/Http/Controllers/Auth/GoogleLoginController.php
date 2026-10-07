<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Google\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GoogleLoginController extends Controller
{
    public function create(Request $request): View
    {
        if (app()->environment('local') && config('dorm.temporary_login')) {
            return view('auth.temporary-login', [
                'members' => User::whereIn('email', array_column(config('dorm.temporary_members'), 'email'))
                    ->where('is_active', true)->orderBy('student_id')->get(),
            ]);
        }

        $nonce = Str::random(64);
        $request->session()->put('google_login_nonce', $nonce);
        $request->session()->put('google_login_expires_at', now()->addMinutes(10)->timestamp);

        return view('auth.login', [
            'nonce' => $nonce,
            'clientId' => (string) config('services.google.client_id'),
        ]);
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        if (! config('services.google.client_id')) {
            return $this->error('ระบบยังไม่พร้อมให้เข้าสู่ระบบ กรุณาติดต่อผู้ดูแล', 503);
        }
        $validator = Validator::make($request->only('credential'), [
            'credential' => ['required', 'string', 'max:8192'],
        ]);
        if ($validator->fails()) {
            return $this->error('ไม่พบข้อมูลยืนยันตัวตนจาก Google กรุณาลองใหม่', 422);
        }

        $nonce = $request->session()->pull('google_login_nonce');
        $expiresAt = $request->session()->pull('google_login_expires_at');
        if (! is_string($nonce) || ! is_numeric($expiresAt) || now()->timestamp >= (int) $expiresAt) {
            return $this->error('คำขอเข้าสู่ระบบหมดอายุ กรุณาโหลดหน้าใหม่', 419);
        }

        try {
            $payload = $client->verifyIdToken($request->input('credential'));
        } catch (ConnectException) {
            return $this->error('ไม่สามารถติดต่อ Google ได้ กรุณาลองใหม่', 503);
        } catch (Throwable) {
            return $this->error('ไม่สามารถยืนยันตัวตนกับ Google ได้ กรุณาลองใหม่', 422);
        }

        if (! is_array($payload)
            || ($payload['aud'] ?? null) !== config('services.google.client_id')
            || ! in_array($payload['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! is_numeric($payload['exp'] ?? null)
            || (int) $payload['exp'] <= now()->timestamp
            || ! is_string($payload['nonce'] ?? null)
            || ! hash_equals($nonce, $payload['nonce'])
            || ($payload['email_verified'] ?? false) !== true
            || ! is_string($payload['sub'] ?? null)
            || $payload['sub'] === ''
            || strlen($payload['sub']) > 255
            || ! is_string($payload['email'] ?? null)
            || ! filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('ไม่สามารถยืนยันตัวตนกับ Google ได้ กรุณาลองใหม่', 422);
        }

        $email = strtolower($payload['email']);
        $domain = substr(strrchr($email, '@'), 1);
        $allowedDomains = ['kkumail.com', 'kku.ac.th'];
        if (! in_array($domain, $allowedDomains, true)
            || ! in_array($payload['hd'] ?? null, $allowedDomains, true)) {
            return $this->error('กรุณาเข้าสู่ระบบด้วยบัญชี KKU เท่านั้น', 403);
        }

        try {
            $user = DB::transaction(function () use ($email, $payload): ?User {
                $user = User::where('email', $email)->lockForUpdate()->first();
                if (! $user || ! $user->is_active
                    || ($user->google_id !== null && $user->google_id !== $payload['sub'])
                    || User::where('google_id', $payload['sub'])->where('id', '!=', $user->id)->exists()) {
                    return null;
                }
                if ($user->google_id === null) {
                    $user->google_id = $payload['sub'];
                }
                $user->email_verified_at ??= now();
                $user->save();

                return $user;
            });
        } catch (QueryException) {
            return $this->error('ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่หรือติดต่อผู้ดูแล', 409);
        }
        if (! $user) {
            return $this->error('บัญชีนี้ยังไม่ได้รับอนุญาตให้ใช้งาน กรุณาติดต่อผู้ดูแล', 403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('dashboard')]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'ออกจากระบบเรียบร้อยแล้ว');
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
