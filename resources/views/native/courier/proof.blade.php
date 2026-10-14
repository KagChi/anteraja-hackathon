@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Konfirmasi Pengiriman" back />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-5 p-4 pb-6">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif ($form === null)
            <native:column class="w-full items-center gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:text class="text-lg font-bold text-theme-on-surface">Paket tidak dapat dikonfirmasi</native:text>
                <native:text class="text-center text-sm text-theme-secondary">
                    Konfirmasi pengiriman hanya tersedia saat paket sedang diantar.
                </native:text>
                <native:button variant="primary" label="Kembali ke Tugas" @navigate="'/courier/tasks'" />
            </native:column>
        @else

        {{-- Which shipment this proof belongs to. --}}
        <native:column class="w-full gap-1 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="w-full items-center justify-between">
                <native:text class="text-sm text-theme-secondary uppercase tracking-wider">Nomor Resi</native:text>
                <native:badge :label="$form['service']" variant="secondary" />
            </native:row>
            <native:text class="text-lg font-bold text-theme-on-surface select-text">{{ $form['tracking'] }}</native:text>
            <native:row class="items-center gap-1.5 pt-1">
                <native:icon :ios="Ios::PersonFill" :android="Android::Person" :size="18" class="text-theme-on-surface-variant" />
                <native:text class="text-md text-theme-on-surface-variant">
                    Tujuan Penerima: {{ $form['recipient'] }}
                </native:text>
            </native:row>
        </native:column>

        <native:column class="w-full gap-5 rounded-xl bg-theme-surface-container-lowest p-4">

            {{-- Who physically received the parcel. --}}
            <native:column class="w-full gap-2">
                <native:outlined-text-input
                    label="Nama Penerima Fisik"
                    supporting="Pastikan nama sesuai dengan identitas penerima paket di lokasi."
                    placeholder="Masukkan nama orang yang menerima paket"
                    native:model.blur="recipientName" />
            </native:column>

            {{-- Relationship to the recipient. --}}
            <native:column class="w-full gap-2">
                <native:text class="text-md text-theme-on-surface">Hubungan dengan Penerima</native:text>
                @foreach (array_chunk($form['relations'], 2) as $pair)
                    <native:row class="w-full gap-2">
                        @foreach ($pair as $option)
                            <native:pressable class="flex-1" @tap="chooseRelation('{{ $option }}')" a11y-label="{{ $option }}">
                                <native:column class="w-full items-center justify-center rounded-xl p-3 {{ $relation === $option ? 'bg-theme-primary' : 'bg-theme-surface-container-low' }}">
                                    <native:text class="text-md {{ $relation === $option ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">
                                        {{ $option }}
                                    </native:text>
                                </native:column>
                            </native:pressable>
                        @endforeach
                    </native:row>
                @endforeach
            </native:column>

            {{-- The photo is the proof; the native camera is why this screen exists. --}}
            <native:column class="w-full gap-2">
                <native:text class="text-md text-theme-on-surface">Foto Bukti Penyerahan</native:text>
                @if ($photoPath !== null)
                    <native:row class="w-full items-center justify-between gap-3 rounded-xl bg-theme-surface-container-low p-3">
                        <native:row class="flex-1 items-center gap-3">
                            <native:image :src="$photoPath" alt="Bukti serah terima paket beku"
                                fit="cover" class="w-14 h-14 rounded-lg" />
                            <native:column class="flex-1 gap-0.5">
                                <native:text class="text-md text-theme-on-surface">{{ $form['photo_name'] }}</native:text>
                                <native:text class="text-sm text-theme-secondary">{{ $form['photo_meta'] }}</native:text>
                            </native:column>
                        </native:row>
                        <native:button variant="secondary" label="Ambil Ulang" @tap="capturePhoto"
                            :ios-icon="Ios::CameraFill" :android-icon="Android::PhotoCamera" />
                    </native:row>
                @else
                    <native:button variant="secondary" label="Ambil Foto Bukti" @tap="capturePhoto"
                        :ios-icon="Ios::CameraFill" :android-icon="Android::PhotoCamera" />
                    <native:text class="text-sm text-theme-secondary">
                        Ambil foto paket bersama penerima atau di depan pintu rumah/lokasi penyerahan.
                    </native:text>
                @endif
            </native:column>

            <native:column class="w-full gap-2">
                <native:outlined-text-input
                    label="Catatan Tambahan"
                    supporting="Opsional"
                    :placeholder="$form['notes_placeholder']"
                    native:model.blur="notes" />
            </native:column>
        </native:column>

        {{-- Hand it over. --}}
        @include('native.partials.action-error', ['message' => $actionError])

        {{-- The location the proof will carry, and the way out of a refusal. --}}
        <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="w-full items-center justify-between gap-2">
                <native:row class="items-center gap-2">
                    <native:icon :ios="Ios::Location" :android="Android::LocationOn" :size="18"
                        class="text-theme-secondary" />
                    <native:text class="text-sm text-theme-secondary uppercase">Lokasi Serah Terima</native:text>
                </native:row>
                <native:text class="text-sm text-theme-secondary">{{ $fixCount }} titik</native:text>
            </native:row>

            <native:text class="text-sm text-theme-secondary">
                {{ $gpsAccuracy ?? 'Menunggu sinyal GPS perangkat.' }}
            </native:text>
            <native:text class="text-sm leading-snug text-theme-secondary">
                Koordinat dan riwayat sinyal dikirim bersama bukti foto; hub memeriksanya terhadap radius
                titik penerima.
            </native:text>

            @if ($gpsNotice !== '')
                <native:row class="w-full items-center gap-2 rounded-lg bg-theme-tertiary-fixed p-3">
                    <native:icon :ios="Ios::CheckmarkCircle" :android="Android::CheckCircle" :size="16"
                        class="text-theme-on-tertiary-fixed" />
                    <native:text class="flex-1 text-sm text-theme-on-tertiary-fixed">{{ $gpsNotice }}</native:text>
                </native:row>
            @endif

            @if ($actionError !== null)
                <native:button class="w-full" variant="secondary" label="Minta Tinjauan Hub" @tap="requestReview"
                    :ios-icon="Ios::QuestionmarkCircle" :android-icon="Android::HelpOutline" />
            @endif
        </native:column>

        <native:column class="w-full gap-2">
            @if ($state === 'done')
                <native:button variant="success" label="Pengiriman Berhasil Selesai" @tap="backToTasks"
                    :ios-icon="Ios::CheckmarkCircle" :android-icon="Android::CheckCircle" />
            @else
                <native:button variant="primary" label="Selesaikan Pengiriman" @tap="submit"
                    :disabled="trim($recipientName) === ''"
                    :ios-icon="Ios::CheckmarkSeal" :android-icon="Android::TaskAlt" />
            @endif
            <native:text class="text-center text-sm text-theme-secondary">
                Status paket akan langsung diperbarui ke sistem pusat Satria.
            </native:text>
        </native:column>

        @endif
    </native:column>
</scroll-view>
