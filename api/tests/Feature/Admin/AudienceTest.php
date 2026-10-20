<?php

namespace Tests\Feature\Admin;

use App\Audience\Audience;
use App\Enums\ColumnRole;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/** Who buys: channels, gender and age groups from the business's own tables. */
class AudienceTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 12:00:00');

        $this->shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->forTenant($this->shop)->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_nothing_to_show_without_the_columns(): void
    {
        $this->getJson('/api/v1/admin/analytics/audience')
            ->assertOk()
            ->assertJsonPath('data.channels', null)
            ->assertJsonPath('data.gender', null)
            ->assertJsonPath('data.age', null)
            ->assertJsonPath('data.social', []);
    }

    public function test_orders_are_split_by_channel_gender_and_age(): void
    {
        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Հոկտեմբեր',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
                ['label' => 'Աղբյուր', 'type' => 'text', 'role' => 'channel'],
                ['label' => 'Սեռ', 'type' => 'text', 'role' => 'gender'],
                ['label' => 'Տարիք', 'type' => 'number', 'role' => 'age'],
            ],
        ])->assertCreated()->json('data');

        $keys = array_column($table['columns'], 'key');
        foreach ([
            ['2026-10-16', 5000, 'insta', 'Իգական', 29],
            ['2026-10-15', 3000, 'Instagram', 'ж', 41],
            ['2026-10-14', 2000, 'Facebook', 'Արական', 23],
            ['2026-08-01', 9000, 'Facebook', 'Արական', 23], // outside 30 days
        ] as $row) {
            $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => array_combine($keys, $row)])->assertCreated();
        }

        // Site orders: under the UTM source they came with, «Կայք» without one.
        $this->asTenant($this->shop, function () {
            foreach (['instagram', null] as $utm) {
                Lead::create(['name' => 'Client', 'phone' => '+37491000000', 'preferred_locale' => 'hy', 'consent' => true, 'source' => 'website', 'utm_source' => $utm]);
            }
        });

        $data = $this->getJson('/api/v1/admin/analytics/audience?period=30')->assertOk()->json('data');

        $this->assertSame([
            ['key' => 'instagram', 'label' => 'Instagram', 'rows' => 3, 'amount' => 8000],
            ['key' => 'facebook', 'label' => 'Facebook', 'rows' => 1, 'amount' => 2000],
            ['key' => 'կայք', 'label' => 'Կայք', 'rows' => 1, 'amount' => null],
        ], $data['channels']);
        $this->assertSame(['female' => 2, 'male' => 1, 'other' => 0], array_column($data['gender'], 'rows', 'key'));
        $this->assertSame(8000, $data['gender'][0]['amount']);
        $this->assertSame(['<18' => 0, '18-24' => 1, '25-34' => 1, '35-44' => 1, '45-54' => 0, '55+' => 0], array_column($data['age'], 'rows', 'key'));
    }

    public function test_values_are_read_in_any_language(): void
    {
        $this->assertSame('female', Audience::gender('Женский'));
        $this->assertSame('male', Audience::gender('male'));
        $this->assertSame('female', Audience::gender('F'));
        $this->assertNull(Audience::gender(' '));
        $this->assertSame('25-34', Audience::ageGroup('25-34'));
        $this->assertSame('55+', Audience::ageGroup('60+'));
        $this->assertNull(Audience::ageGroup('—'));
        $this->assertSame('Instagram', Audience::channel('Ինստագրամ'));
        $this->assertSame('Կայք', Audience::channel('сайт'));
        $this->assertSame(ColumnRole::Gender, ColumnRole::guess('Пол'));
        $this->assertSame(ColumnRole::Gender, ColumnRole::guess('Հաճախորդի սեռ'));
        $this->assertSame(ColumnRole::Age, ColumnRole::guess('Возраст'));
        $this->assertSame(ColumnRole::Age, ColumnRole::guess('Age'));
    }

    public function test_a_moderator_does_not_see_it(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->getJson('/api/v1/admin/analytics/audience')->assertForbidden();
    }
}
