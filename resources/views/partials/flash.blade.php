@if(session('success'))
    <div class="alertx alert-successx" role="status"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alertx alert-errorx" role="alert">
        <i class="bi bi-exclamation-circle"></i>
        <div>
            <strong>{{ __('common.validation_summary') }}</strong>
            <ul class="alert-list">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
