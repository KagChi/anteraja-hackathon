@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Buat Akun" back />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        <native:column class="w-full items-center gap-2 py-4">
            <native:column class="h-14 w-14 items-center justify-center rounded-2xl bg-theme-primary">
                <native:icon :ios="Ios::PersonBadgePlus" :android="Android::PersonAdd" :size="26"
                    class="text-theme-on-primary" />
            </native:column>
            <native:text class="text-2xl font-bold text-theme-on-surface">Daftar Anteraja Frozen</native:text>
            <native:text class="text-base text-theme-secondary">
                Akun dipakai untuk memesan, mengonfirmasi jadwal, dan melacak kiriman beku Anda.
            </native:text>
        </native:column>

        <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:outlined-text-input
                label="Nama lengkap"
                placeholder="Nama penerima"
                native:model.blur="name" />

            <native:outlined-text-input
                label="Nomor HP"
                placeholder="0812xxxxxxx"
                keyboard="phone"
                supporting="Nomor ini menjadi ID akun Anda"
                native:model.blur="phone" />

            <native:outlined-text-input
                label="Password"
                placeholder="Minimal 8 karakter"
                keyboard="password"
                secure
                revealable
                native:model.blur="password" />

            <native:outlined-text-input
                label="Ulangi password"
                placeholder="Ketik ulang password"
                keyboard="password"
                secure
                revealable
                native:model.blur="passwordConfirmation" />
        </native:column>

        <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:text class="text-sm text-theme-secondary uppercase">Alamat pengantaran</native:text>

            <native:outlined-text-input
                label="Alamat lengkap"
                placeholder="Jalan, nomor rumah, kelurahan"
                :max-lines="3"
                native:model.blur="address" />

            <native:outlined-text-input
                label="Area / kota"
                placeholder="Contoh: Tebet, Jakarta Selatan"
                native:model.blur="area" />

            <native:select
                label="Jenis alamat"
                native:model.blur="addressType"
                :options="$typeOptions" />
        </native:column>

        @if ($error !== null)
            <native:row class="w-full items-start gap-2 rounded-lg bg-theme-error-container p-3">
                <native:icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::ErrorOutline" :size="18"
                    class="text-theme-on-error-container" />
                <native:text class="flex-1 text-sm leading-snug text-theme-on-error-container">{{ $error }}</native:text>
            </native:row>
        @endif

        <native:button class="w-full" variant="primary" :label="$busy ? 'Mendaftarkan…' : 'Daftar dan Lanjut Checkout'"
            @tap="submit" :ios-icon="Ios::Checkmark" :android-icon="Android::Check" />

        <native:column class="w-full items-center gap-2">
            <native:text class="text-sm text-theme-secondary">Sudah punya akun?</native:text>
            <native:button class="w-full" variant="secondary" label="Masuk" @tap="goToLogin"
                :ios-icon="Ios::ArrowRightToLine" :android-icon="Android::Login" />
        </native:column>
    </native:column>
</scroll-view>
