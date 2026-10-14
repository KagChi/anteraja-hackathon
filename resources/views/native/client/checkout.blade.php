@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Checkout" back />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-4 pb-8">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif (count($lines) === 0)
            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:icon :ios="Ios::Bag" :android="Android::ShoppingCart" :size="26"
                    class="text-theme-secondary" />
                <native:text class="text-lg font-bold text-theme-on-surface">Keranjang masih kosong</native:text>
                <native:text class="text-md text-theme-secondary">
                    Pilih produk beku di katalog, lalu kembali ke halaman ini untuk mengisi data pengantaran.
                </native:text>
                <native:button class="w-full" variant="primary" label="Buka Katalog" @tap="openCatalog"
                    :ios-icon="Ios::ListBullet" :android-icon="Android::List" />
            </native:column>
        @else

            {{-- What is being ordered. --}}
            <native:column class="w-full gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:row class="w-full items-center justify-between">
                    <native:text class="text-sm text-theme-secondary uppercase">Keranjang</native:text>
                    <native:text class="text-sm text-theme-secondary">{{ $cartCount }} item</native:text>
                </native:row>

                @foreach ($lines as $line)
                    <native:column class="w-full gap-2" native:key="line-{{ $line['id'] }}">
                        <native:row class="w-full items-start gap-3">
                            <native:column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-surface-container">
                                <native:icon :ios="Ios::Snowflake" :android="Android::AcUnit" :size="18"
                                    class="text-theme-primary" />
                            </native:column>
                            <native:column class="flex-1 gap-0.5">
                                <native:text class="text-md font-bold text-theme-on-surface">{{ $line['name'] }}</native:text>
                                <native:text class="text-sm text-theme-secondary">
                                    {{ $line['price_label'] }} • {{ $line['weight_label'] }}
                                </native:text>
                            </native:column>
                            <native:text class="text-md font-bold text-theme-on-surface">
                                {{ $line['line_total_label'] }}
                            </native:text>
                        </native:row>

                        <native:row class="w-full items-center gap-2">
                            <native:pressable @tap="decrement({{ $line['id'] }})" a11y-label="Kurangi {{ $line['name'] }}">
                                <native:column class="h-8 w-8 items-center justify-center rounded-lg bg-theme-surface-container">
                                    <native:icon :ios="Ios::Minus" :android="Android::Remove" :size="16"
                                        class="text-theme-on-surface" />
                                </native:column>
                            </native:pressable>

                            <native:text class="text-md font-bold text-theme-on-surface">{{ $line['quantity'] }}</native:text>

                            <native:pressable @tap="increment({{ $line['id'] }})" a11y-label="Tambah {{ $line['name'] }}">
                                <native:column class="h-8 w-8 items-center justify-center rounded-lg bg-theme-surface-container">
                                    <native:icon :ios="Ios::Plus" :android="Android::Add" :size="16"
                                        class="text-theme-on-surface" />
                                </native:column>
                            </native:pressable>

                            <native:pressable class="ml-auto" @tap="removeLine({{ $line['id'] }})"
                                a11y-label="Hapus {{ $line['name'] }} dari keranjang">
                                <native:row class="items-center gap-1 px-2 py-1">
                                    <native:icon :ios="Ios::Xmark" :android="Android::Close" :size="14"
                                        class="text-theme-secondary" />
                                    <native:text class="text-sm text-theme-secondary">Hapus</native:text>
                                </native:row>
                            </native:pressable>
                        </native:row>

                        <native:divider />
                    </native:column>
                @endforeach

                <native:row class="w-full items-center justify-between">
                    <native:text class="text-md text-theme-secondary">Total produk</native:text>
                    <native:text class="text-xl font-bold text-theme-primary">{{ $totalLabel }}</native:text>
                </native:row>
                <native:text class="text-sm text-theme-secondary">
                    Ongkir dihitung hub setelah jadwal dikonfirmasi; total di atas hanya nilai produk.
                </native:text>
            </native:column>

            {{-- Where and when. --}}
            <native:column class="w-full gap-4 rounded-xl bg-theme-surface-container-lowest p-4">
                <native:text class="text-sm text-theme-secondary uppercase">Data pengantaran</native:text>

                <native:outlined-text-input
                    label="Nama penerima"
                    placeholder="Nama orang yang menerima"
                    native:model.blur="recipientName" />

                <native:outlined-text-input
                    label="Nomor HP penerima"
                    placeholder="0812xxxxxxx"
                    keyboard="phone"
                    supporting="Kurir menghubungi nomor ini saat tiba"
                    native:model.blur="recipientPhone" />

                <native:outlined-text-input
                    label="Alamat lengkap"
                    placeholder="Jalan, nomor rumah, patokan"
                    :max-lines="3"
                    native:model.blur="recipientAddress" />

                <native:outlined-text-input
                    label="Area / kota"
                    placeholder="Contoh: Tebet, Jakarta Selatan"
                    native:model.blur="recipientArea" />

                <native:select
                    label="Jenis alamat"
                    native:model.blur="addressType"
                    :options="$typeOptions" />

                <native:select
                    label="Layanan"
                    supporting="Instant Frozen hanya untuk area Jabodetabek"
                    native:model.blur="serviceType"
                    :options="$serviceOptions" />

                <native:select
                    label="Tanggal pengantaran"
                    native:model.blur="scheduleDate"
                    :options="$dateOptions" />

                <native:select
                    label="Jendela waktu"
                    native:model.blur="scheduleWindow"
                    :options="$windowOptions" />

                <native:outlined-text-input
                    label="Catatan untuk kurir"
                    placeholder="Contoh: titip ke sekuriti lobi"
                    supporting="Opsional, maksimal 300 karakter"
                    :max-lines="3"
                    native:model.blur="notes" />
            </native:column>

            @if ($actionError !== null)
                @include('native.partials.action-error', ['message' => $actionError])
            @endif

            <native:button class="w-full" variant="primary"
                :label="$busy ? 'Mengirim pesanan…' : 'Buat Pesanan'"
                @tap="submit" :ios-icon="Ios::CheckmarkSeal" :android-icon="Android::Verified" />
        @endif
    </native:column>
</scroll-view>
