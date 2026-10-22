<?php

namespace App\Filament\Support;

/**
 * Sidebar groups, in the order the panel uses them.
 *
 * Kept as constants so a resource cannot drift into a differently-spelled
 * group name and silently create a second sidebar section.
 */
final class NavGroup
{
    public const CONTENT = 'Konten';

    public const PROGRAM = 'Program';

    public const CAREER = 'Karier';

    public const CUSTOMER = 'Pelanggan';

    public const SYSTEM = 'Sistem';
}
