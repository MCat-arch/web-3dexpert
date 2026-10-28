<x-filament-panels::page>
    {{--
        Scoped layout CSS: the panel's compiled Tailwind bundle does not ship
        every utility used here (sm:grid-cols-2 / list-decimal are missing), so
        spacing is spelled out instead of relying on classes that may not exist.
        style-src allows 'unsafe-inline', so a scoped <style> is safe under the
        app's CSP.
    --}}
    <style>
        .pay-settings { display: flex; flex-direction: column; gap: 1.5rem; }
        .pay-settings-grid { display: grid; gap: 1.25rem; }
        .pay-settings-label { display: block; margin-bottom: 0.4rem; font-size: 0.875rem; line-height: 1.25rem; font-weight: 500; }
        .pay-settings-error { margin-top: 0.35rem; font-size: 0.8125rem; }
        .pay-settings-steps { list-style: decimal; padding-left: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem; }
        .pay-settings-actions { display: flex; justify-content: flex-end; }
        @media (min-width: 768px) {
            .pay-settings-grid { grid-template-columns: 1fr 1fr; }
            .pay-settings-wide { grid-column: 1 / -1; }
        }
    </style>

    <form wire:submit="save" class="pay-settings">
        <x-filament::section
            heading="Rekening tujuan transfer"
            description="Ditampilkan hanya di halaman pembayaran milik pelanggan, tidak pernah di halaman publik."
        >
            <div class="pay-settings-grid">
                <div class="pay-settings-wide">
                    <label for="bankName" class="pay-settings-label">Nama bank</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            id="bankName"
                            wire:model="bankName"
                            placeholder="Contoh: Bank Mandiri"
                            autocomplete="off"
                        />
                    </x-filament::input.wrapper>
                    @error('bankName')
                        <p class="pay-settings-error text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bankAccount" class="pay-settings-label">Nomor rekening</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            id="bankAccount"
                            wire:model="bankAccount"
                            placeholder="Contoh: 123-00-1234567-8"
                            autocomplete="off"
                            inputmode="numeric"
                        />
                    </x-filament::input.wrapper>
                    @error('bankAccount')
                        <p class="pay-settings-error text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bankHolder" class="pay-settings-label">Nama pemilik rekening</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            id="bankHolder"
                            wire:model="bankHolder"
                            placeholder="Contoh: PT Luxora Aura Semesta"
                            autocomplete="off"
                        />
                    </x-filament::input.wrapper>
                    @error('bankHolder')
                        <p class="pay-settings-error text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Cara kerja"
            description="Alur pembayaran manual saat ini."
            collapsible
        >
            <ol class="pay-settings-steps text-gray-600 dark:text-gray-300">
                <li>Pelanggan memilih kursus atau acara, lalu membuka halaman pembayaran.</li>
                <li>Halaman itu menampilkan rekening di atas beserta form unggah bukti transfer.</li>
                <li>Bukti masuk ke penyimpanan privat dan hanya bisa dibaca staf dengan akses pembayaran.</li>
                <li>Staf memvalidasi bukti di menu Pembayaran; akses kursus/peserta acara diberikan otomatis.</li>
            </ol>
        </x-filament::section>

        <div class="pay-settings-actions">
            <x-filament::button type="submit">
                Simpan rekening
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
