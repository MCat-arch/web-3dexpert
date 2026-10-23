<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteSettingAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    /**
     * Insert directly so the JSON column keeps the raw legacy shape. The model
     * setter would normalise it before it ever reaches the form.
     */
    private function legacySetting(string $key, mixed $rawTranslations): SiteSetting
    {
        DB::table('site_settings')->insert([
            'key' => $key,
            'type' => 'string',
            'value' => null,
            'value_translations' => $rawTranslations,
            'is_translatable' => true,
            'group' => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return SiteSetting::query()->where('key', $key)->sole();
    }

    public function test_setting_edit_page_survives_sql_null_legacy_json(): void
    {
        $setting = $this->legacySetting('legacy_null', null);

        $this->actingAs($this->admin())
            ->get(SiteSettingResource::getUrl('edit', ['record' => $setting]))
            ->assertOk()
            ->assertSee('legacy_null')
            ->assertSee('Pengaturan situs');
    }

    public function test_setting_edit_page_survives_a_json_null_value(): void
    {
        $setting = $this->legacySetting('legacy_json_null', 'null');

        $this->actingAs($this->admin())
            ->get(SiteSettingResource::getUrl('edit', ['record' => $setting]))
            ->assertOk()
            ->assertSee('legacy_json_null');
    }

    public function test_setting_edit_page_survives_a_plain_string_translation(): void
    {
        $setting = $this->legacySetting('legacy_plain', 'Teks lama sebelum translasi');

        $this->actingAs($this->admin())
            ->get(SiteSettingResource::getUrl('edit', ['record' => $setting]))
            ->assertOk()
            ->assertSee('legacy_plain');
    }

    public function test_setting_create_page_renders_without_a_record(): void
    {
        $this->actingAs($this->admin())
            ->get(SiteSettingResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Pengaturan situs');
    }

    public function test_a_normal_translation_map_still_renders_both_keys(): void
    {
        $setting = $this->legacySetting('normal_setting', json_encode([
            'id' => 'Nilai Indonesia',
            'en' => 'English value',
        ]));

        $this->actingAs($this->admin())
            ->get(SiteSettingResource::getUrl('edit', ['record' => $setting]))
            ->assertOk()
            ->assertSee('normal_setting')
            ->assertSee('Nilai Indonesia');
    }
}
