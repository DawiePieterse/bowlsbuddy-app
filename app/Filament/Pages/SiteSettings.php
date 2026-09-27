<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\ClubDocuments;
use App\Support\ClubLogo;
use App\Support\Settings;
use App\Support\StandardTexts;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The Secretary's settings (PLAN.md Phase 4): names and text, the info and help pages, behaviour,
 * and the Business Terms and Privacy Policy uploads. Values live in bs_options; the info and
 * help HTML is cleaned with a sanitizer on save (PLAN.md section 6). The PDFs land in
 * storage/app/documents, where the member-facing /documents routes serve them.
 *
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.site-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /** The settings edited here, form field => bs_options key. */
    private const KEYS = [
        'client_name_full' => 'client.name.full',
        'client_name_short' => 'client.name.short',
        'meta_description' => 'service.meta.description',
        'info' => 'service.info',
        'help' => 'service.help',
        'activation' => 'service.user.activation',
        'max_active_bookings' => 'service.user.default.max_active_bookings',
    ];

    private const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private const DAY_EXCEPTIONS = 'service.calendar.day-exceptions';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.config');
    }

    public function mount(Settings $settings): void
    {
        $data = [];

        foreach (self::KEYS as $field => $key) {
            $data[$field] = $settings->get($key);
        }

        foreach (array_keys(ClubDocuments::ALL) as $document) {
            if (ClubDocuments::exists($document)) {
                $data[$document.'_pdf'] = [$document.'.pdf'];
            }
        }

        $data['playing_days'] = self::playingDays($settings);
        $data['info'] = StandardTexts::for('info');
        $data['help'] = StandardTexts::for('help');

        if (($logo = ClubLogo::path()) !== null) {
            $data['logo'] = [basename($logo)];
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                Tab::make('Names and text')->schema([
                    TextInput::make('client_name_full')->label('Club name')->required()->maxLength(100),
                    TextInput::make('client_name_short')->label('Short name')->required()->maxLength(20),
                    TextInput::make('meta_description')->label('Site description')->maxLength(200),
                    FileUpload::make('logo')->label('Club logo')
                        ->disk('documents')
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                        ->maxSize(1024)
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file) => 'logo.'.strtolower($file->getClientOriginalExtension()),
                        )
                        ->helperText('Shown in the header of every page, on the day sheet and in this panel.'),
                ]),
                Tab::make('Behaviour')->schema([
                    Select::make('activation')->label('New registrations')->options([
                        'immediate' => 'Active immediately',
                        'manual' => 'Activated by the Secretary',
                    ])->required(),
                    CheckboxList::make('playing_days')->label('Playing days')
                        ->options(array_combine(self::WEEKDAYS, self::WEEKDAYS))
                        ->columns(7)
                        ->required()
                        ->helperText('Members can book on the ticked days. Close a green for a single day on its calendar page.'),
                    TextInput::make('max_active_bookings')->label('Open bookings per member (0 = no limit)')
                        ->numeric()->minValue(0)->maxValue(50),
                ]),
                Tab::make('Documents')->schema([
                    Section::make('Info page')->description('The text on the Info page, and an optional PDF members can open from it.')->schema([
                        RichEditor::make('info')->label('Info page text')
                            ->helperText('Clear the text to go back to the standard text.')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3']),
                        self::pdfUpload('info', 'Info sheet (PDF)'),
                    ]),
                    Section::make('Help page')->description('The text on the Help page, and an optional PDF members can open from it.')->schema([
                        RichEditor::make('help')->label('Help page text')
                            ->helperText('Clear the text to go back to the standard text.')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3']),
                        self::pdfUpload('help', 'Help guide (PDF)'),
                    ]),
                    Section::make('Terms and privacy')->description('PDF files members see when registering and on the Info page.')->schema([
                        self::pdfUpload('terms', 'Business Terms (PDF)'),
                        self::pdfUpload('privacy', 'Privacy Policy (PDF)'),
                    ]),
                ]),
            ]),
        ])->statePath('data');
    }

    public function save(Settings $settings): void
    {
        $state = $this->form->getState();

        foreach (self::KEYS as $field => $key) {
            $value = $state[$field] ?? null;

            if (in_array($field, ['info', 'help'], true) && filled($value)) {
                $value = $this->sanitize((string) $value);
            }

            $settings->set($key, filled($value) ? (string) $value : null);
        }

        self::storePlayingDays($settings, (array) ($state['playing_days'] ?? []));

        foreach (array_keys(ClubDocuments::ALL) as $document) {
            if (blank($state[$document.'_pdf'] ?? null)) {
                ClubDocuments::remove($document);
            }
        }

        ClubLogo::keepOnly(blank($state['logo'] ?? null) ? null : basename((string) $state['logo']));

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save settings')->action('save'),
        ];
    }

    private function sanitize(string $html): string
    {
        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->forceAttribute('a', 'rel', 'noopener noreferrer'),
        );

        return $sanitizer->sanitize($html);
    }

    /**
     * The weekdays members can book: every weekday not hidden by service.calendar.day-exceptions.
     *
     * @return list<string>
     */
    private static function playingDays(Settings $settings): array
    {
        $hidden = array_map(
            fn (string $entry) => strtolower(trim($entry)),
            preg_split('/[\n,]/', (string) $settings->get(self::DAY_EXCEPTIONS, '')) ?: [],
        );

        return array_values(array_filter(
            self::WEEKDAYS,
            fn (string $weekday) => ! in_array(strtolower($weekday), $hidden, true),
        ));
    }

    /**
     * Stores the unticked weekdays as hidden. Date entries an older setup may hold (a single date,
     * or "+date") are kept as they are.
     *
     * @param  list<string>  $playing
     */
    private static function storePlayingDays(Settings $settings, array $playing): void
    {
        $dates = array_filter(
            array_map('trim', preg_split('/[\n,]/', (string) $settings->get(self::DAY_EXCEPTIONS, '')) ?: []),
            fn (string $entry) => $entry !== '' && ! in_array(strtolower($entry), array_map('strtolower', self::WEEKDAYS), true),
        );

        $hidden = array_values(array_diff(self::WEEKDAYS, $playing));

        $settings->set(self::DAY_EXCEPTIONS, implode("\n", [...$hidden, ...$dates]));
    }

    private static function pdfUpload(string $document, string $label): FileUpload
    {
        return FileUpload::make($document.'_pdf')->label($label)
            ->disk('documents')
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize(10240)
            ->getUploadedFileNameForStorageUsing(fn () => $document.'.pdf');
    }
}
