<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Models\AdminNotification;
use App\Models\Candidate;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\JobOpening;
use App\Models\Tenant;
use App\Models\User;
use App\Team\HrRefused;
use App\Team\HrTools;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Anya, the HR agent: absences in the calendar and the bell, job openings,
 * candidates and their interviews, hiring with an onboarding checklist.
 * Every change of the team is a card the owner applies, discards or undoes.
 */
class HrAgentTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private FakeAssistantClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-27 10:00:00');
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop, 'owner')->create();
        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/hr", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
        app(TenantContext::class)->set($this->shop);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ask(array $calls): array
    {
        $this->client->toolCalls = $calls;
        $this->client->toolResults = [];

        return $this->postJson('/api/v1/admin/assistant', ['question' => 'Արա', 'agent' => 'hr'])->assertOk()->json('data');
    }

    private function apply(array $reply, int $card = 0): array
    {
        $id = $reply['actions'][$card]['id'];
        $this->assertSame('hr', $reply['actions'][$card]['type']);

        return $this->postJson("/api/v1/admin/hr-changes/{$id}/apply")->assertOk()->json('data');
    }

    private function arman(): Employee
    {
        $id = $this->postJson('/api/v1/admin/employees', ['name' => 'Արման Սարգսյան', 'position' => 'Վարպետ'])->assertCreated()->json('data.id');

        return Employee::query()->findOrFail($id);
    }

    public function test_anya_has_the_hr_tools_and_reads_the_team(): void
    {
        $this->arman();
        $this->ask([['team_overview', []]]);

        $names = array_column($this->client->toolDefinitions, 'name');
        foreach (HrTools::NAMES as $tool) {
            $this->assertContains($tool, $names);
        }
        $this->assertNotContains('add_rows', $names);
        $this->assertStringContainsString('You are Anya', $this->client->system);
        $this->assertSame('Արման Սարգսյան', $this->client->toolResults[0]['employees'][0]['name']);
    }

    public function test_a_vacation_waits_for_the_press_warns_of_orders_and_shows_in_the_calendar(): void
    {
        $arman = $this->arman();
        $table = DataTable::create(['section' => 'orders', 'name' => 'Պատվերներ', 'columns' => [
            ['key' => 'd', 'label' => 'Ամսաթիվ', 'type' => 'date', 'required' => false, 'options' => [], 'role' => 'date'],
            ['key' => 'p', 'label' => 'Կատարող', 'type' => 'text', 'required' => false, 'options' => [], 'role' => 'performer'],
        ]]);
        $row = DataTableRow::make(['data_table_id' => $table->id, 'data' => ['d' => '2026-11-05', 'p' => 'Արման Սարգսյան']]);
        $row->setRelation('table', $table)->save();
        $this->assertSame($arman->id, $row->fresh()->employee_id);

        $reply = $this->ask([['add_absence', ['employee' => 'Արման', 'kind' => 'vacation', 'from' => '2026-11-03', 'to' => '2026-11-14']]]);
        $this->assertSame([['date' => '2026-11-05', 'count' => 1]], $this->client->toolResults[0]['ordersOnThoseDays']);
        $this->assertSame(0, EmployeeAbsence::query()->count());

        $card = $this->getJson("/api/v1/admin/hr-changes/{$reply['actions'][0]['id']}")->assertOk()->json('data');
        $this->assertSame('Արձակուրդ՝ Արման Սարգսյան', $card['title']);
        $this->assertSame([
            'Արման Սարգսյան՝ արձակուրդ, 03.11.2026–14.11.2026 (12 օր)',
            'Ուշադրություն՝ այդ օրերին Արման Սարգսյան-ի անունով 1 պատվեր կա. փոխանցեք ուրիշին։',
        ], $card['lines']);

        $this->apply($reply);
        $this->assertSame(1, EmployeeAbsence::query()->count());

        $this->getJson('/api/v1/admin/calendar?from=2026-11-01&to=2026-11-30')->assertOk()
            ->assertJsonPath('data.absences.0.employee.name', 'Արման Սարգսյան')
            ->assertJsonPath('data.absences.0.days', 12);

        // The same days again are refused.
        try {
            app(HrTools::class)->run('add_absence', ['employee' => $arman->id, 'kind' => 'day_off', 'from' => '2026-11-10', 'to' => '2026-11-10']);
            $this->fail('An overlapping absence was planned.');
        } catch (HrRefused $e) {
            $this->assertStringContainsString('արդեն բացակա է', $e->getMessage());
        }

        $this->postJson("/api/v1/admin/hr-changes/{$reply['actions'][0]['id']}/undo")->assertOk()->assertJsonPath('data.status', 'undone');
        $this->assertSame(0, EmployeeAbsence::query()->count());
    }

    public function test_the_bell_says_who_is_away_today_and_tomorrow_once_a_day(): void
    {
        $arman = $this->arman();
        $gor = Employee::create(['name' => 'Գոռ', 'birth_date' => '1995-10-27']);
        EmployeeAbsence::create(['employee_id' => $arman->id, 'kind' => 'sick', 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-28']);
        EmployeeAbsence::create(['employee_id' => $gor->id, 'kind' => 'day_off', 'starts_on' => '2026-10-28', 'ends_on' => '2026-10-28']);
        Candidate::create(['name' => 'Լուսինե', 'interview_at' => '2026-10-27 15:00:00']);

        $this->getJson('/api/v1/admin/notifications')->assertOk()
            ->assertJsonFragment(['type' => 'team', 'title' => 'Այսօր բացակա են՝ 1'])
            ->assertJsonFragment(['body' => 'Այսօր բացակա են՝ Արման Սարգսյան (հիվանդություն մինչև 28.10). Վաղվանից՝ Գոռ (հանգստյան օր). Հարցազրույցներ՝ 15:00 Լուսինե. Ծննդյան օր՝ Գոռ']);
        $this->getJson('/api/v1/admin/notifications')->assertOk();
        $this->assertSame(1, AdminNotification::query()->where('type', 'team')->count());

        // A moderator hears who is away, not the interviews or birthdays.
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->getJson('/api/v1/admin/notifications')->assertOk()
            ->assertJsonFragment(['body' => 'Այսօր բացակա են՝ Արման Սարգսյան (հիվանդություն մինչև 28.10). Վաղվանից՝ Գոռ (հանգստյան օր)']);
        $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2026-10-31')->assertOk()
            ->assertJsonCount(2, 'data.absences')
            ->assertJsonCount(0, 'data.interviews');
    }

    public function test_an_opening_a_candidate_and_the_hire_with_onboarding(): void
    {
        $reply = $this->ask([['save_job_opening', ['title' => 'Քիմմաքրման վարպետ', 'salary' => '250 000 դր.', 'employment' => 'full_time', 'description' => 'Պարտականություններ…']]]);
        $this->apply($reply);
        $opening = JobOpening::query()->sole();
        $this->assertSame('open', $opening->status);

        $reply = $this->ask([['save_candidate', ['opening' => $opening->id, 'name' => 'Լուսինե Պետրոսյան', 'phone' => '055 11 22 33', 'source' => 'staff.am', 'stage' => 'interview', 'interview_at' => '2026-10-30 15:00']]]);
        $this->apply($reply);
        $candidate = Candidate::query()->sole();
        $this->assertSame('2026-10-30 15:00', $candidate->interviewTime());

        // The AI never reads the contacts back.
        $this->ask([['list_hiring', []]]);
        $read = $this->client->toolResults[0]['openings'][0]['candidates'][0];
        $this->assertArrayNotHasKey('phone', $read);
        $this->assertTrue($read['hasContacts']);

        $this->getJson('/api/v1/admin/calendar?from=2026-10-26&to=2026-11-01')->assertOk()
            ->assertJsonPath('data.interviews.0', ['id' => $candidate->id, 'date' => '2026-10-30', 'time' => '15:00', 'name' => 'Լուսինե Պետրոսյան', 'opening' => 'Քիմմաքրման վարպետ', 'stage' => 'interview']);

        $reply = $this->ask([['hire_candidate', ['candidate' => $candidate->id, 'hired_on' => '2026-11-02']]]);
        $card = $this->getJson("/api/v1/admin/hr-changes/{$reply['actions'][0]['id']}")->json('data');
        $this->assertSame('Ընդունել աշխատանքի՝ Լուսինե Պետրոսյան', $card['title']);
        $this->assertContains('Թեկնածուի փուլ՝ Ընդունված', $card['lines']);
        $this->apply($reply);

        $employee = Employee::query()->sole();
        $this->assertSame(['Լուսինե Պետրոսյան', 'Քիմմաքրման վարպետ', '055 11 22 33', '2026-11-02'], [$employee->name, $employee->position, $employee->phone, $employee->hired_on->toDateString()]);
        $this->assertCount(5, $employee->onboarding);
        $this->assertSame(['hired', $employee->id], [$candidate->fresh()->stage->value, $candidate->fresh()->employee_id]);

        // Undo takes both back.
        $this->postJson("/api/v1/admin/hr-changes/{$reply['actions'][0]['id']}/undo")->assertOk();
        $this->assertSame(0, Employee::query()->count());
        $this->assertSame(['interview', null], [$candidate->fresh()->stage->value, $candidate->fresh()->employee_id]);
    }

    public function test_changes_show_old_and_new_and_stale_cards_are_refused(): void
    {
        $arman = $this->arman();
        $reply = $this->ask([['save_employee', ['id' => $arman->id, 'position' => 'Ավագ վարպետ', 'birth_date' => '1990-05-12']]]);
        $id = $reply['actions'][0]['id'];
        $this->getJson("/api/v1/admin/hr-changes/{$id}")->assertJsonPath('data.lines', ['Պաշտոն՝ Վարպետ → Ավագ վարպետ', 'Ծննդյան օր՝ — → 12.05.1990']);

        $this->patchJson("/api/v1/admin/employees/{$arman->id}", ['position' => 'Մենեջեր'])->assertOk();
        $this->getJson("/api/v1/admin/hr-changes/{$id}")->assertJsonPath('data.stale', 'Տվյալներն այդ ընթացքում փոխվել են։ Խնդրեք նորից։');
        $this->postJson("/api/v1/admin/hr-changes/{$id}/apply")->assertUnprocessable()->assertJsonPath('code', 'change_refused');
        $this->assertSame('Մենեջեր', $arman->fresh()->position);

        $reply = $this->ask([['set_onboarding', ['employee' => $arman->id, 'items' => [['title' => 'Պայմանագիր', 'done' => true], ['title' => 'Հագուստ']]]]]);
        $this->postJson("/api/v1/admin/hr-changes/{$reply['actions'][0]['id']}/discard")->assertOk()->assertJsonPath('data.status', 'discarded');
        $this->assertNull($arman->fresh()->onboarding);
    }

    public function test_absences_and_hiring_by_hand_and_only_for_those_who_manage_the_team(): void
    {
        $arman = $this->arman();
        $id = $this->postJson('/api/v1/admin/team/absences', ['employeeId' => $arman->id, 'kind' => 'day_off', 'from' => '2026-10-29', 'to' => '2026-10-29'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/team/absences', ['employeeId' => $arman->id, 'kind' => 'vacation', 'from' => '2026-10-25', 'to' => '2026-10-30'])
            ->assertUnprocessable();
        $this->getJson('/api/v1/admin/team/absences')->assertOk()->assertJsonPath('data.absences.0.kindLabel', 'Հանգստյան օր');

        $opening = $this->postJson('/api/v1/admin/team/openings', ['title' => 'Վարորդ'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/team/candidates', ['name' => 'Վահե', 'openingId' => $opening, 'interviewAt' => '2026-10-31 11:00'])->assertCreated()
            ->assertJsonPath('data.interviewAt', '2026-10-31 11:00')
            ->assertJsonPath('data.stage', 'new');
        $this->getJson('/api/v1/admin/team/hiring')->assertOk()->assertJsonCount(1, 'data.candidates')->assertJsonCount(6, 'data.stages');

        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->getJson('/api/v1/admin/team/hiring')->assertForbidden();
        $this->deleteJson("/api/v1/admin/team/absences/{$id}")->assertForbidden();

        $other = $this->createTenant('other-shop');
        Sanctum::actingAs(User::factory()->forTenant($other, 'owner')->create());
        $this->deleteJson("/api/v1/admin/team/absences/{$id}")->assertNotFound();
        $this->assertSame(1, EmployeeAbsence::withoutTenancy()->count());
    }
}
