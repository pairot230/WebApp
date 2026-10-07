<?php

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Dorm;
use App\Models\FinancialTransaction;
use App\Models\MembershipPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function financeFixture(): array
{
    $dorm = Dorm::create(['code' => 'H8', 'name' => 'Dorm 8']);
    $year = AcademicYear::create(['year' => 2569, 'is_active' => true]);
    $member = User::factory()->create(['dorm_id' => $dorm->id]);
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $superadmin = User::factory()->create(['role' => 'superadmin', 'dorm_id' => $dorm->id]);

    return compact('dorm', 'year', 'member', 'admin', 'superadmin');
}

function financePayload(array $f, string $type = 'income'): array
{
    return [
        'type' => $type, 'user_id' => $type === 'income' ? $f['member']->id : null,
        'academic_year_id' => $f['year']->id, 'category' => $type === 'income' ? 'membership' : 'Maintenance',
        'title' => $type === 'income' ? 'Annual fee' : 'Repair cost', 'amount' => '100.25',
        'transaction_date' => '2026-10-07', 'description' => 'Internal details',
        'status' => 'posted', 'is_public' => 0,
    ];
}

function financeRecord(array $f, array $overrides = []): FinancialTransaction
{
    return FinancialTransaction::create([
        ...financePayload($f), 'category' => 'other', 'dorm_id' => $f['dorm']->id, 'created_by' => $f['admin']->id, ...$overrides,
    ]);
}

test('staff can create income expense and render finance forms details and totals', function () {
    $f = financeFixture();
    $this->actingAs($f['admin']);
    foreach (['income', 'expense'] as $type) {
        $this->get('/admin/finance/create?type='.$type)->assertOk()->assertSee('บันทึกข้อมูล');
        $this->post('/admin/finance', financePayload($f, $type))->assertRedirect();
        $finance = FinancialTransaction::latest('id')->first();
        expect((int) $finance->created_by)->toBe($f['admin']->id)->and($finance->dorm_id)->toBe($f['dorm']->id);
        $this->get('/admin/finance/'.$finance->id)->assertOk();
        $this->get('/admin/finance/'.$finance->id.'/edit')->assertOk();
    }
    $this->get('/admin/finance')->assertOk()->assertViewHas('totals', ['income' => '100.25', 'expense' => '100.25', 'balance' => '0.00']);
    $payment = MembershipPayment::first();
    expect($payment->status)->toBe('paid')->and($payment->amount)->toBe('100.25')
        ->and($payment->paid_at->format('Y-m-d H:i'))->toBe('2026-10-06 17:00');
    expect(AuditLog::where('action', 'finance.created')->count())->toBe(2);
});

