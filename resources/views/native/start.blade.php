{{-- Shown for the instant the app spends deciding between the login screen
     and the first tab. It never lingers: the gate navigates in mount(). --}}
<native:column class="w-full h-full items-center justify-center gap-2 bg-theme-background">
    <native:text class="text-md text-theme-secondary">{{ $label }}</native:text>
</native:column>
