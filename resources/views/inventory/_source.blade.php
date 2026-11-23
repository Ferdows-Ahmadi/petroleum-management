@if($movement->dealSource)
    <a href="{{ route('deals.show', $movement->dealSource->deal) }}">{{ $movement->source_reference }}</a>
@elseif($movement->dealDestination)
    <a href="{{ route('deals.show', $movement->dealDestination->deal) }}">{{ $movement->source_reference }}</a>
@elseif($movement->purchaseItem)
    <a href="{{ route('purchases.show', $movement->purchaseItem->purchase) }}">{{ $movement->source_reference }}</a>
@elseif($movement->saleItem)
    <a href="{{ route('sales.show', $movement->saleItem->sale) }}">{{ $movement->source_reference }}</a>
@else
    {{ $movement->source_reference }}
@endif
