<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\MessageMembers;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\RelationManagers\PaymentsRelationManager;
use App\Filament\Widgets\BirthdaysWidget;
use App\Models\MemberPayment;
use App\Models\User;
use App\Services\Birthdays;
use App\Services\Membership;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 09:00');
    $this->seed(ClubSeeder::class);
    $this->admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    $settings = app(Settings::class);
    $settings->set('client.name.short', 'LCE');
    $settings->set(Membership::TYPES_OPTION, "Full: 1200\nSocial: 450,50\nLife");
});

/** A member with a name, number and membership details in meta. */
function clubMember(string $first, string $last, ?string $phone, array $meta = []): User
{
    $user = User::factory()->create(['alias' => "$first $last", 'phone' => $phone]);
    $user->setMeta('firstname', $first);
    $user->setMeta('lastname', $last);

    foreach ($meta as $key => $value) {
        $user->setMeta($key, $value);
    }

    return $user->fresh();
}

function paid(User $user, int $year, string $amount = '1200'): MemberPayment
{
    return MemberPayment::query()->create([
        'uid' => $user->uid, 'paid_on' => '2026-02-01', 'year' => $year, 'amount' => $amount, 'method' => 'eft',
    ]);
}

it('reads membership types and fees from the settings', function () {
    $membership = app(Membership::class);

    expect($membership->types())->toBe(['Full' => 1200.0, 'Social' => 450.5, 'Life' => null])
        ->and($membership->typeOptions())->toBe(['Full' => 'Full (R1 200.00)', 'Social' => 'Social (R450.50)', 'Life' => 'Life'])
        ->and($membership->feeFor('Full'))->toBe(1200.0)
        ->and($membership->feeFor('Unknown'))->toBeNull();

    app(Settings::class)->set(Membership::TYPES_OPTION, null);

    expect(array_keys($membership->types()))->toBe(['Full', 'Social', 'Junior', 'Life']);
});

it('runs the membership year from the month the club chooses', function () {
    $membership = app(Membership::class);

    expect($membership->currentYear())->toBe(2026)
        ->and($membership->yearLabel(2026))->toBe('2026');

    app(Settings::class)->set(Membership::YEAR_START_OPTION, '11'); // November to October

    expect($membership->currentYear())->toBe(2025)
        ->and($membership->yearLabel(2025))->toBe('2025/26')
        ->and($membership->currentYear(Carbon::parse('2026-11-01')))->toBe(2026);
});

it('fills in each member\'s name and fee, or "everyone" for a group', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567', [Membership::TYPE => 'Full']);

    expect(app(Membership::class)->personalise('Hi {name}, your {fee} for {club} is due.', $jan))
        ->toBe('Hi Jan, your R1 200.00 for LCE is due.')
        ->and(app(Membership::class)->personalise('Hi {name}, your {fee} is due.', null))
        ->toBe('Hi everyone, your the membership fee is due.');
});

it('saves a member\'s membership type, gender and birthday, and shows them again', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567');
    $this->actingAs($this->admin);

    Livewire::test(EditMember::class, ['record' => $jan->uid])
        ->fillForm(['membership' => 'Full', 'joined' => '1998-03-01', 'gender' => 'male', 'birthday' => '1956-10-05'])
        ->call('save')
        ->assertHasNoFormErrors();

    $jan = $jan->fresh();
    expect($jan->meta(Membership::TYPE))->toBe('Full')
        ->and($jan->meta(Membership::JOINED))->toBe('1998-03-01')
        ->and($jan->meta(Membership::GENDER))->toBe('male')
        ->and($jan->meta(Membership::BIRTHDAY))->toBe('1956-10-05');

    Livewire::test(EditMember::class, ['record' => $jan->uid])
        ->assertFormSet(['membership' => 'Full', 'gender' => 'male', 'birthday' => '1956-10-05']);
});

