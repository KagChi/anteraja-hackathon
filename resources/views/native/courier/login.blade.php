@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:column class="w-full h-full items-start justify-center gap-6 p-6 bg-theme-background">

    <native:column class="w-full items-center gap-2">
        <native:column class="h-16 w-16 items-center justify-center rounded-2xl bg-theme-primary">
            <native:icon :ios="Ios::TruckBox" :android="Android::LocalShipping" :size="30" class="text-theme-on-primary" />
        </native:column>
        <native:text class="text-2xl font-bold text-theme-on-surface">Anteraja Kurir</native:text>
        <native:text class="text-base text-theme-secondary">Masuk dengan akun kurir Anda.</native:text>
    </native:column>

    <native:column class="w-full gap-4">
        <native:outlined-text-input
            label="Email"
            placeholder="nama@anteraja.id"
            keyboard="email"
            native:model.blur="email" />

        <native:outlined-text-input
            label="Password"
            placeholder="Password akun"
            keyboard="password"
            secure
            revealable
            native:model.blur="password" />

        @if ($error !== null)
            <native:row class="w-full items-start gap-2 rounded-lg bg-theme-error-container p-3">
                <native:icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::ErrorOutline" :size="18"
                    class="text-theme-on-error-container" />
                <native:text class="flex-1 text-sm leading-snug text-theme-on-error-container">{{ $error }}</native:text>
            </native:row>
        @endif
    </native:column>

    <native:button class="w-full" variant="primary" label="Masuk" @tap="submit"
        :ios-icon="Ios::ArrowRightToLine" :android-icon="Android::Login" />

</native:column>
