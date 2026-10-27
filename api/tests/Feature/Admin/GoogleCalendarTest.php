<?php

namespace Tests\Feature\Admin;

use App\Google\GoogleCalendarClient;
use App\Models\DataTable;
use App\Models\GoogleConnection;
use App\Models\GoogleEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeGoogleCalendarClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Google Calendar in the business calendar: connecting an account (read
 * only), choosing calendars, syncing their events and turning them into an
 * order table, a new version each time something changed. Google is a fake.
 */
class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    private User $owner;

    private FakeGoogleCalendarClient $google;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 10:00:00');
        $this->salon = $this->createTenant('test-salon');
        $this->owner = User::factory()->forTenant($this->salon, 'owner')->create();
        Sanctum::actingAs($this->owner);

        $this->google = new FakeGoogleCalendarClient;
        $this->google->calendars = [
            ['id' => 'owner@gmail.com', 'summary' => 'owner@gmail.com', 'primary' => true, 'backgroundColor' => '#9fe1e7', 'accessRole' => 'owner'],
            ['id' => 'orders@group.calendar.google.com', 'summary' => 'Պատվերներ', 'backgroundColor' => '#16a765', 'accessRole' => 'writer'],
            ['id' => 'holidays', 'summary' => 'Busy only', 'accessRole' => 'freeBusyReader'],
        ];
        $this->google->events['orders@group.calendar.google.com'] = [
            $this->event('e1', 'Բազմոցի մաքրում', '2026-10-22T06:00:00Z', "Հաճախորդ: Անի\nՀեռախոս: 091 23 45 67\nԳին: 9900 դրամ\nՄուտքը բակից", 'Կոմիտասի 5'),
            $this->event('e2', 'Ներքնակ 5000 ֏', '2026-10-23T08:30:00Z'),
            ['id' => 'e3', 'status' => 'confirmed', 'summary' => 'Օրը զբաղված', 'start' => ['date' => '2026-10-24'], 'end' => ['date' => '2026-10-25'], 'updated' => '2026-10-20T00:00:00Z'],
            ['id' => 'gone', 'status' => 'cancelled'],
        ];
        $this->app->instance(GoogleCalendarClient::class, $this->google);
    }

    private function event(string $id, string $summary, string $start, ?string $description = null, ?string $location = null, string $updated = '2026-10-20T00:00:00Z'): array
    {
        return array_filter([
            'id' => $id,
            'status' => 'confirmed',
            'summary' => $summary,
            'description' => $description,
            'location' => $location,
            'start' => ['dateTime' => $start],
            'end' => ['dateTime' => date('Y-m-d\TH:i:s\Z', strtotime($start) + 3600)],
            'htmlLink' => "https://www.google.com/calendar/event?eid={$id}",
            'updated' => $updated,
        ], fn ($v) => $v !== null);
    }

    /** Connects the owner's account and chooses the «Պատվերներ» calendar. */
    private function connect(): void
    {
        $url = $this->postJson('/api/v1/admin/google/connect')->assertOk()->json('data.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->postJson('/api/v1/admin/google/callback', ['code' => 'code-1', 'state' => $query['state']])->assertOk();

        $orders = collect($this->getJson('/api/v1/admin/google')->json('data.connection.calendars'))->firstWhere('name', 'Պատվերներ');
        $this->putJson('/api/v1/admin/google/calendars', ['calendars' => [$orders['id']]])->assertOk();
    }

    private function calendar(string $from = '2026-10-19', string $to = '2026-10-31'): array
    {
        return $this->getJson("/api/v1/admin/calendar?from={$from}&to={$to}")->assertOk()->json('data');
    }

    public function test_without_keys_it_says_it_is_not_set_up(): void
    {
        $this->google->configured = false;

        $this->getJson('/api/v1/admin/google')->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.connection', null);
        $this->postJson('/api/v1/admin/google/connect')->assertStatus(503)->assertJsonPath('code', 'google_not_configured');
    }

    public function test_connecting_keeps_the_tokens_encrypted_and_lists_readable_calendars(): void
    {
        $this->connect();

        $state = $this->getJson('/api/v1/admin/google')->assertOk()->json('data');
        $this->assertTrue($state['configured']);
        $this->assertSame('owner@gmail.com', $state['connection']['email']);
        // Primary first; a free/busy-only calendar can't be read.
        $this->assertSame(['owner@gmail.com', 'Պատվերներ'], array_column($state['connection']['calendars'], 'name'));
        $this->assertSame([false, true], array_column($state['connection']['calendars'], 'selected'));
        $this->assertStringNotContainsString('access-1', json_encode($state));

        $raw = DB::table('google_connections')->first();
        $this->assertNotSame('access-1', $raw->access_token);
        $this->assertNotSame('refresh-1', $raw->refresh_token);
        $this->assertSame('refresh-1', GoogleConnection::withoutTenancy()->first()->refresh_token);
    }

    public function test_a_state_of_someone_else_or_too_old_is_refused(): void
    {
        $url = $this->postJson('/api/v1/admin/google/connect')->json('data.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'admin')->create());
        $this->postJson('/api/v1/admin/google/callback', ['code' => 'x', 'state' => $query['state']])
            ->assertStatus(422)->assertJsonPath('code', 'google_state');

        Sanctum::actingAs($this->owner);
        $this->travel(16)->minutes();
        $this->postJson('/api/v1/admin/google/callback', ['code' => 'x', 'state' => $query['state']])->assertStatus(422);
        $this->postJson('/api/v1/admin/google/callback', ['code' => 'x', 'state' => 'forged'])->assertStatus(422);

        $this->assertSame(0, GoogleConnection::withoutTenancy()->count());
    }

    public function test_sync_shows_the_events_in_the_calendar_in_local_time(): void
    {
        $this->connect();
        $this->google->pageSize = 2;

        $this->postJson('/api/v1/admin/google/sync')->assertOk()
            ->assertJsonPath('data.result', ['calendars' => 1, 'added' => 3, 'updated' => 0, 'removed' => 0]);

        $events = $this->calendar()['google'];
        $this->assertSame(
            [['2026-10-22', '10:00', 'Բազմոցի մաքրում'], ['2026-10-23', '12:30', 'Ներքնակ 5000 ֏'], ['2026-10-24', null, 'Օրը զբաղված']],
            array_map(fn ($e) => [$e['date'], $e['time'], $e['title']], $events),
        );
        $this->assertSame('Պատվերներ', $events[0]['calendar']);
        $this->assertSame('Կոմիտասի 5', $events[0]['location']);
        // Read in pages, only the chosen calendar.
        $this->assertSame([[null], ['2']], [
            array_column(array_filter($this->google->calls, fn ($c) => $c[0] === 'events' && $c[3] === null), 3),
            array_values(array_column(array_filter($this->google->calls, fn ($c) => $c[0] === 'events' && $c[3] !== null), 3)),
        ]);
    }

    public function test_the_next_sync_reads_only_what_changed(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();

        $this->google->changes['orders@group.calendar.google.com'] = [
            $this->event('e1', 'Բազմոց + 2 բազկաթոռ', '2026-10-22T07:00:00Z', updated: '2026-10-21T09:00:00Z'),
            ['id' => 'e2', 'status' => 'cancelled'],
            $this->event('e4', 'Գորգ', '2026-10-27T10:00:00Z'),
        ];

        $this->postJson('/api/v1/admin/google/sync')->assertOk()
            ->assertJsonPath('data.result', ['calendars' => 1, 'added' => 1, 'updated' => 1, 'removed' => 1]);
        $this->assertSame('changes', collect($this->google->calls)->where(0, 'events')->last()[2]);

        $this->assertSame(
            [['2026-10-22', '11:00', 'Բազմոց + 2 բազկաթոռ'], ['2026-10-24', null, 'Օրը զբաղված'], ['2026-10-27', '14:00', 'Գորգ']],
            array_map(fn ($e) => [$e['date'], $e['time'], $e['title']], $this->calendar()['google']),
        );
    }

    public function test_a_forgotten_sync_token_reads_the_calendar_again_in_full(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();

        $this->google->expiredTokens['orders@group.calendar.google.com'] = true;
        array_shift($this->google->events['orders@group.calendar.google.com']);

        $this->postJson('/api/v1/admin/google/sync')->assertOk()->assertJsonPath('data.result.removed', 1);
        $this->assertSame(['e2', 'e3'], array_column($this->calendar()['google'], 'eventId'));
    }

    public function test_a_calendar_no_longer_chosen_leaves_the_calendar(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $this->assertCount(3, $this->calendar()['google']);

        $this->putJson('/api/v1/admin/google/calendars', ['calendars' => []])->assertOk();

        $this->assertSame([], $this->calendar()['google']);
        $this->assertSame(0, GoogleEvent::withoutTenancy()->count());
    }

    public function test_an_expired_token_is_refreshed_and_a_revoked_one_asks_to_connect_again(): void
    {
        $this->connect();
        $this->travel(2)->hours();

        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $this->assertContains(['refresh', 'refresh-1'], $this->google->calls);

        $this->travel(2)->hours();
        $this->google->refreshRevoked = true;
        $this->postJson('/api/v1/admin/google/sync')->assertStatus(409)->assertJsonPath('code', 'google_reconnect');
        $this->getJson('/api/v1/admin/google')->assertJsonPath('data.connection.needsReconnect', true);
    }

    public function test_events_become_an_order_table_and_leave_the_google_list(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();

        $this->getJson('/api/v1/admin/google/table')->assertOk()
            ->assertJsonPath('data.table', null)
            ->assertJsonPath('data.new', 3);

        $made = $this->postJson('/api/v1/admin/google/table')->assertCreated()->json('data');
        $this->assertTrue($made['created']);
        $this->assertSame('google', $made['table']['source']);
        $this->assertSame('Google օրացույց · 21.10.2026 14:00', $made['table']['name']);
        $this->assertSame(3, $made['table']['rowsCount']);

        $keys = collect($made['table']['columns'])->mapWithKeys(fn ($c) => [$c['role'] => $c['key']]);
        $rows = collect($this->calendar()['rows'])->keyBy(fn ($r) => $r['values'][$keys['item']]);

        $sofa = $rows['Բազմոցի մաքրում'];
        $this->assertSame(['2026-10-22', '10:00', 9900, 'google'], [$sofa['date'], $sofa['time'], (int) $sofa['amount'], $sofa['source']]);
        $this->assertSame('Անի', $sofa['values'][$keys['customer']]);
        $this->assertSame('091 23 45 67', $sofa['values'][$keys['phone']]);
        $this->assertSame('Կոմիտասի 5', $sofa['values'][$keys['address']]);
        $this->assertSame('Մուտքը բակից', $sofa['values'][$keys['note']]);
        // An amount with its currency in the title.
        $this->assertSame(5000, (int) $rows['Ներքնակ 5000 ֏']['amount']);

        // Shown once: as rows, not again as Google events.
        $this->assertSame([], $this->calendar()['google']);

        // Nothing changed: no second table.
        $this->postJson('/api/v1/admin/google/table')->assertOk()->assertJsonPath('data.created', false)->assertJsonPath('data.counts.upToDate', true);
        $this->assertSame(1, DataTable::withoutGlobalScopes()->count());
    }

    public function test_changes_make_a_new_version_and_keep_the_old_one_and_hand_edits(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $first = $this->postJson('/api/v1/admin/google/table')->assertCreated()->json('data.table');
        $keys = collect($first['columns'])->mapWithKeys(fn ($c) => [$c['role'] => $c['key']]);
        $rows = collect($this->getJson("/api/v1/admin/data-tables/{$first['id']}/rows")->json('data'));
        $mattress = $rows->first(fn ($r) => $r['values'][$keys['item']] === 'Ներքնակ 5000 ֏');

        // By hand: the mattress order gets a customer, and an order is added to the table.
        $this->patchJson("/api/v1/admin/data-tables/{$first['id']}/rows/{$mattress['id']}", ['values' => [
            ...$mattress['values'], $keys['customer'] => 'Գոհար',
        ]])->assertOk();
        $this->postJson("/api/v1/admin/data-tables/{$first['id']}/rows", ['values' => [
            $keys['date'] => '2026-10-25 09:00', $keys['item'] => 'Աթոռներ', $keys['amount'] => 2000,
        ]])->assertCreated();

        // In Google: the sofa moved, the mattress renamed, the busy day deleted, a carpet added.
        $this->google->changes['orders@group.calendar.google.com'] = [
            $this->event('e1', 'Բազմոցի մաքրում', '2026-10-22T09:00:00Z', "Հաճախորդ: Անի\nԳին: 9900", updated: '2026-10-21T09:00:00Z'),
            $this->event('e2', 'Ներքնակ 6000 ֏', '2026-10-23T08:30:00Z', updated: '2026-10-21T09:00:00Z'),
            ['id' => 'e3', 'status' => 'cancelled'],
            $this->event('e4', 'Գորգ 3000 դր', '2026-10-27T10:00:00Z'),
        ];
        $this->postJson('/api/v1/admin/google/sync')->assertOk();

        $this->getJson('/api/v1/admin/google/table')->assertOk()
            ->assertJsonPath('data.new', 1)
            ->assertJsonPath('data.changed', 1)
            ->assertJsonPath('data.removed', 1)
            ->assertJsonPath('data.keptEdited', 1)
            ->assertJsonPath('data.upToDate', false);

        $made = $this->postJson('/api/v1/admin/google/table')->assertCreated()->json('data');
        $this->assertSame($first['id'], $made['previous']['id']);
        $second = $made['table'];
        $this->assertNotSame($first['id'], $second['id']);

        // The old version is kept, archived and pointing to the new one.
        $old = DataTable::withoutGlobalScopes()->find($first['id']);
        $this->assertNotNull($old->archived_at);
        $this->assertSame($second['id'], $old->replaced_by_id);
        $this->assertSame(4, DB::table('data_table_rows')->where('data_table_id', $old->id)->count());
        $list = collect($this->getJson('/api/v1/admin/data-tables?section=orders')->json('data'));
        $this->assertSame([$second['id'], $first['id']], $list->pluck('id')->all());
        $this->assertNotNull($list->last()['archivedAt']);
        $this->getJson("/api/v1/admin/data-tables/{$first['id']}/rows")->assertOk();

        // The calendar counts each order once, from the new version.
        $rows = collect($this->calendar()['rows']);
        $this->assertSame([$second['id']], $rows->pluck('tableId')->unique()->values()->all());
        $byItem = $rows->keyBy(fn ($r) => $r['values'][$keys['item']]);
        $this->assertEqualsCanonicalizing(['Բազմոցի մաքրում', 'Ներքնակ 5000 ֏', 'Աթոռներ', 'Գորգ 3000 դր'], $byItem->keys()->all());
        $this->assertSame('13:00', $byItem['Բազմոցի մաքրում']['time']);
        // Edited by hand: kept as it was in the business.
        $this->assertSame('Գոհար', $byItem['Ներքնակ 5000 ֏']['values'][$keys['customer']]);
        $this->assertSame('manual', $byItem['Աթոռներ']['source']);
        $this->assertSame(3000, (int) $byItem['Գորգ 3000 դր']['amount']);
        $this->assertSame([], $this->calendar()['google']);

        // The analytics count the new version only.
        $this->assertSame(2, DataTable::withoutGlobalScopes()->where('source', 'google')->count());
        $this->assertSame(1, DataTable::withoutTenancy()->where('source', 'google')->count());
    }

    public function test_a_google_table_split_by_month_keeps_syncing_into_the_month_tables(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $google = $this->postJson('/api/v1/admin/google/table')->assertCreated()->json('data.table');
        $keys = collect($google['columns'])->mapWithKeys(fn ($c) => [$c['role'] => $c['key']]);

        // Split like any other table: the rows keep their events.
        $this->postJson('/api/v1/admin/table-reshapes', ['ids' => [$google['id']], 'period' => 'month'])->assertCreated();
        $october = DataTable::withoutTenancy()->sole();
        $this->assertNull($october->source);
        $this->assertSame(3, DB::table('data_table_rows')->where('data_table_id', $october->id)->whereNotNull('external_id')->count());
        $this->assertSame([], $this->calendar()['google']);

        // In Google: the sofa moved, a November order added.
        $this->google->changes['orders@group.calendar.google.com'] = [
            $this->event('e1', 'Բազմոցի մաքրում', '2026-10-22T09:00:00Z', "Հաճախորդ: Անի\nԳին: 9900", updated: '2026-10-21T09:00:00Z'),
            $this->event('e4', 'Գորգ 3000 դր', '2026-11-03T10:00:00Z'),
        ];
        $this->postJson('/api/v1/admin/google/sync')->assertOk();

        $this->getJson('/api/v1/admin/google/table')->assertOk()
            ->assertJsonPath('data.table', null)
            ->assertJsonPath('data.spread.0.id', $october->id)
            ->assertJsonPath('data.new', 1)
            ->assertJsonPath('data.changed', 1);

        // No Google table again: the change in place, the new order in November's table.
        $this->postJson('/api/v1/admin/google/table')->assertOk()->assertJsonPath('data.created', false);
        $this->assertSame(0, DataTable::withoutTenancy()->where('source', 'google')->count());
        $tables = DataTable::withoutTenancy()->orderBy('id')->get();
        $this->assertCount(2, $tables);
        $this->assertSame('month', $tables[1]->period->value);
        $this->assertSame('2026-11-01', $tables[1]->period_start->toDateString());

        $rows = collect($this->calendar('2026-10-19', '2026-11-30')['rows'])->keyBy(fn ($r) => $r['values'][$keys['item']]);
        $this->assertSame(['2026-10-22', '13:00'], [$rows['Բազմոցի մաքրում']['date'], $rows['Բազմոցի մաքրում']['time']]);
        $this->assertSame($october->id, $rows['Բազմոցի մաքրում']['tableId']);
        $this->assertSame($tables[1]->id, $rows['Գորգ 3000 դր']['tableId']);
        $this->assertCount(4, $rows);
        $this->assertSame([], $this->calendar('2026-10-19', '2026-11-30')['google']);

        $this->postJson('/api/v1/admin/google/table')->assertOk()->assertJsonPath('data.counts.upToDate', true);
    }

    public function test_a_google_table_is_deleted_with_its_versions_and_copied_orders_show_once(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $first = $this->postJson('/api/v1/admin/google/table')->assertCreated()->json('data.table');
        $this->google->changes['orders@group.calendar.google.com'] = [$this->event('e4', 'Գորգ 3000 դր', '2026-10-27T10:00:00Z')];
        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $second = $this->postJson('/api/v1/admin/google/table')->assertCreated()->json('data.table');

        // A copy of the sofa order, typed by hand into a table of its own.
        $own = $this->postJson('/api/v1/admin/data-tables', ['section' => 'orders', 'name' => 'Հոկտեմբեր', 'columns' => [
            ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
            ['label' => 'Ամսաթիվ', 'type' => 'datetime', 'role' => 'date'],
            ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
        ]])->assertCreated()->json('data');
        [$item, $date, $amount] = array_column($own['columns'], 'key');
        $this->postJson("/api/v1/admin/data-tables/{$own['id']}/rows", ['values' => [$item => 'Բազմոցի մաքրում', $date => '2026-10-22 10:00', $amount => 9900]])->assertCreated();

        // The copy lacks the customer, phone, address: both show, marked for the owner to decide.
        $sofa = collect($this->calendar()['rows'])->where('date', '2026-10-22');
        $this->assertCount(2, $sofa);
        $this->assertNotContains(null, $sofa->pluck('possibleDuplicate')->all());

        $this->deleteJson("/api/v1/admin/data-tables/{$second['id']}")->assertNoContent();

        // The old version went with it.
        $this->assertSame([$own['id']], DataTable::withoutGlobalScopes()->pluck('id')->all());
        $this->assertNull(DataTable::withoutGlobalScopes()->find($first['id']));

        // The sofa is the business's own row now; the other events are Google's again.
        $calendar = $this->calendar();
        $this->assertSame(1, collect($calendar['rows'])->where('date', '2026-10-22')->count());
        $this->assertEqualsCanonicalizing(['e2', 'e3', 'e4'], array_column($calendar['google'], 'eventId'));
    }

    public function test_a_moderator_syncs_but_does_not_make_tables(): void
    {
        $moderator = User::factory()->forTenant($this->salon, 'moderator')->create();
        Sanctum::actingAs($moderator);
        $this->connect();

        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $this->postJson('/api/v1/admin/google/table')->assertForbidden();
    }

    public function test_other_businesses_do_not_see_the_events(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();

        $other = $this->createTenant('other-salon');
        Sanctum::actingAs(User::factory()->forTenant($other, 'owner')->create());

        $this->assertSame([], $this->calendar()['google']);
        $this->getJson('/api/v1/admin/google')->assertJsonPath('data.connection', null)->assertJsonPath('data.pending.events', 0);
        $this->postJson('/api/v1/admin/google/table')->assertStatus(422);
    }

    public function test_disconnecting_forgets_the_account_but_keeps_tables(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/google/sync')->assertOk();
        $table = $this->postJson('/api/v1/admin/google/table')->assertCreated()->json('data.table.id');

        $this->deleteJson('/api/v1/admin/google')->assertNoContent();

        $this->assertContains(['revoke', 'refresh-1'], $this->google->calls);
        $this->assertSame(0, GoogleConnection::withoutTenancy()->count());
        $this->assertSame(0, GoogleEvent::withoutTenancy()->count());
        $this->assertSame(3, DB::table('data_table_rows')->where('data_table_id', $table)->count());
    }
}
