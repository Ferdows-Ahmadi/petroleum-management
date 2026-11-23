<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function create(Request $request)
    {
        return view('users.form', ['managedUser' => new User(['is_active' => true, 'role' => UserRole::PurchaseSalesOfficer]), 'roles' => $this->rolesFor($request)]);
    }

    public function edit(Request $request, User $user)
    {
        $this->ensureManageable($request, $user);

        return view('users.form', ['managedUser' => $user, 'roles' => $this->rolesFor($request)]);
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('office.configuration', ['tab' => 'users'])->with('success', 'کاربر ثبت شد.');
    }

    public function update(Request $request, User $user)
    {
        $this->ensureManageable($request, $user);
        $data = $this->validated($request, $user);
        DB::transaction(function () use ($request, $user, $data): void {
            // Serialize changes to privileged users so two administrators cannot remove the last access path.
            $privileged = User::query()->whereIn('role', [UserRole::GeneralManager->value, UserRole::SystemAdmin->value])->orderBy('id')->lockForUpdate()->get();
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->ensureManageable($request, $locked);
            if ($locked->id === $request->user()->id && (! $data['is_active'] || $data['role'] !== $locked->role->value)) {
                throw ValidationException::withMessages(['role' => 'نقش یا فعالیت حساب جاری خود را تغییر داده نمی‌توانید.']);
            }
            $retainsPrivilege = $data['is_active'] && in_array($data['role'], [UserRole::GeneralManager->value, UserRole::SystemAdmin->value], true);
            if (! $retainsPrivilege && $privileged->where('is_active', true)->where('id', '!=', $locked->id)->isEmpty()) {
                throw ValidationException::withMessages(['is_active' => 'حداقل یک مدیر فعال باید باقی بماند.']);
            }
            if (isset($data['password']) || ! $data['is_active'] || $data['role'] !== $locked->role->value) {
                $data['remember_token'] = Str::random(60);
                DB::table('sessions')->where('user_id', $locked->id)->delete();
            }
            $locked->forceFill($data)->save();
        });

        return redirect()->route('office.configuration', ['tab' => 'users'])->with('success', 'معلومات کاربر ذخیره شد.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_map(fn ($role) => $role->value, $this->rolesFor($request)))],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    private function rolesFor(Request $request): array
    {
        return $request->user()->role === UserRole::GeneralManager ? UserRole::cases()
            : [UserRole::SystemAdmin, UserRole::PurchaseSalesOfficer, UserRole::WarehouseOfficer, UserRole::TransportOfficer];
    }

    private function ensureManageable(Request $request, User $user): void
    {
        abort_unless(in_array($user->role, $this->rolesFor($request), true), 403);
    }
}
