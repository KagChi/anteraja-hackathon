@use('App\Icons\Ios')
@use('App\Icons\Android')

<native:top-bar title="Lapor Masalah" back />

<scroll-view axis="vertical" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-5 p-4 pb-8">

        @if ($error !== null)
            @include('native.partials.state', ['message' => $error])
        @elseif ($form === null)
            <native:column class="w-full items-center gap-3 rounded-xl bg-theme-surface-container-lowest p-5">
                <native:text class="text-lg font-bold text-theme-on-surface">Kendala tidak dapat dilaporkan</native:text>
                <native:text class="text-center text-sm text-theme-secondary">
                    Paket ini tidak berada pada status yang dapat dilaporkan kendalanya.
                </native:text>
                <native:button variant="primary" label="Kembali ke Tugas" @navigate="'/courier/tasks'" />
            </native:column>
        @else

        {{-- Which shipment, and who is reporting. --}}
        <native:column class="w-full gap-2 rounded-xl bg-theme-surface-container-lowest p-4">
            <native:row class="w-full items-center justify-between">
                <native:text class="text-sm text-theme-secondary uppercase tracking-wider">Nomor Resi</native:text>
                <native:badge label="Kendala Lapangan" variant="destructive" />
            </native:row>
            <native:text class="text-lg font-bold text-theme-on-surface tracking-tight">{{ $form['tracking'] }}</native:text>
            <native:row class="w-full gap-4 pt-1">
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Penerima</native:text>
                    <native:text class="text-base font-bold text-theme-on-surface">{{ $form['recipient'] }}</native:text>
                </native:column>
                <native:column class="flex-1 gap-0.5">
                    <native:text class="text-sm text-theme-secondary">Kurir Pelapor</native:text>
                    <native:text class="text-base text-theme-on-surface">{{ $form['reporter'] }}</native:text>
                </native:column>
            </native:row>
        </native:column>

        <native:column class="w-full gap-5">

            {{-- What went wrong. --}}
            <native:select
                label="Jenis Kendala"
                native:model.blur="issueType"
                :options="$typeOptions" />

            {{-- What the hub needs to act on it. --}}
            <native:column class="w-full gap-1">
                <native:outlined-text-input
                    label="Catatan Kendala"
                    supporting="Berikan rincian kondisi paket secara jelas"
                    :max-lines="5"
                    native:model.blur="note" />
                <native:text class="text-sm text-theme-secondary">
                    {{ mb_strlen($note) }}/{{ $form['note_limit'] }}
                </native:text>
            </native:column>

            {{-- Photo of the parcel as found. --}}
            <native:column class="w-full gap-2">
                <native:row class="w-full items-center justify-between">
                    <native:text class="text-base text-theme-on-surface">Bukti Foto Fisik</native:text>
                    @if ($photoPath !== null)
                        <native:text class="text-sm text-theme-tertiary">1 Terlampir</native:text>
                    @endif
                </native:row>

                @if ($photoPath !== null)
                    <native:row class="w-full items-center justify-between gap-3 rounded-xl bg-theme-surface-container-lowest p-4">
                        <native:row class="flex-1 items-center gap-3">
                            <native:image :src="$photoPath" alt="Bukti foto kemasan paket beku"
                                fit="cover" class="w-14 h-14 rounded-lg" />
                            <native:column class="flex-1 gap-0.5">
                                <native:text class="text-base font-bold text-theme-on-surface">{{ $form['photo_name'] }}</native:text>
                                <native:text class="text-sm text-theme-secondary">{{ $form['photo_meta'] }}</native:text>
                            </native:column>
                        </native:row>
                        <native:button variant="secondary" icon="trash" label="Hapus" @tap="clearPhoto"
                            a11y-label="Hapus foto" />
                    </native:row>
                @endif

                <native:button variant="secondary" label="Ambil Foto" @tap="capturePhoto"
                    :ios-icon="Ios::CameraFill" :android-icon="Android::PhotoCamera" />
            </native:column>

            {{-- What happens next. --}}
            <native:row class="w-full items-start gap-2 rounded-xl bg-theme-surface-container-low p-4">
                <native:icon :ios="Ios::InfoCircleFill" :android="Android::Info" :size="20" class="text-theme-primary" />
                <native:text class="flex-1 text-sm leading-snug text-theme-secondary">
                    Laporan ini otomatis memberitahukan tim operasional Hub dan customer service. Paket harap
                    disimpan kembali di kantong tahan suhu.
                </native:text>
            </native:row>

            {{-- Send it. --}}
            @include('native.partials.action-error', ['message' => $actionError])

            @if ($state === 'done')
                <native:button variant="success" label="Laporan Terkirim" @tap="backToTasks"
                    :ios-icon="Ios::CheckmarkCircle" :android-icon="Android::CheckCircle" />
            @else
                <native:button variant="primary" label="Kirim Laporan" @tap="submit"
                    :ios-icon="Ios::PaperplaneFill" :android-icon="Android::Send" />
            @endif

        </native:column>
        @endif
    </native:column>
</scroll-view>
