/**
 * The blog, kept in the repository as typed content.
 *
 * Five articles are enough to show the section working without standing up a
 * CMS: the index and the detail page read the same module, so a new post is one
 * entry here.
 */

export interface PostSection {
    heading?: string;
    paragraphs?: string[];
    bullets?: string[];
}

export interface Post {
    slug: string;
    title: string;
    excerpt: string;
    category: string;
    readMinutes: number;
    publishedAt: string;
    author: string;
    /** Tailwind gradient for the card art; no image asset needed. */
    accent: string;
    icon: string;
    sections: PostSection[];
}

export const POSTS: Post[] = [
    {
        slug: "cara-menjaga-suhu-frozen-selama-pengiriman",
        title: "Cara menjaga suhu frozen selama pengiriman",
        excerpt:
            "Rantai dingin putus bukan karena freezernya mati, tapi karena celah kecil di antara dua titik. Ini urutan yang kami pakai.",
        category: "Rantai Dingin",
        readMinutes: 5,
        publishedAt: "2026-09-18",
        author: "Tim Operasional Anteraja Frozen",
        accent: "from-sky-500/90 via-cyan-500/80 to-emerald-400/80",
        icon: "ac_unit",
        sections: [
            {
                paragraphs: [
                    "Produk beku tidak rusak dalam satu momen. Ia rusak perlahan setiap kali suhunya naik melewati batas aman, lalu turun kembali. Karena itu penanganan frozen bukan soal satu freezernya bagus, melainkan soal menjaga setiap perpindahan tetap singkat.",
                    "Di Anteraja Frozen, urutannya selalu sama: barang dikeluarkan dari freezer sesaat sebelum kurir berangkat, dimasukkan ke cooler box bersama ice gel, dan suhu dicatat di titik itu."
                ]
            },
            {
                heading: "Empat titik yang paling sering bocor",
                bullets: [
                    "Perpindahan dari freezer penjual ke cooler box tanpa ice gel yang cukup.",
                    "Cooler box dibuka berulang kali selama penyortiran di hub.",
                    "Kurir mengantar lebih dari satu pesanan dalam satu perjalanan.",
                    "Paket dititipkan ke sekuriti tanpa pemberitahuan, lalu menunggu berjam-jam."
                ]
            },
            {
                heading: "Yang bisa Anda lakukan sebagai pembeli",
                paragraphs: [
                    "Pilih jendela antar saat Anda benar-benar ada di lokasi. Jika harus dititipkan, tulis instruksi dengan jelas di catatan penanganan supaya kurir tahu siapa yang menerima dan di mana paket boleh diletakkan.",
                    "Terakhir, periksa kemasan saat diterima. Kemasan yang basah atau es yang mencair di sudut box adalah sinyal bahwa perjalanan paket lebih lama dari yang seharusnya."
                ]
            }
        ]
    },
    {
        slug: "mengapa-bukti-serah-terima-harus-tervalidasi",
        title: "Mengapa bukti serah terima harus tervalidasi",
        excerpt:
            "Foto paket di depan pintu memang meyakinkan, tetapi foto itu tidak membuktikan kurir benar-benar berada di sana. Validasi lokasi menutup celah itu.",
        category: "Integritas Pengiriman",
        readMinutes: 4,
        publishedAt: "2026-09-26",
        author: "Tim Integritas Pengiriman",
        accent: "from-fuchsia-500/90 via-pink-500/80 to-rose-400/80",
        icon: "verified_user",
        sections: [
            {
                paragraphs: [
                    "Bukti pengantaran punya dua pertanyaan yang harus dijawab sekaligus: paketnya benar diserahkan, dan penyerahannya terjadi di alamat yang benar. Foto hanya menjawab pertanyaan pertama.",
                    "Karena itu setiap serah terima di aplikasi kurir membawa sinyal lokasi perangkat: koordinat, akurasi, jam perangkat, dan beberapa pembacaan terakhir."
                ]
            },
            {
                heading: "Yang diperiksa server",
                bullets: [
                    "Akurasi sinyal: nilai di bawah satu meter adalah ciri lokasi simulasi.",
                    "Koordinat beku: banyak pembacaan identik tanpa variasi sinyal.",
                    "Perpindahan mustahil: lompatan jarak yang tidak mungkin ditempuh dalam waktunya.",
                    "Radius titik penerima: apakah kurir benar berada di alamat tujuan."
                ]
            },
            {
                heading: "Kalau sistem menolak, kurir tidak berhenti",
                paragraphs: [
                    "Penolakan otomatis selalu bisa ditinjau manusia. Kurir mengirim permintaan tinjauan, hub memeriksa bukti dan koordinat, lalu memutuskan dengan alasan tertulis. Insidennya tetap tercatat meski pengiriman dilanjutkan."
                ]
            }
        ]
    },
    {
        slug: "cara-memilih-jadwal-antar-untuk-stok-mingguan",
        title: "Cara memilih jadwal antar untuk stok mingguan",
        excerpt:
            "Jendela antar yang tepat membuat paket tidak menunggu di teras. Ini cara memilihnya untuk belanja frozen mingguan.",
        category: "Tips Pelanggan",
        readMinutes: 3,
        publishedAt: "2026-10-02",
        author: "Tim Layanan Pelanggan",
        accent: "from-amber-400/90 via-orange-400/80 to-rose-400/70",
        icon: "schedule",
        sections: [
            {
                paragraphs: [
                    "Belanja frozen biasanya dilakukan sekaligus untuk seminggu. Karena itu jadwal antar yang dipilih menentukan seberapa sering paket berpindah tangan tanpa ada yang menerima."
                ]
            },
            {
                heading: "Tiga aturan sederhana",
                bullets: [
                    "Pilih jendela dua jam saat ada orang di rumah, bukan sepanjang hari.",
                    "Hindari jam istirahat siang kalau alamat Anda perkantoran.",
                    "Untuk kiriman berisi es krim atau produk sensitif, pilih layanan instan."
                ]
            },
            {
                paragraphs: [
                    "Kalau rencana berubah, konfirmasi jadwal ulang dari aplikasi. Pengiriman ulang tetap akan memakai rantai dingin yang sama, dan paket lama akan diperiksa kondisinya sebelum diteruskan."
                ]
            }
        ]
    },
    {
        slug: "menyiapkan-toko-frozen-food-untuk-pengiriman",
        title: "Menyiapkan toko frozen food untuk pengiriman harian",
        excerpt:
            "Dari pengecekan stok sampai penyerahan ke kurir: alur yang membuat toko frozen siap mengirim setiap hari.",
        category: "UMKM",
        readMinutes: 6,
        publishedAt: "2026-10-07",
        author: "Tim Kemitraan UMKM",
        accent: "from-emerald-400/90 via-teal-400/80 to-sky-400/80",
        icon: "storefront",
        sections: [
            {
                paragraphs: [
                    "Toko frozen food yang tumbuh cepat biasanya tersandung pada satu hal: kapasitas pengepakan, bukan penjualan. Pesanan masuk lebih cepat daripada kemampuan menyiapkan cooler box."
                ]
            },
            {
                heading: "Rutinitas yang terbukti",
                bullets: [
                    "Kelompokkan pesanan per jendela antar, bukan per jam masuknya pesanan.",
                    "Siapkan cooler box dan ice gel pada malam sebelumnya.",
                    "Catat suhu saat paket diserahkan ke kurir, simpan sebagai bukti.",
                    "Pisahkan produk paling sensitif untuk diambil paling akhir."
                ]
            },
            {
                heading: "Pakai katalog di aplikasi",
                paragraphs: [
                    "Pelanggan Anda bisa memilih sendiri produk beku, jumlahnya, dan jadwal antarnya dari aplikasi. Pemesanan mandiri ini memindahkan pekerjaan mencatat alamat dan nomor telepon dari chat ke sistem, sehingga tim Anda fokus pada pengepakan."
                ]
            }
        ]
    },
    {
        slug: "apa-yang-terjadi-saat-paket-frozen-bermasalah",
        title: "Apa yang terjadi saat paket frozen bermasalah",
        excerpt:
            "Kemasan bocor, es mencair, atau penerima menolak: alur kendala kami memutuskan dalam satu putaran, bukan berhari-hari.",
        category: "Operasional",
        readMinutes: 5,
        publishedAt: "2026-10-11",
        author: "Tim Operasional Anteraja Frozen",
        accent: "from-violet-500/90 via-indigo-500/80 to-sky-500/70",
        icon: "report",
        sections: [
            {
                paragraphs: [
                    "Kendala di lapangan selalu punya dua sisi: apa yang dilihat kurir dan apa yang ingin dilakukan pengirim. Alur kendala mempertemukan keduanya sebelum paket bergerak lagi."
                ]
            },
            {
                heading: "Urutannya",
                bullets: [
                    "Kurir mencatat jenis kendala, catatan, dan foto kondisi paket.",
                    "Paket masuk antrean kendala hub dan tidak bisa diantar sebelum diputuskan.",
                    "Admin memilih keputusan: lanjutkan, jadwalkan ulang, kirim ulang, kembalikan, atau periksa ulang.",
                    "Keputusan beserta alasannya tercatat, dan pembeli menerima pemberitahuannya lewat kanal yang aktif."
                ]
            },
            {
                paragraphs: [
                    "Kalau keputusannya pengiriman ulang, paket baru akan dibuat dengan resi sendiri dan tetap membawa instruksi penanganan yang sama, sehingga penerima tidak perlu mengulang seluruh penjelasan."
                ]
            }
        ]
    }
];

export function findPost(slug: string): Post | undefined {
    return POSTS.find((post) => post.slug === slug);
}

/** Newest first, which is the order the landing page and the index both show. */
export function latestPosts(count: number): Post[] {
    return [...POSTS].sort((a, b) => b.publishedAt.localeCompare(a.publishedAt)).slice(0, count);
}

export function formatPostDate(value: string): string {
    const date = new Date(value + "T00:00:00+07:00");

    return date.toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "Asia/Jakarta"
    });
}
