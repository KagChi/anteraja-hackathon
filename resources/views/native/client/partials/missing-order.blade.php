@use('App\Icons\Ios')
@use('App\Icons\Android')

{{-- Shown inside a pushed screen when the resi does not belong to the
     signed-in customer, so an unknown link cannot render an empty screen. --}}
<native:column class="w-full items-center gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
    <native:column class="h-12 w-12 items-center justify-center rounded-full bg-theme-surface-container">
        <native:icon :ios="Ios::QuestionmarkCircle" :android="Android::SearchOff" :size="24"
            class="text-theme-secondary" />
    </native:column>
    <native:text class="text-lg font-bold text-theme-on-surface">Pesanan tidak ditemukan</native:text>
    <native:text class="text-center text-sm leading-relaxed text-theme-secondary">
        Resi {{ $resi !== '' ? $resi : '-' }} tidak terdaftar di akun ini. Pilih pesanan lain dari daftar.
    </native:text>
    <native:button variant="primary" label="Lihat Pesanan" @navigate="'/client/orders'"
        :ios-icon="Ios::Receipt" :android-icon="Android::ReceiptLong" />
</native:column>
