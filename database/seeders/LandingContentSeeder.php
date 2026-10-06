<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\ValueProposition;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * The landing-page content that legacy had NO database table for.
 *
 * Everything in this file was hardcoded markup in index.php:
 *  - the 24 "What Do You Get?" tiles        (index.php:253-431)
 *  - the 10 FAQ items, questions + answers  (index.php:756-864)
 *  - the hero headline, subtitle and CTA    (index.php:33-36)
 *  - the office label, address, maps link   (index.php:501-514)
 *  - the per-service brochure mapping       (index.php:668-678)
 *  - the contact and social links           (partials/footer.php:14-44)
 *
 * Because it was markup, `app:migrate-legacy-data` has nothing to read it from
 * -- there is no legacy table to query. So it is seeded here instead, and this
 * seeder is SAFE TO RUN ALONGSIDE the legacy migration: every write is an
 * upsert keyed on stable content, and existing rows are left alone.
 *
 *     php artisan db:seed --class=Database\\Seeders\\LandingContentSeeder
 *
 * Indonesian strings are copied verbatim from index.php. English strings are
 * translations written for this migration -- legacy index-en.php had its own
 * wording for the hero only; the rest of the English copy did not exist.
 */
class LandingContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDeliverables();
        $this->seedFaqs();
        $this->seedSettings();
    }

    /**
     * The 24 tiles of legacy's "What Do You Get?" grid, in legacy order, each
     * with the exact icon filename legacy referenced.
     *
     * Verified count: index.php contains exactly 24 `.benefit-item` blocks and
     * 24 icon <img> tags. (task.md's "25 benefits" note is a miscount -- the
     * icons/ folder holds 25 PNGs, but one of them is ICS.png, the floating
     * WhatsApp button icon, which is not a tile.)
     */
    private function seedDeliverables(): void
    {
        $tiles = [
            ['interior-design.png',                 'Desain Interior',                      'Interior Design'],
            ['exterior design.png',                 'Desain Exterior',                      'Exterior Design'],
            ['gambar kerja pembangunans.png',       'Gambar Kerja Pembangunan',             'Construction Drawings'],
            ['gambar kerja perijinan.png',          'Gambar Kerja Perijinan',               'Permit Drawings'],
            ['RAB.png',                             'Rencana Anggaran Biaya',               'Cost Budget Plan'],
            ['Detail Furnitur.png',                 'Detail Furnitur',                      'Furniture Details'],
            ['File 3D.png',                         'File Desain 3D dan 2D',                '3D and 2D Design Files'],
            ['Animasi 3D.png',                      'Animasi 3D',                           '3D Animation'],
            ['analisis struktur.png',               'Analisis Struktur',                    'Structural Analysis'],
            ['pengawasan konstruksi.png',           'Pengawasan Konstruksi',                'Construction Supervision'],
            ['konsultasi gratis.png',               'Konsultasi Gratis',                    'Free Consultation'],
            ['survey lokasi ptoyek.png',            'Survey Lokasi Proyek',                 'Project Site Survey'],
            ['CS.png',                              'CS Responsif',                         'Responsive Customer Service'],
            ['hardfile.png',                        'Hardfile A4 Jilid Premium',            'Premium Bound A4 Hardfile'],
            ['softfile.png',                        'Softfile Siap Print',                  'Print-Ready Softfile'],
            ['pembayaran bertahap.png',             'Pembayaran Bertahap',                  'Instalment Payment'],
            ['garansi bisa dibangun.png',           'Garansi Bisa Dibangun',                'Buildability Guarantee'],
            ['desain sesuai harapan.png',           'Desain Sesuai Harapan',                'Design That Matches Your Brief'],
            ['pemilihan material optimal.png',      'Pemilihan Material Optimal',           'Optimal Material Selection'],
            ['optimasi lahan sempit.png',           'Optimasi Lahan Sempit dan Luas',       'Small and Large Plot Optimisation'],
            ['kooridnasi desain.png',               'Koordinasi Desain Saat Pembangunan',   'Design Coordination During Build'],
            ['pendampingan sidang.png',             'Pendampingan Sidang Perijinan',        'Permit Hearing Assistance'],
            ['efisiensi biaya.png',                 'Efisiensi Biaya Pembangunan',          'Build Cost Efficiency'],
            ['garansi lolos perijinan.png',         'Garansi Lolos Perijinan Pembangunan',  'Permit Approval Guarantee'],
        ];

        foreach ($tiles as $order => [$icon, $labelId, $labelEn]) {
            $existing = ValueProposition::where('group', ValueProposition::GROUP_DELIVERABLE)
                ->get()
                ->first(fn (ValueProposition $v) => $v->getTranslation('label', 'id') === $labelId);

            $tile = $existing ?: new ValueProposition();
            $tile->group = ValueProposition::GROUP_DELIVERABLE;
            $tile->label = ['id' => $labelId, 'en' => $labelEn];
            $tile->icon_path = 'images/icons/'.$icon;
            $tile->sort_order = $order;
            $tile->is_active = true;
            $tile->save();
        }
    }

    /** All 10 FAQ items, answers included, verbatim from index.php. */
    private function seedFaqs(): void
    {
        foreach ($this->faqItems() as $order => [$qId, $aId, $qEn, $aEn]) {
            $existing = Faq::all()
                ->first(fn (Faq $f) => $f->getTranslation('question', 'id') === $qId);

            $faq = $existing ?: new Faq();
            $faq->question = ['id' => $qId, 'en' => $qEn];
            $faq->answer = ['id' => $aId, 'en' => $aEn];
            $faq->sort_order = $order;
            $faq->is_published = true;
            $faq->save();
        }
    }

    /**
     * @return array<int, array{0:string,1:string,2:string,3:string}>
     */
    private function faqItems(): array
    {
        return [
            [
                'Apa saja layanan yang disediakan 3D Expert?',
                '3D Expert menyediakan layanan desain interior, desain eksterior, gambar kerja pembangunan, gambar kerja renovasi, rencana anggaran biaya, dan layanan arsitek profesional lainnya untuk membantu mewujudkan hunian impian Anda.',
                'What services does 3D Expert provide?',
                '3D Expert provides interior design, exterior design, construction drawings, renovation drawings, cost budget plans, and other professional architectural services to help bring your dream home to life.',
            ],
            [
                'Apakah saya bisa revisi desain jika belum sesuai?',
                'Ya, kami menyediakan layanan revisi untuk memastikan desain sesuai dengan keinginan Anda. Proses revisi dilakukan berdasarkan feedback yang Anda berikan hingga mencapai hasil yang memuaskan.',
                'Can I request revisions if the design is not right yet?',
                'Yes. We provide revisions to make sure the design matches what you want. Revisions are based on the feedback you give us, and continue until the result is satisfactory.',
            ],
            [
                'Apakah layanan 3D Expert bisa dilakukan secara online?',
                'Tentu saja! Kami menyediakan layanan konsultasi dan meeting online melalui video call. Semua proses dari konsultasi hingga presentasi desain dapat dilakukan secara virtual untuk kemudahan Anda.',
                'Can 3D Expert work with me entirely online?',
                'Absolutely. We offer consultations and meetings over video call. Everything from the first consultation to the design presentation can be done remotely for your convenience.',
            ],
            [
                'Apa saja yang saya dapatkan dalam paket desain?',
                'Paket desain kami meliputi konsep desain 3D, gambar kerja detail, daftar material, rencana anggaran biaya, dan konsultasi teknis. Semua file akan diserahkan dalam format digital yang mudah digunakan.',
                'What is included in a design package?',
                'Our design packages include the 3D design concept, detailed working drawings, a material list, a cost budget plan, and technical consultation. All files are delivered in easy-to-use digital formats.',
            ],
            [
                'Berapa lama waktu yang dibutuhkan untuk proses desain?',
                'Waktu pengerjaan desain bervariasi tergantung kompleksitas proyek. Umumnya untuk desain rumah tinggal membutuhkan waktu 2-4 minggu, sedangkan proyek komersial dapat memakan waktu lebih lama sesuai dengan lingkup pekerjaan.',
                'How long does the design process take?',
                'Timelines vary with project complexity. A residential design typically takes 2-4 weeks, while commercial projects can take longer depending on the scope of work.',
            ],
            [
                'Apakah 3D Expert bisa membantu dalam perencanaan?',
                'Ya, kami membantu dalam perencanaan menyeluruh mulai dari konsep awal, pemilihan material, estimasi biaya, hingga tahap konstruksi. Tim ahli kami siap memberikan solusi terbaik untuk proyek Anda.',
                'Can 3D Expert help with planning?',
                'Yes. We help with end-to-end planning: initial concept, material selection, cost estimation, and through to the construction stage. Our team is ready to find the best solution for your project.',
            ],
            [
                'Bagaimana sistem pembayaran?',
                'Sistem pembayaran kami fleksibel dengan beberapa tahapan. Umumnya dimulai dengan down payment 30%, progress payment 40%, dan pelunasan 30% setelah desain selesai. Kami menerima transfer bank dan metode pembayaran lainnya.',
                'How does payment work?',
                'Payment is flexible and staged. Typically it starts with a 30% down payment, then a 40% progress payment, and the final 30% once the design is complete. We accept bank transfers and other payment methods.',
            ],
            [
                'Apa yang dimaksud dengan "Garansi Bisa Dibangun"?',
                '"Garansi Bisa Dibangun" adalah jaminan bahwa setiap desain yang kami buat dapat direalisasikan secara teknis dan struktural. Semua gambar kerja telah melalui perhitungan teknis yang akurat dan siap untuk tahap konstruksi.',
                'What does the "Buildability Guarantee" mean?',
                'The "Buildability Guarantee" is our assurance that every design we produce can actually be realised, technically and structurally. All working drawings go through accurate engineering calculations and are ready for the construction stage.',
            ],
            [
                'Apakah harga desain bisa disesuaikan dengan budget?',
                'Tentu! Kami memiliki berbagai paket desain yang dapat disesuaikan dengan budget Anda. Tim kami akan membantu menentukan solusi desain terbaik yang sesuai dengan anggaran dan kebutuhan spesifik proyek Anda.',
                'Can the design price be adjusted to my budget?',
                'Of course. We have a range of design packages that can be matched to your budget. Our team will help you settle on the best design solution for your budget and the specific needs of your project.',
            ],
            [
                'Bagaimana cara memulai kerja sama?',
                'Untuk memulai kerja sama, Anda dapat menghubungi kami melalui konsultasi gratis. Tim kami akan membantu menganalisis kebutuhan proyek, memberikan estimasi biaya, dan menjelaskan proses kerja secara detail sebelum memulai kontrak kerja sama.',
                'How do we get started?',
                'Get in touch for a free consultation. Our team will help analyse your project needs, give you a cost estimate, and walk you through the process in detail before any contract is signed.',
            ],
        ];
    }

    /**
     * Settings that legacy kept as literal strings in index.php and
     * partials/footer.php. Only written when currently empty, so a value the
     * legacy migration already filled in is never overwritten.
     */
    private function seedSettings(): void
    {
        $whatsapp = 'https://wa.me/6282338389488';

        $defaults = [
            // Hero (index.php:33-36). Distinct from home_heading, which is the
            // FEATURES block headline (legacy features_section.judul_konten).
            'hero_heading' => [
                'id' => 'Wujudkan Rumah Mewah dengan Sentuhan Profesional',
                'en' => 'Create a Luxurious Home with a Professional Touch',
            ],
            'hero_subheading' => [
                'id' => 'Perpaduan sempurna antara estetika, fungsi, dan eksklusivitas.',
                'en' => 'The perfect blend of aesthetics, function and exclusivity.',
            ],

            // Office block (index.php:501-514).
            'location_office_label' => [
                'id' => 'HEAD OFFICE PT LUXORA AURA',
                'en' => 'HEAD OFFICE PT LUXORA AURA',
            ],
            'company_address' => [
                'id' => "Head Office PT Luxora Aura Semesta\nJl. Bendul Merisi Selatan 7 no 57 Surabaya",
                'en' => "Head Office PT Luxora Aura Semesta\nJl. Bendul Merisi Selatan 7 no 57 Surabaya",
            ],
            'company_maps_url' => 'https://goo.gl/maps/68pDytiKfG1dLDMB8',
            'company_name' => '3D Expert',

            // Social (partials/footer.php:22-37).
            'company_whatsapp' => $whatsapp,
            'company_facebook' => 'https://www.facebook.com/people/3D-expertid/100063785329134/',
            'company_instagram' => 'https://www.instagram.com/3dexpert.id',
            'company_youtube' => 'https://www.youtube.com/@3dexpertbuildingsolution',
            'company_tiktok' => 'https://www.tiktok.com/@3d.expert',
        ];

        foreach ($defaults as $key => $value) {
            if (blank(Settings::get($key))) {
                Settings::set($key, $value);
            }
        }
    }

    /*
     * Brochure mapping moved to LegacyServiceSeeder.
     *
     * It used to match on the Indonesian title, which looked safer than
     * legacy's hardcoded `if ($data['id'] == 1)`. It is not: all four
     * layanan_section rows are titled "Pembangunan Lengkap" (row 1 with a
     * trailing "s"), so a title match assigns the same PDF to all four.
     * LegacyServiceSeeder keys on the legacy id recovered from the slug.
     */
}
