@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Anteraja Frozen" display-mode="large" />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        {{-- What the service promises, in one card. --}}
        <native:column class="w-full gap-3 rounded-2xl bg-theme-primary p-5">
            <native:row class="items-center gap-2">
                <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="18"
                    class="text-theme-on-primary" />
                <native:text class="text-sm text-theme-on-primary uppercase">Rantai dingin -18°C</native:text>
            </native:row>

            <native:text class="text-2xl font-bold leading-snug text-theme-on-primary">
                Kiriman beku Anda sampai utuh, dingin, dan tercatat
            </native:text>

            <native:text class="text-sm leading-snug text-theme-on-primary">
                Pesan produk beku, pilih jadwal antar, dan pantau kurir sampai paket diterima.
            </native:text>

            <native:row class="w-full gap-2 pt-1">
                <native:pressable class="flex-1" @navigate="'/client/catalog'" a11y-label="Lihat katalog produk">
                    <native:row class="items-center justify-center gap-2 rounded-xl bg-theme-surface px-4 py-3">
                        <native:icon :ios="Ios::Bag" :android="Android::ShoppingCart" :size="18"
                            class="text-theme-primary" />
                        <native:text class="text-base font-bold text-theme-primary">Lihat Katalog</native:text>
                    </native:row>
                </native:pressable>

                @if ($signedIn)
                    <native:pressable class="flex-1" @navigate="'/client/orders'" a11y-label="Lihat pesanan saya">
                        <native:row class="items-center justify-center gap-2 rounded-xl bg-theme-primary-fixed px-4 py-3">
                            <native:icon :ios="Ios::ListBullet" :android="Android::List" :size="18"
                                class="text-theme-on-primary-fixed" />
                            <native:text class="text-base font-bold text-theme-on-primary-fixed">Pesanan Saya</native:text>
                        </native:row>
                    </native:pressable>
                @else
                    <native:pressable class="flex-1" @navigate="'/client/register'" a11y-label="Buat akun baru">
                        <native:row class="items-center justify-center gap-2 rounded-xl bg-theme-primary-fixed px-4 py-3">
                            <native:icon :ios="Ios::PersonBadgePlus" :android="Android::PersonAdd" :size="18"
                                class="text-theme-on-primary-fixed" />
                            <native:text class="text-base font-bold text-theme-on-primary-fixed">Daftar</native:text>
                        </native:row>
                    </native:pressable>
                @endif
            </native:row>
        </native:column>

        {{-- Basket summary, shown only once there is something in it. --}}
        @if ($cartCount > 0)
            <native:pressable class="w-full" @navigate="'/client/checkout'" a11y-label="Lanjut ke checkout">
                <native:row class="w-full items-center justify-between gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                    <native:row class="items-center gap-3">
                        <native:icon :ios="Ios::BagBadgePlus" :android="Android::AddShoppingCart" :size="20"
                            class="text-theme-primary" />
                        <native:column class="gap-0.5">
                            <native:text class="text-base font-bold text-theme-on-surface">Keranjang Anda</native:text>
                            <native:text class="text-sm text-theme-secondary">{{ $cartCount }} item siap dipesan</native:text>
                        </native:column>
                    </native:row>
                    <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="18"
                        class="text-theme-primary" />
                </native:row>
            </native:pressable>
        @endif

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @endif

        {{-- Why the cold chain is different. --}}
        <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:text class="text-sm text-theme-secondary uppercase">Kenapa Anteraja Frozen</native:text>

            <native:row class="w-full items-start gap-3">
                <native:icon :ios="Ios::ThermometerSnowflake" :android="Android::Thermostat" :size="20"
                    class="text-theme-tertiary" />
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-base font-bold text-theme-on-surface">Suhu dicatat sebelum berangkat</native:text>
                    <native:text class="text-sm text-theme-secondary">
                        Kurir memeriksa kondisi paket dan mencatat suhunya di setiap keberangkatan.
                    </native:text>
                </native:column>
            </native:row>

            <native:row class="w-full items-start gap-3">
                <native:icon :ios="Ios::Location" :android="Android::LocationOn" :size="20"
                    class="text-theme-tertiary" />
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-base font-bold text-theme-on-surface">Serah terima tervalidasi</native:text>
                    <native:text class="text-sm text-theme-secondary">
                        Lokasi kurir diperiksa di titik penerima sebelum bukti pengantaran diterima.
                    </native:text>
                </native:column>
            </native:row>

            <native:row class="w-full items-start gap-3">
                <native:icon :ios="Ios::SquareAndPencil" :android="Android::EditNote" :size="20"
                    class="text-theme-tertiary" />
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-base font-bold text-theme-on-surface">Jadwal yang bisa Anda pilih</native:text>
                    <native:text class="text-sm text-theme-secondary">
                        Tentukan sendiri jendela antar supaya paket tidak menunggu di teras.
                    </native:text>
                </native:column>
            </native:row>
        </native:column>

        {{-- The catalog's first four products, straight from the API. --}}
        <native:column class="w-full gap-3">
            <native:row class="w-full items-center justify-between">
                <native:text class="text-lg font-bold text-theme-on-surface">Produk Populer</native:text>
                <native:pressable @navigate="'/client/catalog'" a11y-label="Lihat semua produk">
                    <native:row class="items-center gap-1">
                        <native:text class="text-base font-bold text-theme-primary">Semua</native:text>
                        <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="16"
                            class="text-theme-primary" />
                    </native:row>
                </native:pressable>
            </native:row>

            @forelse ($featured as $product)
                <native:pressable class="w-full" native:key="featured-{{ $product['id'] }}"
                    @navigate="'/client/catalog/' . $product['slug']"
                    a11y-label="Lihat {{ $product['name'] }}">
                    <native:row class="w-full items-center gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                        <native:column class="h-12 w-12 items-center justify-center rounded-xl bg-theme-surface-container">
                            <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="20"
                                class="text-theme-primary" />
                        </native:column>
                        <native:column class="flex-1 gap-0.5">
                            <native:text class="text-sm text-theme-secondary uppercase">{{ $product['category'] }}</native:text>
                            <native:text class="text-base font-bold text-theme-on-surface">{{ $product['name'] }}</native:text>
                            <native:text class="text-sm text-theme-secondary">{{ $product['weight_label'] }}</native:text>
                        </native:column>
                        <native:text class="text-base font-bold text-theme-primary">{{ $product['price_label'] }}</native:text>
                    </native:row>
                </native:pressable>
            @empty
                <native:text class="text-base text-theme-secondary">
                    Katalog belum tersedia. Coba lagi sebentar lagi.
                </native:text>
            @endforelse
        </native:column>

        {{-- Sign in, or the support channel. --}}
        @if (! $signedIn)
            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-low p-4">
                <native:text class="text-base font-bold text-theme-on-surface">Sudah punya akun?</native:text>
                <native:text class="text-sm text-theme-secondary">
                    Masuk untuk melihat pesanan dan konfirmasi jadwal pengantaran Anda.
                </native:text>
                <native:button class="w-full" variant="primary" label="Masuk" @tap="goToLogin"
                    :ios-icon="Ios::ArrowRightToLine" :android-icon="Android::Login" />
            </native:column>
        @endif
    </native:column>
</scroll-view>
