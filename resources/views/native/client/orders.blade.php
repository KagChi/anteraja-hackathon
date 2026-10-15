@use('App\Icons\Ios')
@use('App\Icons\Android')
@use('App\Support\ClientData')

<native:top-bar title="Pesanan" display-mode="large" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @else

        {{-- How much is on the road right now. --}}
        <native:column class="w-full gap-1 rounded-xl bg-theme-surface-container-low p-4">
            <native:text class="text-sm text-theme-secondary uppercase">Pesanan Aktif</native:text>
            <native:text class="text-xl font-bold text-theme-on-surface">
                {{ count($orders) }} pengiriman berjalan
            </native:text>
        </native:column>

        @forelse ($orders as $order)
            <native:pressable class="w-full" native:key="order-{{ $order['resi'] }}"
                @navigate="'/client/tracking/' . $order['resi']"
                a11y-label="Lacak paket {{ $order['resi'] }}">
                <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">

                    {{-- Resi and the state of the shipment. --}}
                    <native:row class="w-full items-center justify-between gap-2">
                        <native:text class="text-sm text-theme-secondary select-text">{{ $order['resi'] }}</native:text>
                        <native:column class="rounded-full px-2 py-0.5 {{ ClientData::statusTone($order['status_key']) }}">
                            <native:text class="text-sm font-bold {{ ClientData::statusLabelTone($order['status_key']) }}">
                                {{ $order['status'] }}
                            </native:text>
                        </native:column>
                    </native:row>

                    <native:column class="w-full gap-0.5">
                        <native:text class="text-lg font-bold text-theme-on-surface">{{ $order['product'] }}</native:text>
                        <native:text class="text-sm text-theme-secondary">{{ $order['product_meta'] }}</native:text>
                    </native:column>

                    <native:column class="w-full gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Penerima</native:text>
                        <native:text class="text-base text-theme-on-surface">
                            {{ $order['recipient'] }} • {{ $order['recipient_area'] }}
                        </native:text>
                    </native:column>

                    <native:row class="w-full items-center justify-between pt-2">
                        <native:text class="text-sm text-theme-secondary">
                            {{ $order['eta_date'] }} • {{ $order['eta_time'] }}
                        </native:text>
                        <native:row class="items-center gap-1">
                            <native:text class="text-base font-bold text-theme-primary">Lacak</native:text>
                            <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="16"
                                class="text-theme-primary" />
                        </native:row>
                    </native:row>

                </native:column>
            </native:pressable>
        @empty
            <native:text class="text-base text-theme-secondary">Belum ada pengiriman berjalan.</native:text>
        @endforelse

        @endif
    </native:column>
</scroll-view>