it('records a payment with this year and the member\'s fee suggested', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567', [Membership::TYPE => 'Full']);
    $this->actingAs($this->admin);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $jan, 'pageClass' => EditMember::class])
        ->assertSee('Nothing paid for 2026 yet.')
        ->mountTableAction('create')
        ->assertTableActionDataSet(['year' => 2026, 'amount' => 1200.0, 'method' => 'eft'])
        ->setTableActionData(['reference' => 'EFT 123'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $payment = MemberPayment::query()->sole();
    expect($payment->uid)->toBe($jan->uid)
        ->and($payment->year)->toBe(2026)
        ->and($payment->amount)->toBe('1200.00')
        ->and($payment->reference)->toBe('EFT 123')
        ->and($payment->recorded_by)->toBe($this->admin->uid)
        ->and(app(Membership::class)->hasPaid($jan))->toBeTrue();
});

it('shows who has paid on the members list and filters by it', function () {
    $paidUp = clubMember('Jan', 'Botha', '+27821234567', [Membership::TYPE => 'Full']);
    $owing = clubMember('Anna', 'Venter', '+27831112222', [Membership::TYPE => 'Social']);
    paid($paidUp, 2026);
    paid($owing, 2025);

    $this->actingAs($this->admin);

    Livewire::test(ListMembers::class)
        ->assertTableColumnStateSet('paid_current', true, $paidUp)
        ->assertTableColumnStateSet('paid_current', false, $owing)
        ->assertTableColumnStateSet('membership', 'Social', $owing)
        ->filterTable('paid', false)
        ->assertCanSeeTableRecords([$owing])
        ->assertCanNotSeeTableRecords([$paidUp])
        ->resetTableFilters()
        ->filterTable('membership', 'Full')
        ->assertCanSeeTableRecords([$paidUp])
        ->assertCanNotSeeTableRecords([$owing]);
});

it('opens a WhatsApp chat with a member from the list', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567');
    $this->actingAs($this->admin);

    Livewire::test(ListMembers::class)
        ->assertTableActionHasUrl('whatsapp', 'https://wa.me/27821234567', $jan)
        ->assertTableActionHidden('whatsapp', $this->admin); // no cellphone number
});

it('sends the members ticked on the list to the message page', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567');
    $anna = clubMember('Anna', 'Venter', '+27831112222');
    $this->actingAs($this->admin);

    Livewire::test(ListMembers::class)
        ->callTableBulkAction('message', [$jan, $anna])
        ->assertRedirect(MessageMembers::getUrl(['members' => $jan->uid.','.$anna->uid]));
});

it('prepares a personal WhatsApp message for each member, and one for a group', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567', [Membership::TYPE => 'Full']);
    $anna = clubMember('Anna', 'Venter', '+27831112222', [Membership::TYPE => 'Social']);
    $noPhone = clubMember('Piet', 'Pompies', null);
    paid($anna, 2026);

    $this->actingAs($this->admin);

    $page = Livewire::test(MessageMembers::class)
        ->fillForm(['recipients' => 'unpaid', 'message' => 'Hi {name}, your {fee} subscription is due.'])
        ->call('prepare')
        ->assertSee('Jan Botha')
        ->assertDontSee('Anna Venter') // she has paid
        ->assertSee('Piet Pompies')    // listed without a number
        ->assertSee('https://wa.me/27821234567?text='.rawurlencode('Hi Jan, your R1 200.00 subscription is due.'), false)
        ->assertSee(rawurlencode('Hi everyone, your the membership fee subscription is due.'), false)
        ->assertSee('Members who haven\'t paid for 2026 (3)')
        ->assertSee('Full members (1)')
        ->assertSee('Social members (1)');

    $page->fillForm(['recipients' => 'type:Social'])->call('prepare')
        ->assertSee('Anna Venter')
        ->assertDontSee('Jan Botha');
});

it('offers the members ticked on the list as the recipients', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567');
    clubMember('Anna', 'Venter', '+27831112222');

    $this->actingAs($this->admin);

    Livewire::withQueryParams(['members' => (string) $jan->uid])
        ->test(MessageMembers::class)
        ->assertFormSet(['recipients' => 'selected'])
        ->fillForm(['message' => 'Hello {name}'])
        ->call('prepare')
        ->assertSee('Jan Botha')
        ->assertDontSee('Anna Venter');
});

