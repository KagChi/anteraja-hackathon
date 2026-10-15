@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Detail Paket" back />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-6">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif ($delivery === null)
            <native:column class="w-full items-center gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:text class="text-lg font-bold text-theme-on-surface">Paket tidak ditemukan</native:text>
                <native:text class="text-center text-sm text-theme-secondary">
                    Paket ini tidak dapat dibuka. Pilih tugas lain dari daftar.
                </native:text>
                <native:button variant="primary" label="Kembali ke Tugas" @navigate="'/courier/tasks'" />
            </native:column>
        @else

        {{-- Resi and service tier. --}}
        <native:row class="w-full items-center justify-between rounded-xl bg-theme-surface-container-lowest p-5">
            <native:column class="gap-0.5">
                <native:text class="text-sm text-theme-secondary">Nomor Resi</native:text>
                <native:text class="text-lg font-bold text-theme-on-surface tracking-tight select-text">
                    {{ $delivery['tracking'] }}
                </native:text>
            </native:column>
            <native:badge :label="$delivery['service']" variant="secondary" />
        </native:row>

        {{-- Progress: departed, on the way, arrived. --}}
        <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:text class="text-sm text-theme-secondary uppercase tracking-wider">Progres Pengantaran</native:text>
            <native:row class="w-full gap-2">
                <native:column class="flex-1 items-center gap-1 rounded-lg bg-theme-surface-container-low p-2">
                    <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="16" class="text-theme-tertiary" />
                    <native:text class="text-sm text-theme-secondary">Berangkat</native:text>
                </native:column>
                <native:column class="flex-1 items-center gap-1 rounded-lg bg-theme-primary-fixed p-2">
                    <native:icon :ios="Ios::TruckBox" :android="Android::LocalShipping" :size="16" class="text-theme-primary" />
                    <native:text class="text-sm font-bold text-theme-primary">Sedang Diantar</native:text>
                </native:column>
                <native:pressable class="flex-1" @tap="markArrived" a11y-label="Tandai tiba di lokasi">
                    <native:column class="w-full items-center gap-1 rounded-lg p-2 {{ $arrived ? 'bg-theme-primary-fixed' : 'bg-theme-surface-container-high' }}">
                        <native:icon :ios="Ios::LocationFill" :android="Android::LocationOn" :size="16"
                            class="{{ $arrived ? 'text-theme-primary' : 'text-theme-secondary' }}" />
                        <native:text class="text-sm font-bold {{ $arrived ? 'text-theme-primary' : 'text-theme-secondary' }}">
                            Tiba di Lokasi
                        </native:text>
                    </native:column>
                </native:pressable>
            </native:row>
        </native:column>

        {{-- Location validation: what the server saw and what it decided. It is
             advisory until an action is refused, and then the reason travels
             with the failure. --}}
        <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="w-full items-center justify-between gap-2">
                <native:row class="items-center gap-2">
                    <native:icon :ios="Ios::Location" :android="Android::LocationOn" :size="18"
                        class="text-theme-secondary" />
                    <native:text class="text-sm text-theme-secondary uppercase tracking-wider">
                        Validasi Lokasi
                    </native:text>
                </native:row>
                <native:badge :label="$delivery['gps']['level_label']" variant="{{ $gpsVariant }}" />
            </native:row>

            @if ($gpsDistance !== null)
                <native:text class="text-base font-bold text-theme-on-surface">{{ $gpsDistance }}</native:text>
            @else
                <native:text class="text-sm text-theme-secondary">
                    Menunggu sinyal GPS. Titik terkumpul: {{ $fixCount }}.
                </native:text>
            @endif

            <native:row class="w-full items-center justify-between gap-2">
                <native:text class="text-sm text-theme-secondary">
                    Radius diizinkan {{ $delivery['gps']['radius_m'] }} m
                </native:text>
                @if ($gpsAccuracy !== null)
                    <native:text class="text-sm text-theme-secondary">{{ $gpsAccuracy }}</native:text>
                @endif
            </native:row>

            @foreach ($delivery['gps']['reasons'] as $reason)
                <native:row class="w-full items-start gap-2" native:key="gps-reason-{{ $loop->index }}">
                    <native:icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::ErrorOutline" :size="16"
                        class="{{ ($reason['severity'] ?? '') === 'strong' ? 'text-theme-error' : 'text-theme-secondary' }}" />
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-sm font-bold text-theme-on-surface">{{ $reason['label'] }}</native:text>
                        <native:text class="text-sm leading-snug text-theme-secondary">{{ $reason['detail'] }}</native:text>
                    </native:column>
                </native:row>
            @endforeach

            @if (($delivery['gps']['lock_status'] ?? null) !== null)
                <native:row class="w-full items-center gap-2 rounded-lg bg-theme-surface-container-low p-3">
                    <native:icon :ios="Ios::Clock" :android="Android::Schedule" :size="16"
                        class="text-theme-secondary" />
                    <native:text class="flex-1 text-sm text-theme-secondary">
                        Tinjauan hub: {{ $delivery['gps']['lock_status'] }}
                    </native:text>
                </native:row>
            @endif

            @if ($gpsNotice !== '')
                <native:row class="w-full items-center gap-2 rounded-lg bg-theme-tertiary-fixed p-3">
                    <native:icon :ios="Ios::CheckmarkCircle" :android="Android::CheckCircle" :size="16"
                        class="text-theme-on-tertiary-fixed" />
                    <native:text class="flex-1 text-sm text-theme-on-tertiary-fixed">{{ $gpsNotice }}</native:text>
                </native:row>
            @endif

            @if ($gpsCanReview)
                <native:button class="w-full" variant="secondary" label="Minta Tinjauan Hub" @tap="requestReview"
                    :ios-icon="Ios::QuestionmarkCircle" :android-icon="Android::HelpOutline" />
            @endif
        </native:column>

        @if ($delivery['can_depart'])
            <native:button class="w-full" variant="primary" label="Mulai Berangkat" @tap="markDepart"
                :ios-icon="Ios::TruckBox" :android-icon="Android::LocalShipping" />
        @endif

        {{-- Recipient, address, and the cold-chain instruction. --}}
        <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-5">
            <native:row class="w-full items-start justify-between gap-2">
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Penerima</native:text>
                    <native:text class="text-xl font-bold text-theme-on-surface">{{ $delivery['recipient'] }}</native:text>
                    <native:text class="text-base text-theme-on-surface">{{ $delivery['phone'] }}</native:text>
                </native:column>
                <native:button variant="secondary" label="Panggil" @tap="callRecipient"
                    :ios-icon="Ios::PhoneFill" :android-icon="Android::Call" />
            </native:row>

            <native:divider />

            <native:column class="w-full gap-1">
                <native:row class="items-center gap-1">
                    <native:icon :ios="Ios::MappinAndEllipse" :android="Android::PinDrop" :size="16" class="text-theme-secondary" />
                    <native:text class="text-sm text-theme-secondary">Alamat Tujuan</native:text>
                </native:row>
                <native:text class="text-base leading-relaxed text-theme-on-surface">{{ $delivery['address'] }}</native:text>
            </native:column>

            <native:row class="w-full items-start gap-2 rounded-lg bg-theme-error-container p-3">
                <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="20" class="text-theme-error" />
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-sm font-bold text-theme-on-error-container uppercase tracking-wider">
                        Instruksi Khusus
                    </native:text>
                    <native:text class="text-sm leading-snug text-theme-on-error-container">{{ $delivery['instruction'] }}</native:text>
                </native:column>
            </native:row>
        </native:column>

        {{-- Route. The web embedded a decorative map; native hands this to
             the system maps app instead. --}}
        <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:text class="text-base text-theme-on-surface">{{ $delivery['map_label'] }}</native:text>
            <native:button variant="secondary" label="Buka Navigasi Rute" @tap="openRoute"
                :ios-icon="Ios::MapFill" :android-icon="Android::Navigation" />
        </native:column>

        @include('native.partials.action-error', ['message' => $actionError])

        <native:button variant="primary" label="Serahkan Paket"
            @navigate="'/courier/proof/' . $resi"
            :ios-icon="Ios::Shippingbox" :android-icon="Android::Inventory" />

        <native:pressable class="items-center py-2" @tap="openSheet" a11y-label="Laporkan kendala">
            <native:text class="text-base text-theme-secondary underline">Laporkan Kendala</native:text>
        </native:pressable>

        @endif
    </native:column>
</scroll-view>

@if ($sheetOpen)
    <native:bottom-sheet @dismiss="closeSheet">
        <native:column class="w-full gap-4 p-4">
            <native:text class="text-xl font-bold text-theme-on-surface">Pilih Kendala</native:text>
            @foreach ($issues as $issue)
                <native:pressable class="w-full rounded-lg bg-theme-surface-container-low p-3"
                    @tap="reportIssue('{{ $issue }}')" a11y-label="{{ $issue }}">
                    <native:text class="text-base text-theme-on-surface">{{ $issue }}</native:text>
                </native:pressable>
            @endforeach
            <native:button variant="secondary" label="Batal" @tap="closeSheet" />
        </native:column>
    </native:bottom-sheet>
@endif
