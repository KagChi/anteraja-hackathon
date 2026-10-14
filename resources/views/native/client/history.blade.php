@use('App\Icons\Ios')
@use('App\Icons\Android')
@use('App\Support\ClientData')

<native:top-bar title="Riwayat" display-mode="large" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4">

        {{-- Everything that already arrived. --}}
        <native:row class="w-full items-center justify-between gap-2 rounded-xl bg-theme-surface-container-low p-4">
            <native:column class="gap-1">
                <native:text class="text-sm text-theme-secondary uppercase">Selesai</native:text>
                <native:text class="text-xl font-bold text-theme-on-surface">
                    {{ count($orders) }} pengiriman diterima
                </native:text>
            </native:column>
            <native:badge :label="$onTime . ' Tepat Waktu'" variant="success" />
        </native:row>

        @foreach ($orders as $order)
            <native:pressable class="w-full" native:key="history-{{ $order['resi'] }}"
                @navigate="'/client/tracking/' . $order['resi']"
                a11y-label="Lihat detail {{ $order['resi'] }}">
                <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">

                    <native:row class="w-full items-center justify-between gap-2">
                        <native:text class="text-sm text-theme-secondary select-text">{{ $order['resi'] }}</native:text>
                        <native:row class="items-center gap-1 rounded-full px-2 py-0.5 {{ ClientData::statusTone($order['status_key']) }}">
                            <native:icon :ios="Ios::CheckmarkCircle" :android="Android::CheckCircle" :size="14"
                                class="{{ ClientData::statusLabelTone($order['status_key']) }}" />
                            <native:text class="text-sm font-bold {{ ClientData::statusLabelTone($order['status_key']) }}">
                                {{ $order['status'] }}
                            </native:text>
                        </native:row>
                    </native:row>

                    <native:text class="text-lg font-bold text-theme-on-surface">{{ $order['product'] }}</native:text>

                    <native:row class="w-full items-center justify-between pt-2">
                        <native:text class="text-sm text-theme-secondary">
                            {{ $order['eta_date'] }} • {{ $order['eta_time'] }}
                        </native:text>
                        <native:row class="items-center gap-1">
                            <native:text class="text-md font-bold text-theme-primary">Lihat Detail</native:text>
                            <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="16"
                                class="text-theme-primary" />
                        </native:row>
                    </native:row>

                </native:column>
            </native:pressable>
        @endforeach

    </native:column>
</scroll-view>
