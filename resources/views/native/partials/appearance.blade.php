{{-- The theme switcher: follow the device, or pin the app to light / dark.
     Both shells render the same card, and both resolve it through
     App\Support\Appearance — so the modes and their labels live in one place
     and the two profiles cannot drift. --}}
<native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
    <native:text class="text-sm text-theme-secondary uppercase">Tema</native:text>

    <native:button-group
        :options="$appearanceOptions"
        :value="$appearance"
        @change="setAppearance"
        a11y-label="Pilih tema tampilan" />

    <native:text class="text-sm leading-relaxed text-theme-secondary">
        Sistem mengikuti pengaturan perangkat.
    </native:text>
</native:column>
