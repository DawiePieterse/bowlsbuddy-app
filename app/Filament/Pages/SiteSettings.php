<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
        'day_exceptions' => 'service.calendar.day-exceptions',
        'max_active_bookings' => 'service.user.default.max_active_bookings',
    ];

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

        foreach (['terms', 'privacy'] as $document) {
            if (is_file(storage_path('app/documents/'.$document.'.pdf'))) {
                $data[$document] = ['documents/'.$document.'.pdf'];
            }
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
                ]),
                Tab::make('Info and help pages')->schema([
                    RichEditor::make('info')->label('Info page')
                        ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3']),
                    RichEditor::make('help')->label('Help page')
                        ->helperText('Leave empty for the built-in guide.')
                        ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3']),
                ]),
                Tab::make('Behaviour')->schema([
                    Select::make('activation')->label('New registrations')->options([
                        'immediate' => 'Active immediately',
                        'manual' => 'Activated by the Secretary',
                    ])->required(),
                    Textarea::make('day_exceptions')->label('Days hidden from the calendar')
                        ->rows(3)
                        ->helperText('Weekday names or dates (2026-12-25), one per line. "+2026-10-13" re-allows a date whose weekday is hidden.'),
                    TextInput::make('max_active_bookings')->label('Open bookings per member (0 = no limit)')
                        ->numeric()->minValue(0)->maxValue(50),
                ]),
                Tab::make('Documents')->schema([
                    Section::make()->description('PDF files members see when registering and on the Info page.')->schema([
                        FileUpload::make('terms')->label('Business Terms (PDF)')
                            ->disk('local')->directory('documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->getUploadedFileNameForStorageUsing(fn () => 'terms.pdf'),
                        FileUpload::make('privacy')->label('Privacy Policy (PDF)')
                            ->disk('local')->directory('documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->getUploadedFileNameForStorageUsing(fn () => 'privacy.pdf'),
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
}
