<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    private function authorizeInviter(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->role?->name === 'SuperAdmin' ||
            ($user->role?->name === 'Admin' && $user->company()->exists()), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeInviter($request);

        return view('invitations.index');
    }

    public function store(Request $request)
    {
        $this->authorizeInviter($request);
        $super = $request->user()->role->name === 'SuperAdmin';
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($super ? ['Admin'] : ['Admin', 'Member'])],
            'company_name' => $super ? ['required', 'string', 'max:255', 'unique:companies,name'] : ['prohibited'],
            'company_id' => ['prohibited'],
        ]);
        $token = Str::random(64);
        DB::transaction(function () use ($request, $super, $data, $token) {
            $companyId = $super ? Company::create(['name' => $data['company_name']])->id : $request->user()->company_id;
            Invitation::create([
                'email' => $data['email'], 'role' => $data['role'], 'company_id' => $companyId,
                'invited_by' => $request->user()->id, 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);
        });

        return redirect()->route('invitations.index')->with('invitation_url', route('invitations.accept', $token));
    }

    private function pending(string $token): Invitation
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($invitation->accepted_at || $invitation->expires_at->isPast(), 410, 'This invitation has expired or has already been used.');

        return $invitation;
    }

    public function show(string $token)
    {
        return view('invitations.accept', ['invitation' => $this->pending($token), 'token' => $token]);
    }

    public function accept(Request $request, string $token)
    {
        $this->pending($token);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        try {
            DB::transaction(function () use ($data, $token) {
                $invitation = $this->pending($token);
                $claimed = Invitation::whereKey($invitation->id)->whereNull('accepted_at')
                    ->where('expires_at', '>', now())->update(['accepted_at' => now()]);
                abort_unless($claimed === 1, 410);
                if (User::where('email', $invitation->email)->exists()) {
                    throw ValidationException::withMessages(['email' => 'An account already exists for this invitation.']);
                }
                $user = new User($data);
                $user->email = $invitation->email;
                $user->role_id = Role::where('name', $invitation->role)->sole()->id;
                $user->company_id = $invitation->company_id;
                $user->save();
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email.']);
        }

        return redirect()->route('login')->with('success', 'Your account is ready. Sign in with your new password.');
    }
}
