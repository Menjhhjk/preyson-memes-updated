<?php

namespace App\Http\Controllers;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\AccountSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    use ProfileValidationRules;

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['member', 'moderator', 'admin'])],
        ]);
        $accounts = User::query()->withCount('posts')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['role'] ?? null, function ($query, $role) {
                if ($role === 'admin') {
                    $query->where(fn ($query) => $query->where('role', 'admin')->orWhere('is_admin', true));
                } else {
                    $query->where('role', $role)->where('is_admin', false);
                }
            })
            ->orderBy('id')->paginate(15)->withQueryString();

        return view('accounts.index', compact('accounts', 'filters'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('accounts.form', ['account' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validateAccount($request);
        $data['is_admin'] = $data['role'] === 'admin';
        $account = new User;
        $account->forceFill($data)->save();

        return to_route('accounts.index')->with('success', 'Account created. They can sign in immediately; no email delivery is required.');
    }

    public function edit(Request $request, User $account): View
    {
        $this->authorizeAdmin($request);
        $account->loadCount('posts');

        return view('accounts.form', compact('account'));
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validateAccount($request, $account);

        DB::transaction(function () use ($request, $account, $data) {
            $admins = User::query()->where(fn ($query) => $query->where('role', 'admin')->orWhere('is_admin', true))
                ->orderBy('id')->lockForUpdate()->get();
            $account = User::query()->lockForUpdate()->findOrFail($account->id);
            $isAdmin = $account->is_admin || $account->role === 'admin';
            if ($data['role'] !== 'admin' && $isAdmin && ($account->is($request->user()) || $admins->count() <= 1)) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own administrator role or the last administrator.']);
            }
            if (Str::lower($account->email) === 'admin@gmail.com' && ($data['role'] !== 'admin' || $data['email'] !== 'admin@gmail.com')) {
                throw ValidationException::withMessages(['email' => 'The main administrator must keep admin@gmail.com and the administrator role.']);
            }

            $oldEmail = $account->email;
            $revoke = isset($data['password']) || $account->role !== $data['role'] || $oldEmail !== $data['email'];
            $account->forceFill([...$data, 'is_admin' => $data['role'] === 'admin'])->save();
            if ($revoke) {
                AccountSessions::clearPasswordResets($oldEmail);
                AccountSessions::revoke($account, $account->is($request->user()) ? $request->session()->getId() : null);
            }
        });

        return to_route('accounts.index')->with('success', 'Account updated. Changed credentials or roles take effect on the next request.');
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $paths = DB::transaction(function () use ($request, $account) {
            $admins = User::query()->where(fn ($query) => $query->where('role', 'admin')->orWhere('is_admin', true))
                ->orderBy('id')->lockForUpdate()->get();
            $account = User::query()->lockForUpdate()->findOrFail($account->id);
            if ($account->is($request->user()) || Str::lower($account->email) === 'admin@gmail.com'
                || (($account->is_admin || $account->role === 'admin') && $admins->count() <= 1)) {
                throw ValidationException::withMessages(['account' => 'You cannot delete yourself, the main administrator, or the last administrator.']);
            }

            $paths = $account->posts()->pluck('media_path')->all();
            if ($account->avatar_path) {
                $paths[] = $account->avatar_path;
            }
            AccountSessions::revoke($account);
            $account->posts()->delete();
            $account->delete();

            return $paths;
        });
        Storage::disk('public')->delete($paths);

        return to_route('accounts.index')->with('success', 'Account and its posts have been deleted.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user() && ($request->user()->is_admin || $request->user()->role === 'admin'), 403);
    }

    /** @return array<string, mixed> */
    private function validateAccount(Request $request, ?User $account = null): array
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'username' => Str::lower(trim((string) $request->input('username'))),
        ]);
        $data = $request->validate([
            'username' => $this->usernameRules($account?->id, $account && $request->input('username') === $account->username ? min(6, strlen($account->username)) : 6),
            'email' => $this->emailRules($account?->id),
            'role' => ['required', Rule::in(['member', 'moderator', 'admin'])],
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
