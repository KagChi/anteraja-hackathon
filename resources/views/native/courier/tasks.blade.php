@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Daftar Tugas" display-mode="large" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif ($task === null)
            <native:column class="w-full items-center gap-2 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:text class="text-lg font-bold text-theme-on-surface">Tidak ada tugas</native:text>
                <native:text class="text-center text-sm text-theme-secondary">
                    Belum ada paket yang ditugaskan kepada Anda saat ini.
                </native:text>
            </native:column>
        @else

        {{-- Board state: how fresh the task is, and its id. --}}
        <native:row class="w-full items-center justify-between rounded-xl bg-theme-surface-container-high p-4">
            <native:row class="items-center gap-2">
                <native:badge label="Tugas Baru" variant="primary" />
                <native:text class="text-sm text-theme-secondary">{{ $task['age'] }}</native:text>
            </native:row>
            <native:text class="text-sm text-theme-on-surface-variant">ID: {{ $task['id'] }}</native:text>
        </native:row>

        <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-5">

            {{-- Resi and service tier. --}}
            <native:row class="w-full items-center justify-between gap-2 pb-1">
                <native:column class="gap-1">
                    <native:text class="text-sm text-theme-secondary uppercase">Nomor Resi / AWB</native:text>
                    <native:text class="text-lg font-bold text-theme-on-surface tracking-tight select-text">
                        {{ $task['tracking'] }}
                    </native:text>
                </native:column>
                <native:row class="items-center gap-1 rounded-full bg-theme-surface-container-high px-3 py-1">
                    <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="16" class="text-theme-primary" />
                    <native:text class="text-sm text-theme-on-surface">{{ $task['service'] }}</native:text>
                </native:row>
            </native:row>

            {{-- Delivery window and whether it is still on time. --}}
            <native:row class="w-full items-center justify-between rounded-lg bg-theme-surface-container p-2">
                <native:row class="items-center gap-2">
                    <native:icon :ios="Ios::Clock" :android="Android::Schedule" :size="20" class="text-theme-primary" />
                    <native:column class="gap-0.5">
                        <native:text class="text-sm text-theme-secondary">Jendela Pengantaran</native:text>
                        <native:text class="text-md text-theme-on-surface">{{ $task['window'] }}</native:text>
                    </native:column>
                </native:row>
                <native:badge :label="$task['window_status']" variant="success" />
            </native:row>

            {{-- Who to hand it to, and where. --}}
            <native:column class="w-full gap-2 pt-1">
                <native:row class="w-full items-center justify-between">
                    <native:text class="text-sm text-theme-secondary">Penerima Paket</native:text>
                    <native:text class="text-sm text-theme-secondary">{{ $task['place_type'] }}</native:text>
                </native:row>
                <native:text class="text-xl font-bold text-theme-on-surface">{{ $task['recipient'] }}</native:text>
                <native:text class="text-md leading-relaxed text-theme-secondary">{{ $task['address'] }}</native:text>
            </native:column>

            {{-- Cold-chain instruction — the reason this delivery is different. --}}
            <native:column class="w-full gap-1 rounded-lg bg-theme-surface-container-low p-4">
                <native:row class="items-center gap-1.5">
                    <native:icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="18" class="text-theme-primary" />
                    <native:text class="text-sm font-bold text-theme-primary uppercase tracking-wider">Instruksi Khusus</native:text>
                </native:row>
                <native:text class="text-md leading-snug text-theme-on-surface">{{ $task['instruction'] }}</native:text>
            </native:column>

            {{-- Koli / category / weight. --}}
            <native:row class="w-full gap-2">
                @foreach ($task['metrics'] as $metric)
                    <native:column class="flex-1 items-center gap-1 rounded-lg bg-theme-surface-container p-2">
                        <native:text class="text-sm text-theme-secondary">{{ $metric['label'] }}</native:text>
                        <native:text class="text-md font-bold text-theme-on-surface">{{ $metric['value'] }}</native:text>
                    </native:column>
                @endforeach
            </native:row>
        </native:column>

        {{-- How far, and how long. --}}
        <native:row class="w-full items-center justify-between rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="items-center gap-2">
                <native:icon :ios="Ios::LocationFill" :android="Android::NearMe" :size="20" class="text-theme-on-surface" />
                <native:column class="gap-0.5">
                    <native:text class="text-md text-theme-on-surface">Jarak Pengantaran</native:text>
                    <native:text class="text-sm text-theme-secondary">{{ $task['distance'] }}</native:text>
                </native:column>
            </native:row>
            <native:text class="text-md font-bold text-theme-on-surface">{{ $task['eta'] }}</native:text>
        </native:row>

        {{-- The decision. --}}
        @include('native.partials.action-error', ['message' => $actionError])

        @if ($state === 'accepted' || $state === 'active')
            <native:button variant="primary" :label="$state === 'active' ? 'Tugas Sedang Berjalan' : 'Tugas Diterima'" disabled
                :ios-icon="Ios::CheckmarkCircle" :android-icon="Android::DoneAll" />
        @else
            <native:row class="w-full items-center gap-4 pt-1">
                <native:button class="flex-1" variant="secondary" label="Tolak" @tap="reject" />
                <native:button class="flex-1" variant="primary" label="Terima Tugas" @tap="accept"
                    :ios-icon="Ios::CheckmarkCircle" :android-icon="Android::CheckCircle" />
            </native:row>
        @endif

        @if ($toast !== null)
            <native:row class="w-full items-center justify-between gap-2 rounded-xl bg-theme-inverse-surface p-4">
                <native:text class="flex-1 text-md text-theme-on-inverse-surface">{{ $toast }}</native:text>
                <native:pressable @tap="dismissToast" class="rounded-md px-2 py-1" a11y-label="Tutup">
                    <native:text class="text-sm text-theme-inverse-primary">Tutup</native:text>
                </native:pressable>
            </native:row>
        @endif

        @endif
    </native:column>
</scroll-view>
