@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Riwayat Pengiriman" display-mode="large" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @else

        {{-- The day at a glance. --}}
        <native:row class="w-full items-center justify-between rounded-xl bg-theme-surface-container-lowest p-4">
            <native:column class="gap-1">
                <native:text class="text-sm text-theme-secondary uppercase">Selesai Hari Ini</native:text>
                <native:text class="text-xl font-bold text-theme-on-surface">{{ count($completed) }} Pengiriman</native:text>
            </native:column>
            <native:badge :label="$onTime . ' Tepat Waktu'" variant="success" />
        </native:row>

        @foreach ($completed as $row)
            <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-lowest p-4" native:key="history-{{ $row['resi'] }}">
                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm font-bold text-theme-on-surface select-text">{{ $row['resi'] }}</native:text>
                    @if ($row['status'] === 'Terkirim')
                        <native:badge :label="$row['status']" variant="success" />
                    @else
                        <native:badge :label="$row['status']" variant="destructive" />
                    @endif
                </native:row>
                <native:text class="text-md text-theme-on-surface">{{ $row['recipient'] }}</native:text>
                <native:text class="text-sm text-theme-secondary">{{ $row['completed_at'] }}</native:text>
            </native:column>
        @endforeach

        @endif
    </native:column>
</scroll-view>
