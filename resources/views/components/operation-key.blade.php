@props(['preserve' => true])
<input type="hidden" name="operation_key" value="{{ ($preserve ? old('operation_key') : null) ?? (string) \Illuminate\Support\Str::uuid() }}">
