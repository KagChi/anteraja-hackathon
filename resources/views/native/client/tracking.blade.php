@use('App\Icons\Ios')
@use('App\Icons\Android')
@use('App\Support\ClientData')

<native:top-bar title="Pelacakan Kurir Beku" back>
    <native:top-bar-action id="help" label="Bantuan" @tap="openSupport"
        :ios-icon="Ios::QuestionmarkCircle" :android-icon="Android::HelpOutline" />
</native:top-bar>

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-6">

        @if ($order === null)
            @include('native.client.partials.missing-order', ['resi' => $resi])
        @else

            {{-- Which parcel this is, and where it should land. --}}
            <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:row class="w-full items-center justify-between gap-2">
                    <native:column class="gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Nomor Resi</native:text>
                        <native:text class="text-lg font-bold text-theme-on-surface select-text">
                            {{ $order['resi'] }}
                        </native:text>
                    </native:column>
                    <native:column class="rounded-full px-2 py-0.5 {{ ClientData::statusTone($order['status_key']) }}">
                        <native:text class="text-sm font-bold {{ ClientData::statusLabelTone($order['status_key']) }}">
                            {{ $order['status'] }}
                        </native:text>
                    </native:column>
                </native:row>

                <native:column class="w-full gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Produk</native:text>
                    <native:text class="text-md text-theme-on-surface">{{ $order['product'] }}</native:text>
                    <native:text class="text-sm text-theme-secondary">{{ $order['product_meta'] }}</native:text>
                </native:column>

                <native:row class="w-full gap-4">
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Estimasi Tiba</native:text>
                        <native:text class="text-md text-theme-on-surface">{{ $order['eta_date'] }}</native:text>
                        <native:text class="text-sm text-theme-secondary">{{ $order['eta_time'] }}</native:text>
                    </native:column>
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Penerima</native:text>
                        <native:text class="text-md text-theme-on-surface">{{ $order['recipient'] }}</native:text>
                        <native:text class="text-sm text-theme-secondary">{{ $order['recipient_area'] }}</native:text>
                    </native:column>
                </native:row>

                {{-- Who has it, and in which cooler box. --}}
                <native:row class="w-full items-center justify-between rounded-lg bg-theme-surface-container-low p-3">
                    <native:row class="items-center gap-2">
                        <native:icon :ios="Ios::Scooter" :android="Android::TwoWheeler" :size="20"
                            class="text-theme-secondary" />
                        <native:column class="gap-0.5">
                            <native:text class="text-sm text-theme-secondary">Kurir Pengantar</native:text>
                            <native:text class="text-md text-theme-on-surface">{{ $order['courier'] }}</native:text>
                        </native:column>
                    </native:row>
                    <native:column class="rounded bg-theme-surface-container-lowest px-2 py-0.5">
                        <native:text class="text-sm text-theme-secondary">{{ $order['courier_box'] }}</native:text>
                    </native:column>
                </native:row>
            </native:column>

            {{-- The reading that makes this a frozen delivery. --}}
            <native:row class="w-full items-center justify-between rounded-xl bg-theme-surface-container-lowest p-5">
                <native:row class="items-center gap-3">
                    <native:column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-surface-container">
                        <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="20"
                            class="text-theme-tertiary" />
                    </native:column>
                    <native:column class="gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Suhu Cooler Box</native:text>
                        <native:row class="items-center gap-1">
                            <native:text class="text-xl font-bold text-theme-on-surface">{{ $order['temperature'] }}</native:text>
                            <native:text class="text-sm font-bold text-theme-tertiary">
                                ({{ $order['temperature_state'] }})
                            </native:text>
                        </native:row>
                    </native:column>
                </native:row>
                <native:column class="items-end gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Rantai Dingin</native:text>
                    <native:text class="text-sm text-theme-tertiary">{{ $order['cold_chain'] }}</native:text>
                </native:column>
            </native:row>

            {{-- Every scan so far, newest first. --}}
            <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:row class="w-full items-center justify-between">
                    <native:text class="text-lg font-bold text-theme-on-surface">Riwayat Perjalanan</native:text>
                    <native:text class="text-sm text-theme-secondary">WIB</native:text>
                </native:row>

                @foreach ($order['timeline'] as $node)
                    <native:row class="w-full items-start gap-3" native:key="node-{{ $node['label'] }}">
                        <native:column class="w-4 items-center pt-1">
                            <native:icon :ios="Ios::CircleFill" :android="Android::Circle"
                                :size="($node['active'] ?? false) ? 12 : 8"
                                class="{{ ($node['active'] ?? false) ? 'text-theme-primary' : 'text-theme-secondary' }}" />
                        </native:column>
                        <native:column class="flex-1 gap-0.5">
                            <native:row class="w-full items-center justify-between gap-2">
                                <native:text class="text-md {{ ($node['active'] ?? false) ? 'font-bold text-theme-primary' : 'text-theme-on-surface' }}">
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

            {{-- What this shipment needs from the customer next. --}}
            <native:column class="w-full gap-3 pt-1">
                @if ($order['status_key'] === 'waiting')
                    <native:button variant="primary" label="Konfirmasi Jadwal" @tap="openSchedule"
                        :ios-icon="Ios::CalendarBadgeClock" :android-icon="Android::EventAvailable" />
                @endif

                @if ($order['status_key'] === 'issue')
                    <native:button variant="destructive" label="Lihat Detail Kendala" @tap="openIssue"
                        :ios-icon="Ios::ExclamationmarkTriangle" :android-icon="Android::ReportProblem" />
                @endif

                <native:button variant="secondary" label="Hubungi Kurir" @tap="callCourier"
                    :ios-icon="Ios::PhoneFill" :android-icon="Android::Call" />
            </native:column>

        @endif

    </native:column>
</scroll-view>
