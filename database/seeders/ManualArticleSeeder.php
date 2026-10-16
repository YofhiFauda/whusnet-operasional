<?php

namespace Database\Seeders;

use App\Models\ManualArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ManualArticleSeeder extends Seeder
{
    public function run(): void
    {
        $source = base_path('docs/plan/infrastruktur/manual-book-penataan-infrastruktur.md');
        if (! File::exists($source)) {
            throw new \RuntimeException("Sumber Manual Book Infrastruktur tidak ditemukan: {$source}");
        }

        $markdown = File::get($source);
        $articles = [
            1 => ['prinsip-kerja-infrastruktur', 'Prinsip kerja infrastruktur'],
            2 => ['peran-dan-layar-infrastruktur', 'Peran dan layar kerja'],
            3 => ['menata-titik-jaringan-baru', 'Alur menata titik jaringan baru'],
            4 => ['memasang-switch-baru', 'Memasang atau mengganti switch'],
            5 => ['menambah-odp-baru', 'Menambah ODP baru'],
            6 => ['port-odp-dan-slot-olt', 'Port ODP, slot OLT, dan pelanggan'],
            7 => ['pergantian-aset-infrastruktur', 'Ganti, cabut, dan pindah aset'],
            8 => ['checklist-pekerjaan-infrastruktur', 'Checklist penerimaan pekerjaan'],
            9 => ['masalah-umum-infrastruktur', 'Pemecahan masalah infrastruktur'],
            10 => ['batas-fitur-infrastruktur', 'Batas implementasi infrastruktur'],
        ];

        preg_match_all('/^## (\d+)\. ([^\r\n]+)$/m', $markdown, $headings, PREG_OFFSET_CAPTURE);
        foreach ($headings[0] as $index => $heading) {
            $sectionNumber = (int) $headings[1][$index][0];
            if (! isset($articles[$sectionNumber])) {
                continue;
            }

            [$slug, $summary] = $articles[$sectionNumber];
            $start = $heading[1];
            $end = $headings[0][$index + 1][1] ?? strlen($markdown);
            $body = trim(substr($markdown, $start, $end - $start));
            $article = ManualArticle::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $headings[2][$index][0],
                    'category' => 'Infrastruktur',
                    'summary' => $summary,
                    'body_markdown' => $body,
                    'required_permission' => 'network_nodes.view',
                    'route_names' => ['network.map.index', 'network.nodes.index'],
                ]
            );

            if ($article->wasRecentlyCreated) {
                $revision = $article->recordRevision(null);
                $article->update([
                    'published_revision_id' => $revision->id,
                    'published_at' => now(),
                ]);
            }
        }

        $editorGuide = ManualArticle::firstOrCreate(
            ['slug' => 'mengelola-pusat-panduan'],
            [
                'title' => 'Mengelola Pusat Panduan',
                'category' => 'Pengelolaan Sistem',
                'summary' => 'Menulis, meninjau, menerbitkan, dan memulihkan panduan.',
                'body_markdown' => <<<'MARKDOWN'
## Sebelum mulai

Owner mengatur izin `manuals.create`, `manuals.update`, dan `manuals.publish` melalui matriks role. Semua anggota role yang sama menerima izin yang sama. Bila hanya beberapa admin yang boleh mengelola panduan, buat role global khusus melalui UI dan berikan izin kerja lain yang mereka perlukan.

## Menulis artikel

1. Buka **Pusat Panduan → Kelola panduan → Artikel baru**.
2. Isi slug URL unik, judul, kategori, ringkasan, dan langkah kerja dalam Markdown.
3. Pilih **Izin pembaca** sesuai fitur. Kosong berarti semua staf yang login dapat membaca artikel terbit.
4. Isi nama route halaman terkait bila ingin tombol bantuan pada halaman itu mengarah ke artikel ini.
5. Simpan draf, lalu gunakan **Pratinjau draf tersimpan** untuk memeriksa isi.

## Menerbitkan dan memperbaiki

1. Pemeriksa yang memiliki `manuals.publish` memilih **Terbitkan revisi terbaru**.
2. Penyuntingan berikutnya hanya mengubah draf. Pembaca tetap melihat versi lama sampai revisi baru diterbitkan.
3. Bila perlu, pilih revisi lama lalu **Pulihkan sebagai draf**. Terbitkan lagi setelah diperiksa.
4. Gunakan **Tarik publikasi** bila artikel harus segera disembunyikan. Riwayat revisi tetap tersimpan.

Jangan menulis kata sandi, token API, atau data pelanggan nyata di panduan. Tinjau kembali panduan setiap kali alur fitur berubah.
MARKDOWN,
                'required_permission' => 'manuals.create',
                'route_names' => ['manuals.manage'],
            ]
        );

        if ($editorGuide->wasRecentlyCreated) {
            $revision = $editorGuide->recordRevision(null);
            $editorGuide->update([
                'published_revision_id' => $revision->id,
                'published_at' => now(),
            ]);
        }
    }
}
