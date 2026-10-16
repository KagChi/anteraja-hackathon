import { type FormEvent, useEffect, useMemo, useState } from "react";
import { LocationPicker, type PickedPoint } from "@/components/LocationPicker";
import { MaterialIcon } from "@/components/MaterialIcon";
import { ActionError } from "../../components/ActionError";
import { SelectField, TextAreaField, TextField } from "../../components/Field";
import { ScreenLoading, StateCard } from "../../components/StateCard";
import { clientApi, firstError } from "../../lib/api";
import { clearCart, setQuantity } from "../../lib/cart";
import { rupiah } from "../../lib/format";
import { putReceipt } from "../../lib/receipt";
import { useRouter } from "../../lib/router";
import { isSignedIn } from "../../lib/token";
import { useCart } from "../../lib/useCart";
import { useLoad } from "../../lib/useLoad";

const WINDOWS = ["09:00-11:00", "11:00-13:00", "13:00-15:00", "15:00-17:00", "17:00-19:00"];

const ADDRESS_TYPES = [
    { value: "RESIDENTIAL", label: "Rumah / Residensial" },
    { value: "COMMERCIAL", label: "Kantor / Komersial" }
];

const JAKARTA = "Asia/Jakarta";

/** Today in Jakarta, which is the clock the delivery windows are on. */
function jakartaToday(): string {
    return new Intl.DateTimeFormat("en-CA", { timeZone: JAKARTA }).format(new Date());
}

function addDays(iso: string, days: number): string {
    const [year, month, day] = iso.split("-").map(Number);
    const base = new Date(Date.UTC(year, month - 1, day, 12));
    base.setUTCDate(base.getUTCDate() + days);

    return new Intl.DateTimeFormat("en-CA", { timeZone: "UTC" }).format(base);
}

/** Smallest lead a delivery window may have, matching the hub's two-hour rule. */
const MIN_LEAD_MINUTES = 120;

/** Minutes since midnight in Jakarta, the clock the delivery windows run on. */
function jakartaNowMinutes(): number {
    const parts = new Intl.DateTimeFormat("en-GB", {
        timeZone: JAKARTA,
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23"
    }).formatToParts(new Date());
    const hour = Number(parts.find((part) => part.type === "hour")?.value ?? "0");
    const minute = Number(parts.find((part) => part.type === "minute")?.value ?? "0");

    return hour * 60 + minute;
}

function slotStartMinutes(slot: string): number {
    const [hour, minute] = slot.split("-")[0].split(":").map(Number);

    return hour * 60 + minute;
}

/** Windows still bookable on a date: today drops the ones inside the lead time. */
function availableWindows(date: string): string[] {
    const today = jakartaToday();

    if (date > today) {
        return WINDOWS;
    }

    if (date < today) {
        return [];
    }

    const cutoff = jakartaNowMinutes() + MIN_LEAD_MINUTES;

    return WINDOWS.filter((slot) => slotStartMinutes(slot) >= cutoff);
}

/** The first day that still has a bookable window, which is today unless it is late. */
function defaultScheduleDate(): string {
    const today = jakartaToday();

    return availableWindows(today).length > 0 ? today : addDays(today, 1);
}

function dayLabel(iso: string, offset: number): string {
    const [year, month, day] = iso.split("-").map(Number);
    const date = new Date(Date.UTC(year, month - 1, day, 12));
    const weekday = new Intl.DateTimeFormat("id-ID", { weekday: "long", timeZone: "UTC" }).format(date);
    const short = new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", timeZone: "UTC" }).format(date);

    if (offset === 0) {
        return `Hari Ini, ${short}`;
    }

    return `${offset === 1 ? "Besok" : weekday}, ${short}`;
}

/**
 * Checkout: what is being sent, when it should arrive, and to whom.
 *
 * The customer is the sender, so the address block belongs to the recipient and
 * starts blank. What is typed here becomes the parcel's destination, the point
 * the courier's geofence is measured against, and the number the delivery
 * updates go to.
 */
