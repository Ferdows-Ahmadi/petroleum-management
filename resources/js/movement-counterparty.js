const roles = { CUSTOMER_RECEIPT: 'CUSTOMER', SUPPLIER_PAYMENT: 'SUPPLIER' };

export function bindMovementCounterparty(scope, renderedRole, resetSelection) {
    const type = scope.querySelector('[name="movement_type"]');
    const deal = scope.querySelector('[name="deal_id"]');
    const synchronize = () => {
        const role = roles[type.value] ?? '';
        let suggestedId;
        if (deal.value && deal.value === scope.dataset.dealId) {
            if (role === 'CUSTOMER') suggestedId = scope.dataset.customerId;
            if (role === 'SUPPLIER') suggestedId = scope.dataset.supplierId;
        }
        resetSelection(role, suggestedId);
    };
    // Preserve the rendered draft or validation input until the movement type changes.
    if (!roles[type.value] || roles[type.value] !== renderedRole) synchronize();
    type.addEventListener('change', synchronize);
}
