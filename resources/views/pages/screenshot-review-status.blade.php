<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem;">
        @forelse ($this->getPanelCards() as $card)
            <x-filament::section>
                <x-slot name="heading">{{ $card['label'] }}</x-slot>

                @if ($card['hero_url'])
                    <img
                        src="{{ $card['hero_url'] }}"
                        alt="{{ $card['label'] }} hero"
                        class="rounded-md"
                        style="display:block;width:100%;height:200px;object-fit:cover;"
                    />
                @else
                    <div class="rounded-md bg-gray-100 dark:bg-gray-800"
                         style="display:flex;align-items:center;justify-content:center;height:200px;">
                        <span class="text-sm text-gray-500">No captures yet</span>
                    </div>
                @endif

                <div style="margin-top:0.75rem;display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;">
                    <div>
                        <div class="text-xs text-gray-500">Approved</div>
                        <div class="text-sm font-medium text-success-600">{{ $card['approved'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Pending</div>
                        <div class="text-sm font-medium">{{ $card['pending'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Changes</div>
                        <div class="text-sm font-medium text-warning-600">{{ $card['changes_requested'] }}</div>
                    </div>
                </div>

                <div class="text-xs text-gray-500" style="margin-top:0.75rem;">
                    @if ($card['last_captured_at'])
                        Last captured {{ $card['last_captured_at']->diffForHumans() }} · tag <code>{{ $card['latest_tag'] }}</code>
                    @else
                        No captures yet — run <code>screenshot:dispatch</code>
                    @endif
                </div>

                <div style="margin-top:0.75rem;display:flex;justify-content:flex-end;gap:0.75rem;">
                    @if (! empty($card['catalogue_url']))
                        <a href="{{ $card['catalogue_url'] }}" target="_blank" rel="noopener"
                           class="text-sm font-medium text-gray-500 hover:text-gray-300">
                            Catalogue ↗
                        </a>
                    @endif
                    <a href="{{ $card['review_url'] }}"
                       class="text-sm font-medium text-primary-600 hover:text-primary-500">
                        Review pending →
                    </a>
                </div>
            </x-filament::section>
        @empty
            <div class="text-sm text-gray-500" style="grid-column:1/-1;">
                No panels registered. Use
                <code>PanelRegistry::register(new PanelDescriptor(...))</code> to add one.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
