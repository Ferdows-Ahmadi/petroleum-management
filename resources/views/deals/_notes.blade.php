<section id="deal-notes" class="cardx card-pad" aria-labelledby="deal-notes-heading">
    <h2 id="deal-notes-heading" class="section-title">یادداشت‌ها</h2>
    @if(filled($deal->notes))
        <div class="info-label">یادداشت اصلی · {{ $deal->creator?->name ?: __('common.not_available') }} · <bdi>@jalaliDateTime($deal->created_at)</bdi></div>
        <p class="deal-note-text" dir="auto">{{ $deal->notes }}</p>
    @endif
    @foreach($deal->additionalNotes as $note)
        <div class="info-label">{{ $note->author->name }} · <bdi>@jalaliDateTime($note->created_at)</bdi></div>
        <p class="deal-note-text" dir="auto">{{ $note->body }}</p>
    @endforeach
    @if(blank($deal->notes) && $deal->additionalNotes->isEmpty())
        <p class="muted">یادداشتی ثبت نشده است.</p>
    @endif
    @if(auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER'))
        <details @if($errors->has('note_body')) open @endif>
            <summary class="section-link">افزودن یادداشت</summary>
            <form action="{{ route('deals.notes.store', $deal) }}" method="POST" class="form-grid compact-form">
                @csrf
                <x-operation-key />
                <div class="field span-2">
                    <label for="note_body">متن یادداشت</label>
                    <textarea id="note_body" class="control" name="note_body" rows="2" maxlength="3000" required>{{ old('note_body') }}</textarea>
                    <x-field-error name="note_body" />
                </div>
                <div class="form-actions"><button type="submit" class="btnx btn-primaryx">ثبت یادداشت</button></div>
            </form>
        </details>
    @endif
</section>
