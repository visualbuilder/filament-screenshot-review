<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->getPanelCards() as $card)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                @if ($card['hero_url'])
                    <img
                        src="{{ $card['hero_url'] }}"
                        alt="{{ $card['label'] }} hero"
                        style="width:100%;height:200px;object-fit:cover;border-radius:0.5rem;margin-bottom:0.75rem;"
                    />
                @else
                    <div
                        class="flex h-[200px] items-center justify-center rounded-lg bg-gray-100 text-sm text-gray-500 dark:bg-gray-800"
                        style="margin-bottom:0.75rem;"
                    >
                        {{ __('No captures yet') }}
                    </div>
                @endif

                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $card['label'] }}
                </h3>

                <dl class="mt-2 grid grid-cols-3 gap-2 text-sm">
                    <div>
                        <dt class="text-gray-500">Approved</dt>
                        <dd class="font-medium text-success-600">{{ $card['approved'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Pending</dt>
                        <dd class="font-medium text-gray-600">{{ $card['pending'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Changes</dt>
                        <dd class="font-medium text-warning-600">{{ $card['changes_requested'] }}</dd>
                    </div>
                </dl>

                <div class="mt-3 text-xs text-gray-500">
                    @if ($card['last_captured_at'])
                        Last captured {{ $card['last_captured_at']->diffForHumans() }}
                        · tag <code>{{ $card['latest_tag'] }}</code>
                    @else
                        No captures yet — run <code>screenshot:dispatch</code>
                    @endif
                </div>

                <div class="mt-3 flex items-center justify-end gap-3">
                    @if (! empty($card['catalogue_url']))
                        <a
                            href="{{ $card['catalogue_url'] }}"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-300"
                            title="Open the public S3 catalogue index in a new tab"
                        >
                            Catalogue ↗
                        </a>
                    @endif

                    <a
                        href="{{ $card['review_url'] }}"
                        class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500"
                    >
                        Review pending →
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-sm text-gray-500">
                No panels registered. Use
                <code>PanelRegistry::register(new PanelDescriptor(...))</code>
                to add one.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