export default function Checkout() {
    const { navigate } = useRouter();
    const { cart } = useCart();
    const { data, error, loading, reload } = useLoad(() => clientApi.catalog.list(), "client-catalog");

    const [recipientName, setRecipientName] = useState("");
    const [recipientPhone, setRecipientPhone] = useState("");
    const [recipientAddress, setRecipientAddress] = useState("");
    const [recipientArea, setRecipientArea] = useState("");
    const [addressType, setAddressType] = useState("RESIDENTIAL");
    const [scheduleDate, setScheduleDate] = useState(defaultScheduleDate);
    const [scheduleWindow, setScheduleWindow] = useState(() => availableWindows(defaultScheduleDate())[0] ?? "");
    const [notes, setNotes] = useState("");
    const [pin, setPin] = useState<PickedPoint | null>(null);
    const [pinNotice, setPinNotice] = useState("");
    const [actionError, setActionError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!isSignedIn("client")) {
            navigate("/client/register?next=/client/checkout", { replace: true });
        }
    }, [navigate]);

    // Picking a day must not leave a window that day can no longer take.
    useEffect(() => {
        const windows = availableWindows(scheduleDate);

        if (!windows.includes(scheduleWindow)) {
            setScheduleWindow(windows[0] ?? "");
        }
    }, [scheduleDate, scheduleWindow]);

    const products = useMemo(() => data?.data ?? [], [data]);

    // Anything the catalog no longer offers is dropped from the basket, so the
    // total on this screen always matches what can actually be ordered. The
    // catalog has to have loaded first: while data is null `products` is empty,
    // and pruning against it would wipe a perfectly good basket on mount.
    useEffect(() => {
        if (data === null) {
            return;
        }

        for (const key of Object.keys(cart)) {
            const id = Number(key);

            if (!products.some((product) => product.id === id)) {
                setQuantity(id, 0);
            }
        }
    }, [cart, data, products]);

    const lines = useMemo(
        () =>
            products
                .filter((product) => (cart[product.id] ?? 0) > 0)
                .map((product) => ({
                    product,
                    quantity: cart[product.id] ?? 0,
                    lineTotal: product.price_idr * (cart[product.id] ?? 0)
                })),
        [cart, products]
    );

    const total = lines.reduce((sum, line) => sum + line.lineTotal, 0);
    const itemCount = lines.reduce((sum, line) => sum + line.quantity, 0);
    const dateOptions = Array.from({ length: 5 }, (_, index) => {
        const iso = addDays(jakartaToday(), index);

        return { value: iso, label: dayLabel(iso, index) };
    });
    const windowOptions = availableWindows(scheduleDate);

    async function submit(event: FormEvent) {
        event.preventDefault();

        if (busy) {
            return;
        }

        setActionError(null);

        if (lines.length === 0) {
            setActionError("Keranjang masih kosong. Tambahkan produk dulu.");
            return;
        }

        if (recipientName.trim() === "" || recipientPhone.trim() === "" || recipientAddress.trim() === "") {
            setActionError("Nama penerima, nomor HP, dan alamat wajib diisi.");
            return;
        }

        if (pin === null) {
            setActionError("Pin titik lokasi penerima dulu sebelum membuat pesanan.");
            return;
        }

        const [windowStart, windowEnd] = scheduleWindow.split("-");
        setBusy(true);

        try {
            const response = await clientApi.orders.create({
                recipient: {
                    name: recipientName.trim(),
                    phone: recipientPhone.trim(),
                    address: recipientAddress.trim(),
                    area: recipientArea.trim() === "" ? null : recipientArea.trim(),
                    address_type: addressType,
                    latitude: pin.latitude,
                    longitude: pin.longitude
                },
                service_type: "INSTANT_FROZEN",
                scheduled_date: scheduleDate,
                window_start: windowStart,
                window_end: windowEnd,
                handling_instructions: notes.trim() === "" ? null : notes.trim(),
                items: lines.map((line) => ({ product_id: line.product.id, quantity: line.quantity }))
            });

            const resi = response.data.tracking_number;

            if (resi === "") {
                setActionError("Pesanan dibuat, tetapi nomor resi tidak diterima. Cek tab Pesanan.");
                return;
            }

            putReceipt(response.data);
            clearCart();
            navigate(`/client/order-success?resi=${encodeURIComponent(resi)}`, { replace: true });
        } catch (failure) {
            setActionError(firstError(failure));
        } finally {
            setBusy(false);
        }
    }

    if (error !== null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <StateCard message={error} onRetry={reload} />
            </div>
        );
    }

    // The catalog is what turns the basket into lines; until it lands the
    // basket is not empty, it is simply not known, so neither card is right yet.
    if (loading && data === null) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <ScreenLoading label="Memuat katalog…" />
            </div>
        );
    }

    if (lines.length === 0) {
        return (
            <div className="flex w-full flex-col gap-space-lg p-margin">
                <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-xl">
                    <MaterialIcon name="shopping_cart" className="text-[26px] text-secondary" />
                    <span className="font-headline-md text-headline-md text-on-surface">Keranjang masih kosong</span>
                    <span className="font-body-md text-body-md text-secondary">
                        Pilih produk beku di katalog, lalu kembali ke halaman ini untuk mengisi data pengantaran.
                    </span>
                    <button
                        type="button"
                        onClick={() => navigate("/client/catalog")}
                        className="mt-space-sm inline-flex h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-space-lg font-label-md text-label-md text-on-primary"
                    >
                        <MaterialIcon name="list" className="text-[18px]" />
                        Buka Katalog
                    </button>
                </section>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="flex w-full flex-col gap-space-lg p-margin pb-space-xl">
            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <div className="flex w-full items-center justify-between">
                    <span className="font-label-sm text-label-sm text-secondary uppercase">Keranjang</span>
                    <span className="font-body-sm text-body-sm text-secondary">{itemCount} item</span>
                </div>

                {lines.map((line) => (
                    <div
                        key={line.product.id}
                        className="flex w-full flex-col gap-space-sm border-b border-outline-variant pb-space-md last:border-b-0"
                    >
                        <div className="flex w-full items-start gap-space-md">
                            <img
                                src={line.product.image_url ?? "/images/product-placeholder.svg"}
                                alt=""
                                className="h-10 w-10 rounded-lg bg-surface-container object-contain"
                            />
                            <span className="flex flex-1 flex-col gap-0.5">
                                <span className="font-headline-sm text-headline-sm text-on-surface">
                                    {line.product.name}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">
                                    {line.product.price_label} • {line.product.weight_label}
                                </span>
                            </span>
                            <span className="font-headline-sm text-headline-sm text-on-surface">
                                {rupiah(line.lineTotal)}
                            </span>
                        </div>

                        <div className="flex w-full items-center gap-space-sm">
                            <button
                                type="button"
                                aria-label={`Kurangi ${line.product.name}`}
                                onClick={() => setQuantity(line.product.id, line.quantity - 1)}
                                className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-container"
                            >
                                <MaterialIcon name="remove" className="text-[16px] text-on-surface" />
                            </button>

                            <span className="font-headline-sm text-headline-sm text-on-surface">{line.quantity}</span>

                            <button
                                type="button"
                                aria-label={`Tambah ${line.product.name}`}
                                onClick={() => setQuantity(line.product.id, line.quantity + 1)}
                                className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-container"
                            >
                                <MaterialIcon name="add" className="text-[16px] text-on-surface" />
                            </button>

                            <button
                                type="button"
                                onClick={() => setQuantity(line.product.id, 0)}
                                className="ml-auto flex items-center gap-1 px-2 py-1 font-body-sm text-body-sm text-secondary"
                            >
                                <MaterialIcon name="close" className="text-[14px]" />
                                Hapus
                            </button>
                        </div>
                    </div>
                ))}

                <div className="flex w-full items-center justify-between">
                    <span className="font-body-md text-body-md text-secondary">Total produk</span>
                    <span className="font-headline-md text-headline-md text-primary">{rupiah(total)}</span>
                </div>
                <span className="font-body-sm text-body-sm text-secondary">
                    Ongkir dihitung hub setelah jadwal dikonfirmasi; total di atas hanya nilai produk.
                </span>
            </section>

            <section className="flex w-full flex-col gap-space-lg rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Data penerima</span>
                <span className="font-body-sm text-body-sm leading-snug text-secondary">
                    Paket dikirim ke orang yang Anda isi di sini. Update pengantaran dikirim ke nomor penerima lewat
                    WhatsApp.
                </span>

                <TextField
                    label="Nama penerima"
                    value={recipientName}
                    onChange={setRecipientName}
                    placeholder="Nama orang yang menerima"
                />
                <TextField
                    label="Nomor HP penerima"
                    value={recipientPhone}
                    onChange={setRecipientPhone}
                    placeholder="0812xxxxxxx"
                    type="tel"
                    inputMode="tel"
                    supporting="Kurir menghubungi nomor ini saat tiba"
                />
                <TextAreaField
                    label="Alamat lengkap"
                    value={recipientAddress}
                    onChange={setRecipientAddress}
                    placeholder="Jalan, nomor rumah, patokan"
                />
                <TextField
                    label="Area / kota"
                    value={recipientArea}
                    onChange={setRecipientArea}
                    placeholder="Contoh: Tebet, Jakarta Selatan"
                />
                <SelectField
                    label="Jenis alamat"
                    value={addressType}
                    onChange={setAddressType}
                    options={ADDRESS_TYPES}
                />
                <SelectField
                    label="Tanggal pengantaran"
                    value={scheduleDate}
                    onChange={setScheduleDate}
                    options={dateOptions}
                />
                <SelectField
                    label="Jendela waktu"
                    value={scheduleWindow}
                    onChange={setScheduleWindow}
                    options={windowOptions.map((slot) => ({ value: slot, label: slot }))}
                />
                <TextAreaField
                    label="Catatan untuk kurir"
                    value={notes}
                    onChange={setNotes}
                    placeholder="Contoh: titip ke sekuriti lobi"
                    supporting="Opsional, maksimal 300 karakter"
                />
            </section>

            <section className="flex w-full flex-col gap-space-md rounded-xl bg-surface-container-lowest p-space-lg">
                <span className="font-label-sm text-label-sm text-secondary uppercase">Titik lokasi penerima</span>
                <span className="font-body-sm text-body-sm leading-snug text-secondary">
                    Geser pin di peta ke alamat penerima. Kurir memakai titik ini untuk memastikan paket tiba di alamat
                    yang benar.
                </span>

                <LocationPicker
                    value={pin}
                    onChange={(point) => {
                        setPin(point);
                        setPinNotice("");
                    }}
                    onResolvedAddress={(address, area) => {
                        setRecipientAddress((current) => (current.trim() === "" ? address : current));

                        if (area !== null) {
                            setRecipientArea((current) => (current.trim() === "" ? area : current));
                        }
                    }}
                    heightClass="h-64"
                    hint="Klik peta atau geser pin ke alamat penerima."
                />

                <div className="flex w-full items-center gap-space-md rounded-lg bg-surface-container p-space-md">
                    <MaterialIcon name="location_on" className="text-[20px] text-primary" />
                    <span className="flex flex-1 flex-col gap-0.5">
                        {pin === null ? (
                            <>
                                <span className="font-headline-sm text-headline-sm text-secondary">Belum dipilih</span>
                                <span className="font-body-sm text-body-sm text-secondary">
                                    Pilih titik penerima di peta sebelum membuat pesanan.
                                </span>
                            </>
                        ) : (
                            <>
                                <span className="font-headline-sm text-headline-sm text-on-surface">
                                    {pin.latitude.toFixed(5)}, {pin.longitude.toFixed(5)}
                                </span>
                                <span className="font-body-sm text-body-sm text-secondary">Titik penerima siap.</span>
                            </>
                        )}
                    </span>
                </div>

                {pinNotice === "" ? null : (
                    <span className="font-body-sm text-body-sm text-secondary">{pinNotice}</span>
                )}
            </section>

            <ActionError message={actionError} />

            <button
                type="submit"
                disabled={busy}
                className="inline-flex h-12 w-full items-center justify-center gap-1.5 rounded-xl bg-primary px-space-lg font-label-md text-label-md text-on-primary transition-colors hover:bg-primary-container disabled:opacity-60"
            >
                <MaterialIcon name="verified" className="text-[20px]" />
                {busy ? "Mengirim pesanan…" : "Buat Pesanan"}
            </button>
        </form>
    );
}
