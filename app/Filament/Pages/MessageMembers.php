<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Membership;
use App\Support\Ids;
use App\Support\Phone;
use App\Support\WhatsApp;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * WhatsApp messages to members: pick who (everyone, members who haven't paid for this membership year, one
 * membership type, or the members ticked on the Members list), write the message once with {name} and {fee},
 * then send each member their own copy with one tap, or one copy to a group chat. The app sends nothing
 * itself; the Secretary sends from their own WhatsApp. Which ones were sent is only marked in the browser, so
 * tapping Send doesn't reload the list.
 *
 * @property-read Schema $form
 */
class MessageMembers extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationParentItem = 'Members';

    protected static ?string $slug = 'message-members';

    protected static ?string $title = 'Message members';

    protected string $view = 'filament.pages.message-members';

    /** The members ticked on the Members list (their uids, comma-separated), when they came from there. */
    #[Url]
    public string $members = '';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Whether the messages are ready to send (after "Prepare messages"). */
    public bool $prepared = false;

    /** @var array<string, string>|null the recipient choices, counted once per request */
    private ?array $recipientOptions = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.user');
    }

    public function mount(): void
    {
        $this->form->fill(['recipients' => $this->selectedUids() !== [] ? 'selected' : 'all', 'message' => '']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('recipients')->label('Send to')
                ->options(fn (): array => $this->recipientOptions())
                ->required()
                ->afterStateUpdated(fn () => $this->resetMessages())
                ->live(),
            Textarea::make('message')
                ->rows(4)
                ->required()
                ->maxLength(1000)
                ->placeholder('Hi {name}, ...')
                ->helperText('{name} becomes each member\'s first name and {fee} the fee of their membership type.')
                ->afterStateUpdated(fn () => $this->resetMessages())
                ->live(onBlur: true),
        ])->statePath('data');
    }

    public function prepare(): void
    {
        $this->form->validate();
        $this->prepared = true;
    }

    /**
     * Who the message goes to, with the count of each choice.
     *
     * @return array<string, string>
     */
    public function recipientOptions(): array
    {
        if ($this->recipientOptions !== null) {
            return $this->recipientOptions;
        }

        $membership = app(Membership::class);
        $options = [];

        if ($this->selectedUids() !== []) {
            $options['selected'] = 'The members ticked on the Members list ('.$this->recipientsQuery('selected')->count().')';
        }

        $options['all'] = 'All members ('.$this->recipientsQuery('all')->count().')';
        $options['unpaid'] = 'Members who haven\'t paid for '.$membership->currentYearLabel().' ('.$this->recipientsQuery('unpaid')->count().')';

        // One grouped query for the membership types.
        $perType = $this->recipientsQuery('all')
            ->join('bs_users_meta as type', 'type.uid', '=', 'bs_users.uid')
            ->where('type.key', Membership::TYPE)
            ->groupBy('type.value')
            ->selectRaw('type.value as membership, count(*) as members')
            ->pluck('members', 'membership');

        foreach (array_keys($membership->types()) as $type) {
            $options['type:'.$type] = $type.' members ('.($perType[$type] ?? 0).')';
        }

        return $this->recipientOptions = $options;
    }

    /**
     * One message per member with a cellphone number, and the members without one.
     *
     * @return array{send: list<array{uid: int, name: string, phone: string, text: string, url: string}>, without: list<array{name: string, email: string|null}>, group: string}
     */
    public function messages(): array
    {
        $membership = app(Membership::class);
        $message = (string) ($this->data['message'] ?? '');
        $send = [];
        $without = [];

        foreach ($this->recipientsQuery((string) ($this->data['recipients'] ?? 'all'))->with('metaEntries')->orderBy('alias')->get() as $user) {
            $name = $user->fullName();
            $text = $membership->personalise($message, $user);
            $url = WhatsApp::to($user->phone, $text);

            if ($url === null) {
                $without[] = ['name' => $name, 'email' => $user->email];

                continue;
            }

            $send[] = [
                'uid' => $user->uid,
                'name' => $name,
                'phone' => Phone::pretty($user->phone),
                'text' => $text,
                'url' => $url,
            ];
        }

        return ['send' => $send, 'without' => $without, 'group' => WhatsApp::share($membership->personalise($message, null))];
    }

    /** @return Builder<User> */
    private function recipientsQuery(string $recipients): Builder
    {
        $membership = app(Membership::class);
        $query = User::query();

        if ($recipients === 'selected') {
            return $query->whereIn('uid', $this->selectedUids());
        }

        // Everyone who can log in; members waiting for approval or blocked get no club messages.
        $query->whereIn('status', User::LOGIN_STATUSES);

        if ($recipients === 'unpaid') {
            $query->wherePaidFor($membership->currentYear(), false);
        } elseif (str_starts_with($recipients, 'type:')) {
            $query->whereMeta(Membership::TYPE, substr($recipients, 5));
        }

        return $query;
    }

    /** @return list<int> */
    private function selectedUids(): array
    {
        return Ids::parse($this->members);
    }

    private function resetMessages(): void
    {
        $this->prepared = false;
    }
}
