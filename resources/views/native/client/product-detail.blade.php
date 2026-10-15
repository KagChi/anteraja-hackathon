@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Detail Produk" back>
    <native:top-bar-action id="cart" label="Keranjang" @tap="openCart"
        :ios-icon="Ios::Bag" :android-icon="Android::ShoppingCart" />
</native:top-bar>

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif ($product === null)
            <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:icon :ios="Ios::Magnifyingglass" :android="Android::Search" :size="26"
                    class="text-theme-secondary" />
                <native:text class="text-lg font-bold text-theme-on-surface">Produk tidak ditemukan</native:text>
                <native:text class="text-base text-theme-secondary">
                    Produk ini sudah tidak dijual. Silakan pilih produk lain di katalog.
                </native:text>
                <native:button class="w-full" variant="primary" label="Kembali ke katalog" @tap="openCatalog"
                    :ios-icon="Ios::ListBullet" :android-icon="Android::List" />
            </native:column>
        @else

            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:row class="w-full items-start gap-3">
                    <native:column class="h-14 w-14 items-center justify-center rounded-2xl bg-theme-surface-container">
                        <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="24"
                            class="text-theme-primary" />
                    </native:column>
                    <native:column class="flex-1 gap-0.5">
                        <native:text class="text-sm text-theme-secondary uppercase">{{ $product['category'] }}</native:text>
                        <native:text class="text-2xl font-bold text-theme-on-surface">{{ $product['name'] }}</native:text>
                        <native:text class="text-sm text-theme-secondary">
                            {{ $product['weight_label'] }} • dijual per {{ $product['unit'] }}
                        </native:text>
                    </native:column>
                </native:row>

                <native:text class="text-2xl font-bold text-theme-primary">{{ $product['price_label'] }}</native:text>

                <native:divider />

                <native:text class="text-base leading-relaxed text-theme-on-surface">
                    {{ $product['description'] }}
                </native:text>
            </native:column>

            <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-low p-4">
                <native:row class="items-center gap-2">
                    <native:icon :ios="Ios::ThermometerSnowflake" :android="Android::Thermostat" :size="18"
                        class="text-theme-tertiary" />
                    <native:text class="text-sm font-bold text-theme-tertiary uppercase">Catatan penanganan</native:text>
                </native:row>
                <native:text class="text-sm leading-snug text-theme-secondary">
                    {{ $product['handling_notes'] !== '' ? $product['handling_notes'] : 'Dikirim dalam cooler box dengan ice gel.' }}
                </native:text>
            </native:column>

            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:text class="text-sm text-theme-secondary uppercase">Jumlah</native:text>

                <native:row class="w-full items-center gap-3">
                    <native:pressable @tap="decrement" a11y-label="Kurangi jumlah">
                        <native:column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-surface-container">
                            <native:icon :ios="Ios::Minus" :android="Android::Remove" :size="18"
                                class="text-theme-on-surface" />
                        </native:column>
                    </native:pressable>

                    <native:text class="text-2xl font-bold text-theme-on-surface select-text">{{ $quantity }}</native:text>

                    <native:pressable @tap="increment" a11y-label="Tambah jumlah">
                        <native:column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-surface-container">
                            <native:icon :ios="Ios::Plus" :android="Android::Add" :size="18"
                                class="text-theme-on-surface" />
                        </native:column>
                    </native:pressable>

                    {{-- EDGE has no ml-auto: a spacer row pushes the note to the end. --}}
                    <native:row class="flex-1 justify-end">
                        <native:text class="text-sm text-theme-secondary">maks 20</native:text>
                    </native:row>
                </native:row>
            </native:column>

            @if ($actionError !== null)
                @include('native.partials.action-error', ['message' => $actionError])
            @endif

            @if ($justAdded)
                <native:row class="w-full items-center gap-2 rounded-lg bg-theme-tertiary-fixed p-3">
                    <native:icon :ios="Ios::CheckmarkCircle" :android="Android::CheckCircle" :size="18"
                        class="text-theme-on-tertiary-fixed" />
                    <native:text class="flex-1 text-sm text-theme-on-tertiary-fixed">
                        {{ $quantity }} {{ $product['name'] }} masuk keranjang.
                    </native:text>
                </native:row>
            @endif

        @endif
    </native:column>
</scroll-view>

@if ($product !== null)
    <native:column class="w-full gap-2 bg-theme-surface-container-low p-4">
        @if ($cartCount > 0)
            <native:text class="text-sm text-theme-secondary">{{ $cartCount }} item siap dipesan</native:text>
        @endif

        <native:row class="w-full gap-2">
            <native:button class="flex-1" variant="primary" label="Tambah ke Keranjang" @tap="addToCart"
                :ios-icon="Ios::BagBadgePlus" :android-icon="Android::AddShoppingCart" />
            <native:button class="flex-1" variant="secondary" label="Checkout" @tap="openCart"
                :ios-icon="Ios::ArrowRight" :android-icon="Android::ArrowForward" />
        </native:row>
    </native:column>
@endif
