@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Profil Kurir" display-mode="large" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @else

        {{-- Identity. --}}
        <native:column class="w-full items-center gap-2 rounded-xl bg-theme-surface-container-lowest p-5">
            <native:icon :ios="Ios::PersonFill" :android="Android::Person" :size="56" class="text-theme-primary" />
            <native:text class="text-xl font-bold text-theme-on-surface">{{ $profile['name'] }}</native:text>
            <native:row class="items-center gap-1">
                <native:text class="text-sm text-theme-tertiary uppercase">{{ $profile['status'] }}</native:text>
            </native:row>
            <native:text class="text-sm text-theme-secondary">ID: {{ $profile['id'] }}</native:text>
        </native:column>

        {{-- Today's numbers. --}}
        <native:row class="w-full gap-2">
            @foreach ($profile['stats'] as $stat)
                <native:column class="flex-1 items-center gap-1 rounded-xl bg-theme-surface-container-lowest p-2" native:key="stat-{{ $stat['label'] }}">
                    <native:text class="text-sm text-theme-secondary">{{ $stat['label'] }}</native:text>
                    <native:text class="text-xl font-bold text-theme-on-surface">{{ $stat['value'] }}</native:text>
                </native:column>
            @endforeach
        </native:row>

        {{-- Assignment. --}}
        <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="items-center gap-2">
                <native:icon :ios="Ios::PhoneFill" :android="Android::Call" :size="18" class="text-theme-secondary" />
                <native:column class="gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Telepon</native:text>
                    <native:text class="text-md text-theme-on-surface">{{ $profile['phone'] }}</native:text>
                </native:column>
            </native:row>
            <native:row class="items-center gap-2">
                <native:icon :ios="Ios::Building2" :android="Android::Warehouse" :size="18" class="text-theme-secondary" />
                <native:column class="gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Hub</native:text>
                    <native:text class="text-md text-theme-on-surface">{{ $profile['hub'] }}</native:text>
                </native:column>
            </native:row>
            <native:row class="items-center gap-2">
                <native:icon :ios="Ios::Scooter" :android="Android::TwoWheeler" :size="18" class="text-theme-secondary" />
                <native:column class="gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Armada</native:text>
                    <native:text class="text-md text-theme-on-surface">{{ $profile['vehicle'] }}</native:text>
                </native:column>
            </native:row>
        </native:column>

        <native:button variant="secondary" label="Keluar" @tap="logout"
            :ios-icon="Ios::ArrowRightToLine" :android-icon="Android::Logout" />

        @endif
    </native:column>
</scroll-view>
