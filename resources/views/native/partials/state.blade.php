@use('App\Icons\Ios')
@use('App\Icons\Android')

{{-- The one card every wired screen falls back to when its API call fails. --}}
<native:column class="w-full items-center gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
    <native:column class="h-12 w-12 items-center justify-center rounded-full bg-theme-error-container">
        <native:icon :ios="Ios::WifiSlash" :android="Android::CloudOff" :size="24"
            class="text-theme-on-error-container" />
    </native:column>
    <native:text class="text-lg font-bold text-theme-on-surface">Gagal memuat data</native:text>
    <native:text class="text-center text-sm leading-relaxed text-theme-secondary">{{ $message }}</native:text>
    <native:button variant="primary" label="Coba Lagi" @tap="retry"
        :ios-icon="Ios::ArrowClockwise" :android-icon="Android::Refresh" />
</native:column>