it('needs a message before preparing anything', function () {
    $this->actingAs($this->admin);

    Livewire::test(MessageMembers::class)
        ->fillForm(['recipients' => 'all', 'message' => ''])
        ->call('prepare')
        ->assertHasFormErrors(['message' => 'required'])
        ->assertSet('prepared', false);
});

it('keeps the message page from staff who cannot manage members', function () {
    $this->actingAs(staff('admin.see-menu', 'admin.booking'))->get('/admin/message-members')->assertForbidden();
});

it('finds birthdays, keeping 29 February ones on 28 February in other years', function () {
    clubMember('Jan', 'Botha', '+27821234567', [Membership::BIRTHDAY => '1956-10-05']);
    clubMember('Leap', 'Day', '+27831112222', [Membership::BIRTHDAY => '1960-02-29']);
    $waiting = clubMember('New', 'Member', '+27845556666', [Membership::BIRTHDAY => '1990-10-05']);
    $waiting->update(['status' => 'disabled']);

    $birthdays = app(Birthdays::class);

    expect(array_map(fn (User $user) => $user->alias, $birthdays->on(Carbon::parse('2026-10-05'))))->toBe(['Jan Botha'])
        ->and(array_map(fn (User $user) => $user->alias, $birthdays->on(Carbon::parse('2027-02-28'))))->toBe(['Leap Day'])
        ->and($birthdays->on(Carbon::parse('2028-02-28')))->toBe([])
        ->and(array_map(fn (User $user) => $user->alias, $birthdays->on(Carbon::parse('2028-02-29'))))->toBe(['Leap Day']);
});

it('shows today\'s birthdays on the dashboard with wishes to send in one tap', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567', [Membership::BIRTHDAY => '1956-10-05']);
    clubMember('Anna', 'Venter', '+27831112222', [Membership::BIRTHDAY => '1970-10-09']);
    clubMember('Later', 'On', '+27845556666', [Membership::BIRTHDAY => '1970-11-01']);

    $this->actingAs($this->admin);

    expect(Dashboard::getNavigationBadge())->toBe('1');

    Livewire::test(BirthdaysWidget::class)
        ->assertSee('Jan Botha')
        ->assertSee('turns 70')
        ->assertSee('Anna Venter')
        ->assertSee('Fri 9 Oct')
        ->assertDontSee('Later On')
        ->assertSee('https://wa.me/27821234567?text='.rawurlencode('Happy birthday, Jan! Best wishes from everyone at LCE.'), false)
        ->call('markWished', $jan->uid)
        ->assertSee('Wished');

    expect(Dashboard::getNavigationBadge())->toBeNull()
        ->and($jan->fresh()->meta(Birthdays::WISHED))->toBe('2026');
});

it('saves the membership settings', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'client_name_full' => 'LCE Bowls Club',
            'client_name_short' => 'LCE',
            'activation' => 'manual',
            'playing_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'membership_types' => "Full: 1300\nSocial: 500",
            'membership_year_start' => '7',
            'birthday_message' => 'Many happy returns, {name}!',
        ])
        ->call('save')
        ->assertNotified('Settings saved');

    expect(app(Membership::class)->types())->toBe(['Full' => 1300.0, 'Social' => 500.0])
        ->and(app(Membership::class)->yearStartMonth())->toBe(7)
        ->and(app(Birthdays::class)->message())->toBe('Many happy returns, {name}!');
});

it('includes the membership details and payments in the member\'s own data download', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567', [Membership::TYPE => 'Full', Membership::BIRTHDAY => '1956-10-05']);
    paid($jan, 2026);

    $this->actingAs($jan)->get('/account/data')
        ->assertOk()
        ->assertJsonPath('membership.type', 'Full')
        ->assertJsonPath('membership.birthday', '1956-10-05')
        ->assertJsonPath('membership.payments.0.membership_year', 2026)
        ->assertJsonPath('membership.payments.0.amount', '1200.00');
});

it('removes the payment history with the account', function () {
    $jan = clubMember('Jan', 'Botha', '+27821234567');
    $jan->update(['pw' => 'a-good-password']);
    paid($jan, 2026);

    $this->actingAs($jan)->delete('/account', ['current_password' => 'a-good-password'])->assertRedirect('/');

    expect(MemberPayment::query()->count())->toBe(0);
});
