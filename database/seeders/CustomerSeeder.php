<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Legacy public accounts, seeded as CUSTOMERS.
 *
 * These people are site visitors, not staff. They must never live in the
 * `users` table: that table backs Filament, where the column default role
 * 'editor' can sign in to /admin. A shared password on 50 panel accounts would
 * hand out admin access; a customer row cannot reach the panel at all.
 *
 * Safe to run any number of times:
 *   - an email that already exists is skipped, never updated -- existing
 *     names, passwords, verification state and login history stay untouched;
 *   - soft-deleted accounts are skipped too, because they still own their
 *     unique email;
 *   - nothing is deleted or truncated.
 */
class CustomerSeeder extends Seeder
{
    /**
     * Shared initial password so these accounts can log in after a reset.
     * Customer casts `password` to 'hashed', so the plain value is only ever
     * assigned -- never stored.
     */
    private const DEFAULT_PASSWORD = 'password123';

    /** @var array<string, string> email => name */
    public const CUSTOMERS = [
        'indra.hardja@gmail.com' => 'Indra Wibawa Hardja',
        'hendri.tedjo@gmail.com' => 'Hendri Ary Tedjo',
        'pahlawan@gmail.com' => 'Pahlawan',
        'natan@gmail.com' => 'Natan',
        'yvan.sitanala@gmail.com' => 'Yvan Sitanala',
        'benjamin.manihuruk@gmail.com' => 'Benjamin Manihuruk',
        'judas.sianturi@gmail.com' => 'Judas Sianturi',
        'gideon.turnip@gmail.com' => 'Gideon Turnip',
        'tirto@gmail.com' => 'Tirto',
        'slamet@gmail.com' => 'Slamet',
        'cinta.muljana@gmail.com' => 'Cinta Bethari Muljana',
        'hartanti.tahyadi@gmail.com' => 'Hartanti Yulia Tahyadi',
        'tandiono@gmail.com' => 'Tandiono',
        'limijanto@gmail.com' => 'Limijanto',
        'zefanja.rehatta@gmail.com' => 'Zefanja Rehatta',
        'sherah.sinabariba@gmail.com' => 'Sherah Sinabariba',
        'anna.bolahan@gmail.com' => 'Anna Bolahan',
        'ruth.simanungkalit@gmail.com' => 'Ruth Simanungkalit',
        'sriningsih@gmail.com' => 'Sriningsih',
        'tuminindyah@gmail.com' => 'Tuminindyah',
        'setiawan.irawan@gmail.com' => 'Setiawan Susila Irawan',
        'yohanes.wibowo@gmail.com' => 'Yohanes Suparman Wibowo',
        'yansen@gmail.com' => 'Yansen',
        'wendi@gmail.com' => 'Wendi',
        'splinter.dissels@gmail.com' => 'Splinter Dissels',
        'rufus.sidari@gmail.com' => 'Rufus Sidari',
        'daniel.baruara@gmail.com' => 'Daniel Baruara',
        'eli.simarmata@gmail.com' => 'Eli Simarmata',
        'adi@gmail.com' => 'Adi',
        'suripto@gmail.com' => 'Suripto',
        'surya.salim@gmail.com' => 'Surya Tri Salim',
        'yandi.chandra@gmail.com' => 'Yandi Hamdani Chandra',
        'willy@gmail.com' => 'Willy',
        'rovanio.yanlin@gmail.com' => 'Rovanio Yanlin',
        'niel.noya@gmail.com' => 'Niel Noya',
        'joash.manalu@gmail.com' => 'Joash Manalu',
        'shem.babo@gmail.com' => 'Shem Babo',
        'bildad.hutajulu@gmail.com' => 'Bildad Hutajulu',
        'dian@gmail.com' => 'Dian',
        'suharto@gmail.com' => 'Suharto',
        'yanti.fanny@gmail.com' => 'Yanti Fanny',
        'leony.pranoto@gmail.com' => 'Leony Kasih Pranoto',
        'canggih.lin@gmail.com' => 'Canggih Lin',
        'handaya@gmail.com' => 'Handaya',
        'siddeeqa.limaheluw@gmail.com' => 'Siddeeqa Limaheluw',
        'magdalene.munthe@gmail.com' => 'Magdalene Munthe',
        'esther.siagian@gmail.com' => 'Esther Siagian',
        'olive.surbakti@gmail.com' => 'Olive Surbakti',
        'sinta@gmail.com' => 'Sinta',
        'tri@gmail.com' => 'Tri',
    ];

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        foreach (self::CUSTOMERS as $email => $name) {
            if (Customer::withTrashed()->where('email', $email)->exists()) {
                $skipped++;

                continue;
            }

            $customer = Customer::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => self::DEFAULT_PASSWORD,
            ]);

            // Not mass assignable on purpose: verification and activation are
            // staff-controlled, so the seeder sets them explicitly.
            $customer->forceFill([
                'email_verified_at' => now(),
                'is_active' => true,
            ])->save();

            $created++;
        }

        $this->command?->info("CustomerSeeder: {$created} pelanggan dibuat, {$skipped} dilewati (sudah ada).");
    }
}
