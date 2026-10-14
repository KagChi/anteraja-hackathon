@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Konfirmasi Jadwal" back>
    <native:top-bar-action id="help" label="Bantuan" @tap="openSupport"
        :ios-icon="Ios::QuestionmarkCircle" :android-icon="Android::HelpOutline" />
</native:top-bar>

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif ($schedule === null)
            @include('native.client.partials.missing-order', ['resi' => $resi])
        @else

            {{-- Where this stands, and which parcel it is. --}}
            <native:row class="w-full items-center justify-between gap-2">
                <native:row class="items-center gap-2 rounded bg-theme-secondary-container px-3 py-1">
                    <native:icon :ios="Ios::CircleFill" :android="Android::Circle" :size="8"
                        class="text-theme-primary" />
                    <native:text class="text-sm text-theme-on-secondary-container uppercase">
                        {{ $schedule['status'] }}
                    </native:text>
                </native:row>
                <native:text class="text-sm text-theme-secondary select-text">ID: {{ $order['resi'] }}</native:text>
            </native:row>

            {{-- Where it goes. --}}
            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:text class="text-sm text-theme-secondary uppercase">Tujuan Pengiriman</native:text>
                <native:row class="w-full items-start gap-3">
                    <native:column class="h-8 w-8 items-center justify-center rounded bg-theme-surface-container">
                        <native:icon :ios="Ios::LocationFill" :android="Android::LocationOn" :size="18"
                            class="text-theme-secondary" />
                    </native:column>
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-lg font-bold text-theme-on-surface">{{ $order['recipient'] }}</native:text>
                        <native:text class="text-sm leading-snug text-theme-secondary select-text">
                            {{ $order['address'] }}
                        </native:text>
                    </native:column>
                </native:row>
            </native:column>

            {{-- What is being carried, and how it has to be treated. --}}
            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary uppercase">Detail Muatan Beku</native:text>
                    <native:badge :label="$schedule['cold_badge']" variant="success" />
                </native:row>

                <native:row class="w-full items-center gap-3">
                    <native:column class="h-16 w-16 items-center justify-center rounded-lg bg-theme-surface-container">
                        <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="28"
                            class="text-theme-tertiary" />
                    </native:column>
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-sm text-theme-tertiary">{{ $schedule['product_category'] }}</native:text>
                        <native:text class="text-lg font-bold text-theme-on-surface">
                            {{ $schedule['product_name'] }}
                        </native:text>
                        <native:text class="text-sm text-theme-secondary">{{ $schedule['product_meta'] }}</native:text>
                    </native:column>
                </native:row>

                <native:row class="w-full items-start gap-2 rounded bg-theme-surface-container-low p-3">
                    <native:icon :ios="Ios::Shippingbox" :android="Android::Inventory2" :size="16"
                        class="text-theme-tertiary" />
                    <native:text class="flex-1 text-sm leading-relaxed text-theme-on-surface-variant">
                        {{ $schedule['product_note'] }}
                    </native:text>
                </native:row>
            </native:column>

            {{-- The window the customer is being asked to accept. --}}
            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary uppercase">Jadwal Pengantaran Terpilih</native:text>
                    <native:text class="text-sm font-bold text-theme-primary">{{ $schedule['priority'] }}</native:text>
                </native:row>

                <native:column class="w-full gap-1 rounded bg-theme-surface-container p-4">
                    <native:row class="items-center gap-2">
                        <native:icon :ios="Ios::Clock" :android="Android::Schedule" :size="20"
                            class="text-theme-primary" />
                        <native:text class="text-xl font-bold text-theme-on-surface">{{ $schedule['window'] }}</native:text>
                    </native:row>
                    <native:text class="text-sm text-theme-secondary">{{ $schedule['window_date'] }}</native:text>
                </native:column>

                <native:row class="w-full items-center justify-between gap-2">
                    <native:row class="items-center gap-3">
                        <native:column class="h-9 w-9 items-center justify-center rounded-full bg-theme-secondary-container">
                            <native:icon :ios="Ios::Scooter" :android="Android::TwoWheeler" :size="20"
                                class="text-theme-on-secondary-container" />
                        </native:column>
                        <native:column class="gap-0.5">
                            <native:text class="text-md text-theme-on-surface">{{ $schedule['courier'] }}</native:text>
                            <native:text class="text-sm text-theme-secondary">{{ $schedule['courier_role'] }}</native:text>
                        </native:column>
                    </native:row>
                    <native:column class="h-6 w-6 items-center justify-center rounded-full bg-theme-tertiary-fixed">
                        <native:icon :ios="Ios::CheckmarkSeal" :android="Android::Verified" :size="14"
                            class="text-theme-tertiary" a11y-label="Sertifikasi cold-chain" />
                    </native:column>
                </native:row>
            </native:column>

            {{-- Why the window matters. --}}
            <native:row class="w-full items-start gap-3 rounded-xl bg-theme-surface-container-low p-4">
                <native:icon :ios="Ios::InfoCircleFill" :android="Android::Info" :size="20"
                    class="text-theme-primary" />
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-md font-bold text-theme-on-surface">{{ $schedule['notice_title'] }}</native:text>
                    <native:text class="text-sm leading-relaxed text-theme-secondary">{{ $schedule['notice'] }}</native:text>
                </native:column>
            </native:row>

            {{-- Accept it, or ask for another one. --}}
            @include('native.partials.action-error', ['message' => $actionError])

            <native:column class="w-full gap-3 pt-1">
                @if ($state === 'confirmed')
                    <native:button variant="success" label="Jadwal Terkonfirmasi" disabled
                        :ios-icon="Ios::CheckmarkCircle" :android-icon="Android::CheckCircle" />
                @elseif ($state === 'rescheduled')
                    <native:button variant="success" label="Permintaan Jadwal Terkirim" disabled
                        :ios-icon="Ios::CalendarBadgeClock" :android-icon="Android::EventAvailable" />
                @else
                    <native:button variant="primary" label="Siap Menerima" @tap="confirm"
                        :ios-icon="Ios::CheckmarkCircle" :android-icon="Android::CheckCircle" />
                @endif

                <native:button variant="secondary" label="Minta Jadwal Lain" @tap="requestOtherWindow"
                    :ios-icon="Ios::CalendarBadgeClock" :android-icon="Android::EventAvailable" />
            </native:column>

        @endif

    </native:column>
