@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Kendala Pengiriman" back>
    <native:top-bar-action id="help" label="Bantuan" @tap="openSupport"
        :ios-icon="Ios::QuestionmarkCircle" :android-icon="Android::HelpOutline" />
</native:top-bar>

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif ($issue === null)
            @include('native.client.partials.missing-order', ['resi' => $resi])
        @else

            {{-- Which parcel went wrong. --}}
            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary uppercase">No. Resi Pengiriman</native:text>
                    <native:text class="text-base font-bold text-theme-on-surface select-text">
                        {{ $order['resi'] }}
                    </native:text>
                </native:row>

                <native:divider />

                <native:row class="w-full gap-4">
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Penerima</native:text>
                        <native:text class="text-base text-theme-on-surface">{{ $order['recipient'] }}</native:text>
                    </native:column>
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Kurir Bertugas</native:text>
                        <native:text class="text-base text-theme-on-surface">{{ $order['courier'] }}</native:text>
                    </native:column>
                </native:row>

                <native:column class="w-full gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Produk Beku</native:text>
                    <native:text class="text-base font-bold text-theme-on-surface">{{ $order['product'] }}</native:text>
                </native:column>
            </native:column>

            {{-- What happened, and what operations decided. --}}
            <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:row class="w-full items-center justify-between gap-2">
                    <native:column class="rounded bg-theme-error-container px-3 py-0.5">
                        <native:text class="text-base text-theme-on-error-container">{{ $issue['status_badge'] }}</native:text>
                    </native:column>
                    <native:column class="rounded bg-theme-surface-container px-3 py-0.5">
                        <native:text class="text-sm text-theme-secondary">{{ $issue['temp_badge'] }}</native:text>
                    </native:column>
                </native:row>

                <native:column class="w-full gap-1">
                    <native:text class="text-sm text-theme-secondary">{{ $issue['problem_label'] }}</native:text>
                    <native:text class="text-lg font-bold text-theme-on-surface">{{ $issue['problem'] }}</native:text>
                </native:column>

                <native:column class="w-full gap-1 rounded-lg bg-theme-surface-container-low p-4">
                    <native:text class="text-sm font-bold text-theme-primary">{{ $issue['decision_label'] }}</native:text>
                    <native:text class="text-base leading-relaxed text-theme-on-surface">{{ $issue['decision'] }}</native:text>
                </native:column>
            </native:column>

            {{-- Why the cold chain decided it. --}}
            <native:column class="w-full gap-1 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:text class="text-base text-theme-tertiary">{{ $issue['quality_label'] }}</native:text>
                <native:text class="text-sm leading-relaxed text-theme-secondary">{{ $issue['quality'] }}</native:text>
            </native:column>

            {{-- How the hub handled it. --}}
            <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:text class="text-lg font-bold text-theme-on-surface">Riwayat Penanganan Hub</native:text>

                @foreach ($issue['history'] as $node)
                    <native:row class="w-full items-start gap-3" native:key="node-{{ $node['label'] }}">
                        <native:column class="w-4 items-center pt-1">
                            <native:icon :ios="Ios::CircleFill" :android="Android::Circle"
                                :size="($node['active'] ?? false) ? 12 : 8"
                                class="{{ ($node['active'] ?? false) ? 'text-theme-primary' : 'text-theme-secondary' }}" />
                        </native:column>
                        <native:column class="flex-1 gap-0.5">
                            <native:row class="w-full items-center justify-between gap-2">
                                <native:text class="text-base {{ ($node['active'] ?? false) ? 'font-bold text-theme-primary' : 'text-theme-on-surface' }}">
                                    {{ $node['label'] }}
                                </native:text>
                                <native:text class="text-sm text-theme-secondary">{{ $node['time'] }}</native:text>
                            </native:row>
                            <native:text class="text-sm leading-relaxed {{ ($node['active'] ?? false) ? 'text-theme-on-surface' : 'text-theme-secondary' }}">
                                {{ $node['detail'] }}
                            </native:text>
                        </native:column>
                    </native:row>
                @endforeach
            </native:column>

            {{-- The way out. --}}
            @include('native.partials.action-error', ['message' => $actionError])

            <native:column class="w-full items-center gap-3 pt-1">
                <native:button class="w-full" variant="primary" label="Ajukan Pengiriman Ulang" @tap="requestResend"
                    :ios-icon="Ios::ArrowClockwise" :android-icon="Android::Refresh" />
                <native:pressable class="w-full" @tap="openSupport" a11y-label="Hubungi layanan pelanggan">
                    <native:text class="text-center text-sm leading-relaxed text-theme-secondary">
                        Butuh bantuan langsung? Hubungi Layanan Pelanggan Anteraja Frozen.
                    </native:text>
                </native:pressable>
            </native:column>

        @endif

    </native:column>
</scroll-view>
