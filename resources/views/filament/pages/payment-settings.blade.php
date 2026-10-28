<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section
            heading="Rekening tujuan transfer"
            description="Ditampilkan hanya di halaman pembayaran milik pelanggan, tidak pernah di halaman publik."
        >
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="bankName" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
                        Nama bank
                    </label>
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
                        <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bankAccount" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
                        Nomor rekening
                    </label>
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
                        <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="bankHolder" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
                        Nama pemilik rekening
                    </label>
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
                        <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Cara kerja"
            description="Alur pembayaran manual saat ini."
            collapsible
        >
            <ol class="list-decimal space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>Pelanggan memilih kursus atau acara, lalu membuka halaman pembayaran.</li>
                <li>Halaman itu menampilkan rekening di atas beserta form unggah bukti transfer.</li>
                <li>Bukti masuk ke penyimpanan privat dan hanya bisa dibaca staf dengan akses pembayaran.</li>
                <li>Staf memvalidasi bukti di menu Pembayaran; akses kursus/peserta acara diberikan otomatis.</li>
            </ol>
        </x-filament::section>

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Simpan rekening
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