</scroll-view>

@if ($rescheduleOpen)
    <native:bottom-sheet @dismiss="closeReschedule">
        <native:column class="w-full gap-4 p-4">
            <native:text class="text-xl font-bold text-theme-on-surface">Pilih Jadwal Baru</native:text>

            <native:column class="w-full gap-2">
                <native:text class="text-sm text-theme-secondary uppercase">Tanggal</native:text>
                <native:row class="w-full gap-2">
                    @foreach ($dateOptions as $option)
                        <native:pressable class="flex-1" @tap="chooseRescheduleDate('{{ $option['value'] }}')"
                            a11y-label="{{ $option['label'] }}">
                            <native:column class="w-full items-center justify-center rounded-xl p-3 {{ $rescheduleDate === $option['value'] ? 'bg-theme-primary' : 'bg-theme-surface-container-low' }}">
                                <native:text class="text-md {{ $rescheduleDate === $option['value'] ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">
                                    {{ $option['label'] }}
                                </native:text>
                            </native:column>
                        </native:pressable>
                    @endforeach
                </native:row>
            </native:column>

            <native:column class="w-full gap-2">
                <native:text class="text-sm text-theme-secondary uppercase">Waktu</native:text>
                @foreach ($schedule['available_slots'] as $slot)
                    <native:pressable class="w-full rounded-lg p-3 {{ $rescheduleSlot === $slot ? 'bg-theme-primary' : 'bg-theme-surface-container-low' }}"
                        @tap="chooseRescheduleSlot('{{ $slot }}')" a11y-label="{{ $slot }}">
                        <native:text class="text-md {{ $rescheduleSlot === $slot ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">
                            {{ $slot }} WIB
                        </native:text>
                    </native:pressable>
                @endforeach
            </native:column>

            <native:button variant="primary" label="Kirim Permintaan" @tap="submitReschedule" />
            <native:button variant="secondary" label="Batal" @tap="closeReschedule" />
        </native:column>
    </native:bottom-sheet>
@endif
