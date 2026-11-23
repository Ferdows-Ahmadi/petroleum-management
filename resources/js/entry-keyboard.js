const implicitSubmitTypes = new Set([
    'text', 'radio', 'search', 'number', 'email', 'tel', 'url', 'password',
    'date', 'time', 'month', 'week', 'datetime-local',
]);

export function preventImplicitEntrySubmit(event) {
    const input = event.target;
    if (event.key === 'Enter' && !event.isComposing && event.keyCode !== 229
        && input.tagName === 'INPUT' && implicitSubmitTypes.has(input.type)
        && input.form?.method.toLowerCase() === 'post') {
        event.preventDefault();
    }
}
