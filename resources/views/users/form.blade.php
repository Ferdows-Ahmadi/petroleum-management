@extends('layouts.app')
@section('title', 'مدیریت کاربر')
@section('content')
<x-page-header title="مدیریت کاربر" subtitle="صلاحیت‌ها در سمت سرور اجرا می‌شوند؛ حساب را به جای حذف، غیرفعال نمایید." />
<form class="cardx card-pad" method="POST" action="{{ $managedUser->exists ? route('users.update', $managedUser) : route('users.store') }}">
    @csrf
    @if($managedUser->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="user_name">نام</label><input class="control" id="user_name" name="name" value="{{ old('name', $managedUser->name) }}" required><x-field-error name="name" /></div>
        <div class="field"><label for="user_email">ایمیل</label><input class="control ltr-value" type="email" id="user_email" name="email" value="{{ old('email', $managedUser->email) }}" required autocomplete="off"><x-field-error name="email" /></div>
        <div class="field"><label for="user_role">نقش</label><select class="control" id="user_role" name="role">@foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('role', $managedUser->role?->value) === $role->value)>{{ $role->label() }}</option>@endforeach</select><x-field-error name="role" /></div>
        <div class="field"><label for="user_active">وضعیت</label><select class="control" id="user_active" name="is_active"><option value="1" @selected(old('is_active', $managedUser->is_active))>فعال</option><option value="0" @selected(!old('is_active', $managedUser->is_active))>غیرفعال</option></select><x-field-error name="is_active" /></div>
        <div class="field"><label for="user_password">رمز عبور {{ $managedUser->exists ? '(برای حفظ رمز فعلی، خالی بگذارید)' : '' }}</label><input class="control" type="password" id="user_password" name="password" autocomplete="new-password" minlength="12" @required(!$managedUser->exists)><div class="field-hint">حداقل ۱۲ حرف، حروف بزرگ و کوچک انگلیسی و عدد</div><x-field-error name="password" /></div>
        <div class="field"><label for="user_password_confirmation">تکرار رمز عبور</label><input class="control" type="password" id="user_password_confirmation" name="password_confirmation" autocomplete="new-password"></div>
    </div>
    <div class="form-actions"><button class="btnx btn-primaryx">{{ __('common.save') }}</button><a class="btnx btn-lightx" href="{{ route('office.configuration', ['tab'=>'users']) }}">{{ __('common.back') }}</a></div>
</form>
@endsection