test('finance validates positive exact cents valid dates member year and protected fields', function () {
    $f = financeFixture();
    $this->actingAs($f['admin']);
    foreach (['0', '-1', '1.234', '1e2', '10000000000.00', 'invalid', ['invalid']] as $amount) {
        $this->postJson('/admin/finance', [...financePayload($f), 'amount' => $amount])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }
    foreach (['user_id' => 99999, 'academic_year_id' => 99999, 'transaction_date' => '2026-02-30', 'status' => 'voided', 'type' => 'unknown', 'category' => 'unknown'] as $field => $value) {
        $this->postJson('/admin/finance', [...financePayload($f), $field => $value])->assertUnprocessable();
    }
    foreach (['dorm_id', 'created_by', 'voided_by', 'voided_at', 'void_reason'] as $field) {
        $this->postJson('/admin/finance', [...financePayload($f), $field => '1'])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->postJson('/admin/finance', [...financePayload($f, 'expense'), 'user_id' => $f['member']->id])->assertUnprocessable();
    expect(FinancialTransaction::count())->toBe(0)->and(AuditLog::count())->toBe(0);
});

test('currency totals are exact to cents including maximum amount and negative balance', function () {
    $f = financeFixture();
    $this->actingAs($f['admin']);
    foreach (['0.1', '0.20', '9999999999.99'] as $amount) {
        $this->post('/admin/finance', [...financePayload($f), 'category' => 'other', 'amount' => $amount])->assertRedirect();
    }
    $this->get('/admin/finance')->assertViewHas('totals', ['income' => '10000000000.29', 'expense' => '0.00', 'balance' => '10000000000.29']);
    $this->post('/admin/finance', [...financePayload($f, 'expense'), 'amount' => '9999999999.99'])->assertRedirect();
    $this->post('/admin/finance', [...financePayload($f, 'expense'), 'amount' => '0.31'])->assertRedirect();
    $this->get('/admin/finance')->assertViewHas('totals', ['income' => '10000000000.29', 'expense' => '10000000000.30', 'balance' => '-0.01']);
});

test('members see only published posted items and public totals without personal or internal details', function () {
    $f = financeFixture();
    $public = financeRecord($f, ['title' => 'PUBLIC INCOME', 'amount' => '120.10', 'is_public' => true]);
    financeRecord($f, ['type' => 'expense', 'user_id' => null, 'title' => 'PUBLIC EXPENSE', 'amount' => '20.05', 'is_public' => true]);
    financeRecord($f, ['title' => 'PRIVATE SECRET', 'amount' => '999.00']);
    financeRecord($f, ['title' => 'PENDING SECRET', 'is_public' => true, 'status' => 'pending']);
    financeRecord($f, ['title' => 'VOID SECRET', 'is_public' => true, 'status' => 'voided']);
    $this->actingAs($f['member'])->get('/finance?is_public=0&status=pending&user_id='.$f['admin']->id)
        ->assertOk()->assertSee('PUBLIC INCOME')->assertSee('PUBLIC EXPENSE')->assertDontSee('PRIVATE SECRET')
        ->assertDontSee('PENDING SECRET')->assertDontSee('VOID SECRET')->assertDontSee('Internal details')
        ->assertDontSee($f['admin']->name)->assertViewHas('totals', ['income' => '120.10', 'expense' => '20.05', 'balance' => '100.05']);
    $this->get('/admin/finance/'.$public->id)->assertForbidden();
    $this->get('/finance/'.$public->id)->assertNotFound();
});

test('year filtering scopes totals and pending voided entries never count', function () {
    $f = financeFixture();
    financeRecord($f, ['is_public' => true, 'amount' => '10.00']);
    $next = AcademicYear::create(['year' => 2570]);
    financeRecord($f, ['academic_year_id' => $next->id, 'is_public' => true, 'amount' => '50.00', 'title' => 'NEXT YEAR']);
    financeRecord($f, ['status' => 'pending', 'amount' => '900.00']);
    financeRecord($f, ['status' => 'voided', 'amount' => '800.00']);
    $totals = ['income' => '10.00', 'expense' => '0.00', 'balance' => '10.00'];
    $this->actingAs($f['admin'])->get('/admin/finance?academic_year_id='.$f['year']->id)->assertOk()->assertViewHas('totals', $totals)->assertDontSee('NEXT YEAR');
    $this->actingAs($f['member'])->get('/finance?academic_year_id='.$f['year']->id)->assertOk()->assertViewHas('totals', $totals)->assertDontSee('NEXT YEAR');
    $this->getJson('/finance?academic_year_id=99999')->assertUnprocessable();
});

test('membership fee can be posted once per member year including pending reservations', function () {
    $f = financeFixture();
    $this->actingAs($f['admin']);
    $data = [...financePayload($f), 'status' => 'pending'];
    $this->post('/admin/finance', $data)->assertRedirect();
    $finance = FinancialTransaction::first();
    expect(MembershipPayment::first()->status)->toBe('pending')->and(MembershipPayment::first()->paid_at)->toBeNull();
    $this->postJson('/admin/finance', financePayload($f))->assertUnprocessable();
    expect(FinancialTransaction::count())->toBe(1);
    $this->put('/admin/finance/'.$finance->id, financePayload($f))->assertRedirect();
    expect(MembershipPayment::first()->status)->toBe('paid');
    $this->postJson('/admin/finance', financePayload($f))->assertUnprocessable();
    $next = AcademicYear::create(['year' => 2570]);
    $this->post('/admin/finance', [...financePayload($f), 'academic_year_id' => $next->id])->assertRedirect();
    expect(MembershipPayment::count())->toBe(2);
});

test('financial edits synchronize payment and record old new audit while no-op creates none', function () {
    $f = financeFixture();
    $this->actingAs($f['admin'])->post('/admin/finance', financePayload($f))->assertRedirect();
    $finance = FinancialTransaction::first();
    $before = AuditLog::count();
    $this->put('/admin/finance/'.$finance->id, financePayload($f))->assertRedirect();
    expect(AuditLog::count())->toBe($before);
    $updated = [...financePayload($f), 'amount' => '150.50', 'title' => 'Corrected fee', 'is_public' => 1];
    $this->put('/admin/finance/'.$finance->id, $updated)->assertRedirect();
    expect($finance->fresh()->amount)->toBe('150.50')->and(MembershipPayment::first()->amount)->toBe('150.50');
    $audit = AuditLog::where('action', 'finance.updated')->first();
    expect($audit->old_values['amount'])->toBe('100.25')->and($audit->new_values['amount'])->toBe('150.50')
        ->and($audit->new_values['is_public'])->toBeTrue();
});

test('finance identities cannot be moved and posted records require reasoned void instead of pending', function () {
    $f = financeFixture();
    $this->actingAs($f['admin'])->post('/admin/finance', financePayload($f))->assertRedirect();
    $finance = FinancialTransaction::first();
    $peer = User::factory()->create(['dorm_id' => $f['dorm']->id]);
    $next = AcademicYear::create(['year' => 2570]);
    foreach (['user_id' => $peer->id, 'academic_year_id' => $next->id, 'category' => 'other', 'status' => 'pending'] as $field => $value) {
        $this->putJson('/admin/finance/'.$finance->id, [...financePayload($f), $field => $value])->assertUnprocessable();
    }
    $this->putJson('/admin/finance/'.$finance->id, financePayload($f, 'expense'))->assertUnprocessable();
    expect(MembershipPayment::count())->toBe(1)->and($finance->fresh()->status)->toBe('posted');
});

test('void preserves transaction and audit excludes balance permits corrected annual replacement', function () {
    $f = financeFixture();
    $this->actingAs($f['admin'])->post('/admin/finance', financePayload($f))->assertRedirect();
    $finance = FinancialTransaction::first();
    $this->postJson('/admin/finance/'.$finance->id.'/void', [])->assertUnprocessable();
    $this->post('/admin/finance/'.$finance->id.'/void', ['void_reason' => 'Wrong amount', 'voided_by' => $f['member']->id])->assertRedirect();
    expect($finance->fresh()->status)->toBe('voided')->and((int) $finance->fresh()->voided_by)->toBe($f['admin']->id)
        ->and($finance->fresh()->void_reason)->toBe('Wrong amount')->and(MembershipPayment::first()->status)->toBe('voided');
    $this->get('/admin/finance/'.$finance->id)->assertOk()->assertSee('Wrong amount');
    $this->get('/admin/finance/'.$finance->id.'/edit')->assertConflict();
    $this->putJson('/admin/finance/'.$finance->id, financePayload($f))->assertConflict();
    $this->postJson('/admin/finance/'.$finance->id.'/void', ['void_reason' => 'Again'])->assertConflict();
    $this->deleteJson('/admin/finance/'.$finance->id)->assertStatus(405);
    $this->get('/admin/finance')->assertViewHas('totals', ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00']);
    $this->post('/admin/finance', [...financePayload($f), 'amount' => '99.99', 'status' => 'pending'])->assertRedirect();
    expect(FinancialTransaction::count())->toBe(2)->and(MembershipPayment::count())->toBe(1)
        ->and(MembershipPayment::first()->paid_at)->toBeNull();
    expect(AuditLog::where('action', 'finance.voided')->count())->toBe(1);
});

test('unlinked pending annual payment is collected without duplicate record', function () {
    $f = financeFixture();
    MembershipPayment::create(['user_id' => $f['member']->id, 'dorm_id' => $f['dorm']->id, 'academic_year_id' => $f['year']->id, 'amount' => '100.25', 'status' => 'pending']);
    $this->actingAs($f['admin'])->post('/admin/finance', financePayload($f))->assertRedirect();
    expect(MembershipPayment::count())->toBe(1)->and(MembershipPayment::first()->status)->toBe('paid');
});

test('finance rejects users guests cross dorm protected accounts and revoked admin privileges', function () {
    $f = financeFixture();
    $record = financeRecord($f);
    $this->get('/admin/finance')->assertRedirect(route('login'));
    $this->get('/finance')->assertRedirect(route('login'));
    $otherDorm = Dorm::create(['code' => 'OTHER', 'name' => 'Other']);
    $outsider = User::factory()->create(['role' => 'admin', 'dorm_id' => $otherDorm->id]);
    foreach ([$f['member'], $outsider] as $actor) {
        $this->actingAs($actor)->get('/admin/finance/'.$record->id)->assertForbidden();
        $this->putJson('/admin/finance/'.$record->id, financePayload($f))->assertForbidden();
        $this->postJson('/admin/finance/'.$record->id.'/void', ['void_reason' => 'Forged'])->assertForbidden();
    }
    $this->actingAs($f['member'])->postJson('/admin/finance', financePayload($f))->assertForbidden();
    $protected = financeRecord($f, ['user_id' => $f['superadmin']->id, 'title' => 'SUPERADMIN SECRET']);
    $this->actingAs($f['admin'])->get('/admin/finance')->assertOk()->assertDontSee('SUPERADMIN SECRET');
    $this->get('/admin/finance/'.$protected->id)->assertForbidden();
    $this->postJson('/admin/finance', [...financePayload($f), 'user_id' => $f['superadmin']->id])->assertForbidden();
    $this->actingAs($f['superadmin'])->get('/admin/finance/'.$protected->id)->assertOk();
    $this->post('/admin/finance', [...financePayload($f), 'user_id' => $f['superadmin']->id])->assertRedirect();
    User::whereKey($f['admin']->id)->update(['role' => 'user']);
    $this->actingAs($f['admin'])->get('/admin/finance')->assertForbidden();
});

test('finance cannot receive payments from inactive or other dorm members and public data remains isolated', function () {
    $f = financeFixture();
    $otherDorm = Dorm::create(['code' => 'OTHER', 'name' => 'Other']);
    $outsider = User::factory()->create(['dorm_id' => $otherDorm->id]);
    $inactive = User::factory()->create(['dorm_id' => $f['dorm']->id, 'is_active' => false]);
    $this->actingAs($f['admin']);
    foreach ([$outsider, $inactive] as $member) {
        $this->postJson('/admin/finance', [...financePayload($f), 'user_id' => $member->id])->assertUnprocessable();
    }
    financeRecord($f, ['dorm_id' => $otherDorm->id, 'user_id' => $outsider->id, 'is_public' => true, 'title' => 'OTHER DORM SECRET']);
    $this->actingAs($f['member'])->get('/finance?dorm_id='.$otherDorm->id)->assertOk()->assertDontSee('OTHER DORM SECRET')
        ->assertViewHas('totals', ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00']);
});

test('finance and membership writes rollback fully if any audit fails including void updates', function () {
    $f = financeFixture();
    $this->actingAs($f['admin'])->post('/admin/finance', financePayload($f))->assertRedirect();
    $record = FinancialTransaction::first();
    $before = AuditLog::count();
    $next = AcademicYear::create(['year' => 2570]);
    AuditLog::creating(function (AuditLog $log): void {
        if (str_starts_with($log->action, 'finance.')) {
            throw new RuntimeException('Audit failure');
        }
    });
    try {
        $this->post('/admin/finance', financePayload($f, 'expense'))->assertStatus(500);
        $this->post('/admin/finance', [...financePayload($f), 'academic_year_id' => $next->id])->assertStatus(500);
        $this->put('/admin/finance/'.$record->id, [...financePayload($f), 'amount' => '500.00'])->assertStatus(500);
        $this->post('/admin/finance/'.$record->id.'/void', ['void_reason' => 'Test'])->assertStatus(500);
        expect(FinancialTransaction::count())->toBe(1)->and($record->fresh()->amount)->toBe('100.25')
            ->and($record->fresh()->status)->toBe('posted')->and(MembershipPayment::first()->amount)->toBe('100.25')
            ->and(MembershipPayment::first()->status)->toBe('paid')->and(MembershipPayment::count())->toBe(1)->and(AuditLog::count())->toBe($before);
    } finally {
        AuditLog::flushEventListeners();
    }
});

test('finance search status filters and pagination preserve scope while totals remain year totals', function () {
    $f = financeFixture();
    financeRecord($f, ['title' => 'Matched', 'amount' => '10.00']);
    financeRecord($f, ['title' => 'Hidden by search', 'amount' => '20.00']);
    financeRecord($f, ['title' => 'Pending matched', 'status' => 'pending']);
    $this->actingAs($f['admin'])->get('/admin/finance?search=Matched&status=posted')->assertOk()->assertSee('Matched')
        ->assertDontSee('Hidden by search')->assertDontSee('Pending matched')
        ->assertViewHas('totals', ['income' => '30.00', 'expense' => '0.00', 'balance' => '30.00']);
});

test('finance forms and all financial write endpoints enforce CSRF', function () {
    $f = financeFixture();
    $record = financeRecord($f);
    $this->app['env'] = 'production';
    $this->actingAs($f['admin'])->postJson('/admin/finance', financePayload($f))->assertStatus(419);
    $this->putJson('/admin/finance/'.$record->id, financePayload($f))->assertStatus(419);
    $this->postJson('/admin/finance/'.$record->id.'/void', ['void_reason' => 'Test'])->assertStatus(419);
    expect(AuditLog::count())->toBe(0);
});

test('historic income remains editable after member moves or becomes inactive without changing owner', function () {
    $f = financeFixture();
    $this->actingAs($f['admin'])->post('/admin/finance', financePayload($f))->assertRedirect();
    $record = FinancialTransaction::first();
    $other = Dorm::create(['code' => 'MOVE', 'name' => 'Moved Dorm']);
    $f['member']->dorm_id = $other->id;
    $f['member']->is_active = false;
    $f['member']->save();
    $this->get('/admin/finance/'.$record->id.'/edit')->assertOk()->assertSee($f['member']->name);
    $this->put('/admin/finance/'.$record->id, [...financePayload($f), 'amount' => '90.00'])->assertRedirect();
    expect($record->fresh()->dorm_id)->toBe($f['dorm']->id)->and($record->fresh()->user_id)->toBe($f['member']->id)
        ->and(MembershipPayment::first()->amount)->toBe('90.00');
});

test('expense edit and public disclosure change are audited and escaped', function () {
    $f = financeFixture();
    $this->actingAs($f['admin'])->post('/admin/finance', financePayload($f, 'expense'))->assertRedirect();
    $record = FinancialTransaction::first();
    $data = [...financePayload($f, 'expense'), 'title' => '<script>alert(1)</script>', 'category' => 'Supplies', 'amount' => '30.00', 'is_public' => 1];
    $this->put('/admin/finance/'.$record->id, $data)->assertRedirect();
    $audit = AuditLog::where('action', 'finance.updated')->first();
    expect($audit->old_values['category'])->toBe('Maintenance')->and($audit->new_values['category'])->toBe('Supplies');
    $this->actingAs($f['member'])->get('/finance')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertViewHas('totals', ['income' => '0.00', 'expense' => '30.00', 'balance' => '-30.00']);
    $this->actingAs($f['admin'])->put('/admin/finance/'.$record->id, [...$data, 'is_public' => 0])->assertRedirect();
    $this->actingAs($f['member'])->get('/finance')->assertDontSee('alert(1)')
        ->assertViewHas('totals', ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00']);
});
