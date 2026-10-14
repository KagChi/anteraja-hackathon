@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Profil" display-mode="large" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4">

        {{-- Identity. --}}
        <native:column class="w-full items-center gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
            <native:column class="h-16 w-16 items-center justify-center rounded-full bg-theme-primary">
                <native:icon :ios="Ios::PersonFill" :android="Android::Person" :size="30"
                    class="text-theme-on-primary" />
            </native:column>
            <native:column class="items-center gap-0.5">
                <native:text class="text-xl font-bold text-theme-on-surface">{{ $profile['name'] }}</native:text>
                <native:text class="text-sm text-theme-secondary">{{ $profile['phone'] }}</native:text>
            </native:column>
        </native:column>

        {{-- Lifetime numbers. --}}
        <native:row class="w-full gap-2">
            @foreach ($profile['stats'] as $stat)
                <native:column class="flex-1 items-center gap-1 rounded-xl bg-theme-surface-container-lowest p-2"
                    native:key="stat-{{ $stat['label'] }}">
                    <native:text class="text-xl font-bold text-theme-on-surface">{{ $stat['value'] }}</native:text>
                    <native:text class="text-center text-sm text-theme-secondary">{{ $stat['label'] }}</native:text>
                </native:column>
            @endforeach
        </native:row>

        {{-- Where the parcels go. --}}
        <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="w-full items-start gap-3">
                <native:column class="h-9 w-9 items-center justify-center rounded-lg bg-theme-surface-container">
                    <native:icon :ios="Ios::LocationFill" :android="Android::LocationOn" :size="18"
                        class="text-theme-secondary" />
                </native:column>
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Alamat Utama</native:text>
                    <native:text class="text-md text-theme-on-surface select-text">{{ $profile['address'] }}</native:text>
                </native:column>
            </native:row>
        </native:column>

        {{-- The one channel out. --}}
        <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:text class="text-sm text-theme-secondary uppercase">Kontak</native:text>

            <native:pressable class="w-full" @tap="openSupport" a11y-label="Hubungi {{ $support['label'] }}">
                <native:row class="w-full items-center gap-3">
                    <native:icon :ios="Ios::BubbleLeftAndTextBubbleRight" :android="Android::SupportAgent" :size="18"
                        class="text-theme-primary" />
                    <native:text class="flex-1 text-md text-theme-on-surface">{{ $support['label'] }}</native:text>
                    <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="16"
                        class="text-theme-primary" />
                </native:row>
            </native:pressable>

            <native:row class="w-full items-center gap-3">
                <native:icon :ios="Ios::Envelope" :android="Android::Mail" :size="18" class="text-theme-secondary" />
                <native:text class="flex-1 text-md text-theme-on-surface select-text">{{ $profile['email'] }}</native:text>
            </native:row>
        </native:column>

        {{-- Appearance. --}}
        @include('native.partials.appearance', ['appearance' => $appearance, 'appearanceOptions' => $appearanceOptions])

    </native:column>
</scroll-view>
