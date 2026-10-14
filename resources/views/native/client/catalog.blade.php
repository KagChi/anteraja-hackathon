@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Katalog Frozen" back>
    <native:top-bar-action id="cart" label="Keranjang" @tap="openCheckout"
        :ios-icon="Ios::Bag" :android-icon="Android::ShoppingCart" />
</native:top-bar>

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @else

            <native:column class="w-full gap-1 rounded-xl bg-theme-surface-container-low p-4">
                <native:text class="text-sm text-theme-secondary uppercase">Katalog Produk Beku</native:text>
                <native:text class="text-md text-theme-on-surface">
                    {{ count($products) }} produk tersedia. Harga dan berat diambil dari sistem hub.
                </native:text>
            </native:column>

            @if ($actionError !== null)
                @include('native.partials.action-error', ['message' => $actionError])
            @endif

            @if ($addedName !== '')
                <native:row class="w-full items-center gap-2 rounded-lg bg-theme-tertiary-fixed p-3">
                    <native:icon :ios="Ios::CheckmarkCircle" :android="Android::CheckCircle" :size="18"
                        class="text-theme-on-tertiary-fixed" />
                    <native:text class="flex-1 text-sm text-theme-on-tertiary-fixed">
                        {{ $addedName }} masuk keranjang.
                    </native:text>
                </native:row>
            @endif

            @forelse ($products as $product)
                <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4"
                    native:key="product-{{ $product['id'] }}">

                    <native:pressable class="w-full" @navigate="'/client/catalog/' . $product['slug']"
                        a11y-label="Lihat detail {{ $product['name'] }}">
                        <native:column class="w-full gap-2">
                            <native:row class="w-full items-start gap-3">
                                <native:column class="h-12 w-12 items-center justify-center rounded-xl bg-theme-surface-container">
                                    <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="20"
                                        class="text-theme-primary" />
                                </native:column>
                                <native:column class="flex-1 gap-0.5">
                                    <native:text class="text-sm text-theme-secondary uppercase">
                                        {{ $product['category'] }}
                                    </native:text>
                                    <native:text class="text-lg font-bold text-theme-on-surface">
                                        {{ $product['name'] }}
                                    </native:text>
                                    <native:text class="text-sm text-theme-secondary">
                                        {{ $product['weight_label'] }} • {{ $product['unit'] }}
                                    </native:text>
                                </native:column>
                                <native:text class="text-md font-bold text-theme-primary">
                                    {{ $product['price_label'] }}
                                </native:text>
                            </native:row>

                            <native:text class="text-sm leading-snug text-theme-secondary">
                                {{ $product['description'] }}
                            </native:text>
                        </native:column>
                    </native:pressable>

                    <native:row class="w-full items-center justify-between gap-3 pt-1">
                        <native:pressable @navigate="'/client/catalog/' . $product['slug']"
                            a11y-label="Atur jumlah {{ $product['name'] }}">
                            <native:row class="items-center gap-1">
                                <native:text class="text-sm text-theme-secondary">Atur jumlah</native:text>
                                <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="14"
                                    class="text-theme-secondary" />
                            </native:row>
                        </native:pressable>

                        <native:pressable @tap="addToCart({{ $product['id'] }})"
                            a11y-label="Tambah {{ $product['name'] }} ke keranjang">
                            <native:row class="items-center gap-1.5 rounded-lg bg-theme-primary px-3 py-2">
                                <native:icon :ios="Ios::BagBadgePlus" :android="Android::AddShoppingCart" :size="16"
                                    class="text-theme-on-primary" />
                                <native:text class="text-sm font-bold text-theme-on-primary">Tambah</native:text>
                            </native:row>
                        </native:pressable>
                    </native:row>
                </native:column>
            @empty
                <native:text class="text-md text-theme-secondary">Belum ada produk yang tersedia.</native:text>
            @endforelse

        @endif
    </native:column>
</scroll-view>

@if ($cartCount > 0)
    {{-- Sticky checkout bar: the basket is the whole point of this screen. --}}
    <native:row class="w-full items-center justify-between gap-3 bg-theme-surface-container-low p-4">
        <native:column class="gap-0.5">
            <native:text class="text-sm text-theme-secondary">{{ $cartCount }} item di keranjang</native:text>
            <native:text class="text-md font-bold text-theme-on-surface">Siap dipesan</native:text>
        </native:column>

        <native:row class="items-center gap-2">
            <native:pressable @tap="clearCart" a11y-label="Kosongkan keranjang">
                <native:row class="items-center gap-1 rounded-lg bg-theme-surface-container-highest px-3 py-2">
                    <native:icon :ios="Ios::Xmark" :android="Android::Close" :size="16"
                        class="text-theme-secondary" />
                    <native:text class="text-sm text-theme-secondary">Kosongkan</native:text>
                </native:row>
            </native:pressable>

            <native:pressable @tap="openCheckout" a11y-label="Lanjut ke checkout">
                <native:row class="items-center gap-1.5 rounded-lg bg-theme-primary px-4 py-2">
                    <native:icon :ios="Ios::ArrowRight" :android="Android::ArrowForward" :size="16"
                        class="text-theme-on-primary" />
                    <native:text class="text-md font-bold text-theme-on-primary">Checkout</native:text>
                </native:row>
            </native:pressable>
        </native:row>
    </native:row>
@endif
