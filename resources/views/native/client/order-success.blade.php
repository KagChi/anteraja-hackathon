@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Pesanan Dibuat" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        <native:column class="w-full items-center gap-2 rounded-2xl bg-theme-primary p-6">
            <native:column class="h-14 w-14 items-center justify-center rounded-full bg-theme-surface">
                <native:icon :ios="Ios::CheckmarkSeal" :android="Android::Verified" :size="26"
                    class="text-theme-primary" />
            </native:column>
            <native:text class="text-xl font-bold text-theme-on-primary">Pesanan berhasil dibuat</native:text>
            <native:text class="text-sm text-theme-on-primary">
                Hub mengonfirmasi jadwal ke penerima lewat WhatsApp, lalu kurir berangkat mengantar paket.
            </native:text>
        </native:column>

        <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="w-full items-center justify-between gap-2">
                <native:text class="text-sm text-theme-secondary uppercase">Nomor resi</native:text>
                <native:text class="text-base font-bold text-theme-on-surface select-text">{{ $resi }}</native:text>
            </native:row>

            @if ($receipt !== null)
                <native:divider />

                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary">Status</native:text>
                    <native:text class="text-base text-theme-on-surface">{{ $receipt['status'] }}</native:text>
                </native:row>

                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary">Layanan</native:text>
                    <native:text class="text-base text-theme-on-surface">{{ $receipt['service'] }}</native:text>
                </native:row>

                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary">Jadwal</native:text>
                    <native:text class="flex-1 text-right text-base text-theme-on-surface">
                        {{ $receipt['scheduled_label'] }}
                    </native:text>
                </native:row>

                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary">Penerima</native:text>
                    <native:text class="flex-1 text-right text-base text-theme-on-surface">
                        {{ $receipt['recipient'] }}
                    </native:text>
                </native:row>

                <native:row class="w-full items-center justify-between gap-2">
                    <native:text class="text-sm text-theme-secondary">Total produk</native:text>
                    <native:text class="text-base font-bold text-theme-primary">{{ $receipt['total_label'] }}</native:text>
                </native:row>
            @endif
        </native:column>

        @if ($receipt !== null && count($receipt['items']) > 0)
            <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:text class="text-sm text-theme-secondary uppercase">Isi paket</native:text>

                @foreach ($receipt['items'] as $item)
                    <native:row class="w-full items-center justify-between gap-2" native:key="item-{{ $loop->index }}">
                        <native:text class="flex-1 text-base text-theme-on-surface">
                            {{ $item['quantity'] }}× {{ $item['name'] }}
                        </native:text>
                        <native:text class="text-base text-theme-secondary">{{ $item['line_total_label'] }}</native:text>
                    </native:row>
                @endforeach
            </native:column>
        @endif

        <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-low p-4">
            <native:row class="items-center gap-2">
                <native:icon :ios="Ios::Info" :android="Android::Info" :size="18"
                    class="text-theme-tertiary" />
                <native:text class="text-sm font-bold text-theme-tertiary uppercase">Langkah berikutnya</native:text>
            </native:row>
            <native:text class="text-sm leading-snug text-theme-secondary">
                Buka tab Pesanan untuk melacak status paket. Penerima yang mengonfirmasi jadwal kedatangan lewat
                WhatsApp.
            </native:text>
        </native:column>

        <native:button class="w-full" variant="primary" label="Lihat Pesanan" @tap="openOrders"
            :ios-icon="Ios::ListBullet" :android-icon="Android::List" />

        <native:button class="w-full" variant="secondary" label="Belanja Lagi" @tap="openCatalog"
            :ios-icon="Ios::Bag" :android-icon="Android::Storefront" />
    </native:column>
</scroll-view>
