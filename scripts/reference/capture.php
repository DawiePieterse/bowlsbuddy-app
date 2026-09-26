<?php

use Zend\Mvc\Application;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/*
 * Records how the original Bowls Buddy app answers the booking scenarios in tests/Reference/scenarios.php,
 * into tests/Reference/reference-values.json. tests/Feature/ReferenceValuesTest.php then runs the same
 * scenarios through this app and expects the same answers. See docs/PLAN.md, section 5.3.
 *
 * Needs a checkout of the original app (the branch with greens: claude/bold-tesla-kb88te) with its
 * Composer packages installed, config/init.php set to Africa/Johannesburg, and config/autoload/local.php
 * pointing at an EMPTY scratch database loaded from data/db/ep3-bs.sql. Every scenario wipes that
 * database. See scripts/reference/README.md.
 *
 *     php scripts/reference/capture.php /path/to/bowlsbuddy
 */

if ($argc < 2 || ! is_file($argv[1].'/config/application.php')) {
    fwrite(STDERR, "Usage: php scripts/reference/capture.php /path/to/bowlsbuddy\n");
    exit(1);
}

$scenarios = require __DIR__.'/../../tests/Reference/scenarios.php';
$output = __DIR__.'/../../tests/Reference/reference-values.json';

chdir($argv[1]);
require 'vendor/autoload.php';
require 'config/init.php';

if (date_default_timezone_get() !== 'Africa/Johannesburg') {
    fwrite(STDERR, "Set date.timezone to Africa/Johannesburg in the original app's config/init.php first.\n");
    exit(1);
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 'stderr');
ini_set('html_errors', '0');
ini_set('log_errors', '0');
$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['REQUEST_URI'] ??= '/';

$db = (require 'config/autoload/local.php')['db'];
$pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['hostname'], $db['database']), $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Output would count as sent headers and stop the original app from starting its session.
ob_start();

$config = require 'config/application.php';
$now = new DateTime;
$today = new DateTime('today');

$results = [
    'captured_at' => $now->format('Y-m-d H:i:s'),
    'original_app' => trim((string) shell_exec('git -C '.escapeshellarg($argv[1]).' rev-parse HEAD 2>/dev/null')),
    'scenarios' => [],
];

foreach ($scenarios as $name => $scenario) {
    $users = seed($pdo, $today);

    foreach ($scenario['setup'] as $step) {
        apply($pdo, $step, $users, $today);
    }

    $application = Application::init($config);
    $services = $application->getServiceManager();
    $result = ['checks' => []];

    foreach ($scenario['checks'] as $check) {
        $result['checks'][] = $check[0] === 'book'
            ? checkBooking($services, $pdo, $check, $users, $today)
            : checkCancel($services, $pdo, $check, $users);
    }

    if ($scenario['overview'] ?? false) {
        $result['overview'] = overview($services);
    }

    $results['scenarios'][$name] = $result;
    fwrite(STDERR, "$name\n");
}

file_put_contents($output, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDERR, 'Written to '.realpath($output)."\n");

/** Empties the database and sets up LCE with the members. Returns name => uid. */
function seed(PDO $pdo, DateTime $today): array
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query("SHOW TABLES LIKE 'bs\\_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec("TRUNCATE `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    foreach ([
        'client.name.full' => 'LCE Bowls Club', 'client.name.short' => 'LCE',
        'service.name.full' => 'Bowls Buddy', 'service.name.short' => 'BB',
        'subject.square.type' => 'Rink', 'subject.square.type.plural' => 'Rinks',
        'subject.square.unit' => 'Player', 'subject.square.unit.plural' => 'Players',
        'subject.type' => 'our club', 'service.calendar.days' => '1',
    ] as $key => $value) {
        $pdo->prepare('INSERT INTO bs_options (`key`, value) VALUES (?, ?)')->execute([$key, $value]);
    }

    $priority = 1;
    foreach (['A', 'B'] as $green) {
        for ($number = 1; $number <= 6; $number++) {
            $pdo->prepare('INSERT INTO bs_squares (name, status, priority, capacity, capacity_heterogenic, allow_notes,
                time_start, time_end, time_block, time_block_bookable, time_block_bookable_max, min_range_book,
                range_book, max_active_bookings, range_cancel)
                VALUES (?, "enabled", ?, 2, 0, 0, "12:00:00", "17:00:00", 3600, 3600, 3600, 0, ?, 0, ?)')
                ->execute(["$green-$number", $priority++, 14 * 86400, 24 * 3600]);
        }
    }

    $users = [];
    foreach (['anna' => 'enabled', 'ben' => 'enabled', 'cara' => 'enabled', 'admin' => 'admin'] as $alias => $status) {
        $users[$alias] = createUser($pdo, $alias, $status);
    }

    return $users;
}

function createUser(PDO $pdo, string $alias, string $status, array $privileges = []): int
{
    $pdo->prepare('INSERT INTO bs_users (alias, status, email, created) VALUES (?, ?, ?, NOW())')
        ->execute([$alias, $status, str_replace([':', ',', '.'], '-', $alias).'@example.com']);
    $uid = (int) $pdo->lastInsertId();

    foreach ($privileges as $privilege) {
        $pdo->prepare('INSERT INTO bs_users_meta (uid, `key`, value) VALUES (?, ?, "true")')->execute([$uid, 'allow.'.$privilege]);
    }

    return $uid;
}

function day(DateTime $today, int $offset): DateTime
{
    return (clone $today)->modify(sprintf('%+d days', $offset));
}

function sid(PDO $pdo, string $rink): int
{
    $statement = $pdo->prepare('SELECT sid FROM bs_squares WHERE name = ?');
    $statement->execute([$rink]);

    return (int) $statement->fetchColumn();
}

function apply(PDO $pdo, array $step, array &$users, DateTime $today): void
{
    switch ($step[0]) {
        case 'booking':
            [, $member, $rink, $day, $time] = $step;
            [$players, $status, $visibility, $hours, $label] = array_slice($step, 5) + [1, 'single', 'public', 1, null];
            $start = DateTime::createFromFormat('Y-m-d H:i', day($today, $day)->format('Y-m-d').' '.$time);
            $end = (clone $start)->modify("+$hours hours");

            $pdo->prepare('INSERT INTO bs_bookings (uid, sid, status, status_billing, visibility, quantity, created)
                VALUES (?, ?, ?, "pending", ?, ?, NOW())')
                ->execute([$users[$member], sid($pdo, $rink), $status, $visibility, $players]);
            $bid = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO bs_reservations (bid, date, time_start, time_end) VALUES (?, ?, ?, ?)')
                ->execute([$bid, $start->format('Y-m-d'), $start->format('H:i:s'), $end->format('H:i:s')]);

            if ($label !== null) {
                $users['booking:'.$label] = $bid;
            }
            break;

        case 'event':
            [, $on, $day, $from, $to, $name] = $step;
            $date = day($today, $day)->format('Y-m-d');
            $sid = $on !== null && ! str_starts_with($on, 'green:') ? sid($pdo, $on) : null;

            $pdo->prepare('INSERT INTO bs_events (sid, status, datetime_start, datetime_end) VALUES (?, "enabled", ?, ?)')
                ->execute([$sid, "$date $from:00", "$date $to:00"]);
            $eid = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO bs_events_meta (eid, `key`, value) VALUES (?, "name", ?)')->execute([$eid, $name]);

            if ($on !== null && str_starts_with($on, 'green:')) {
                $pdo->prepare('INSERT INTO bs_events_meta (eid, `key`, value) VALUES (?, "green", ?)')->execute([$eid, substr($on, 6)]);
            }
            break;

        case 'closed':
            $entry = day($today, $step[2])->format('Y-m-d').':'.$step[1];
            $current = $pdo->query('SELECT value FROM bs_options WHERE `key` = "service.greens.closed"')->fetchColumn();

            if ($current === false) {
                $pdo->prepare('INSERT INTO bs_options (`key`, value) VALUES ("service.greens.closed", ?)')->execute([$entry]);
            } else {
                $pdo->prepare('UPDATE bs_options SET value = ? WHERE `key` = "service.greens.closed"')->execute([trim("$current\n$entry")]);
            }
            break;

        case 'option':
            $value = preg_replace_callback('/\{(date|weekday):(-?\d+)\}/', fn ($match) => day($today, (int) $match[2])
                ->format($match[1] === 'date' ? 'Y-m-d' : 'l'), $step[2]);
            $pdo->prepare('INSERT INTO bs_options (`key`, value) VALUES (?, ?)')->execute([$step[1], $value]);
            break;

        case 'rink':
            foreach ($step[2] as $column => $value) {
                $pdo->prepare("UPDATE bs_squares SET `$column` = ? WHERE name = ?")->execute([$value, $step[1]]);
            }
            break;

        case 'user-meta':
            $pdo->prepare('INSERT INTO bs_users_meta (uid, `key`, value) VALUES (?, ?, ?)')->execute([$users[$step[1]], $step[2], $step[3]]);
            break;

        default:
            throw new RuntimeException('Unknown setup step '.$step[0]);
    }
}

/** Makes the original app see $as as the logged-in user. */
function actAs($services, PDO $pdo, string $as, array &$users): void
{
    if ($as !== 'visitor' && ! isset($users[$as])) {
        $users[$as] = createUser($pdo, $as, 'assist', explode(',', substr($as, 7)));
    }

    $sessions = $services->get('User\Manager\UserSessionManager');
    $user = $as === 'visitor' ? null : $services->get('User\Manager\UserManager')->get($users[$as]);

    (new ReflectionProperty($sessions, 'user'))->setValue($sessions, $user);
}

/** The original app's answer, in this app's BookingRefusal values ("booked" when allowed). */
function checkBooking($services, PDO $pdo, array $check, array &$users, DateTime $today): array
{
    [, $as, $rink, $day, $time] = $check;
    [$hours, $players] = array_slice($check, 5) + [1, 1];

    actAs($services, $pdo, $as, $users);

    $start = DateTime::createFromFormat('Y-m-d H:i', day($today, $day)->format('Y-m-d').' '.$time);
    $end = (clone $start)->modify("+$hours hours");

    try {
        $validator = $services->create('Square\Service\SquareValidator');
        $result = $validator->isBookable($start->format('Y-m-d'), $end->format('Y-m-d'), $start->format('H:i'), $end->format('H:i'), sid($pdo, $rink));

        if (! $result['bookable']) {
            $reason = (string) $result['notBookableReason'];
            $outcome = match (true) {
                str_contains($reason, 'is closed') => 'green-closed',
                str_contains($reason, 'per day') => 'one-rink-per-day',
                str_contains($reason, 'active booking') => 'max-active-bookings',
                default => 'occupied',
            };
        } elseif ($result['square']->need('capacity') - $result['quantity'] < $players) {
            $outcome = 'too-many-players';
        } else {
            $outcome = 'booked';
        }
    } catch (RuntimeException $refused) {
        $message = $refused->getMessage();
        $outcome = match (true) {
            str_contains($message, 'not available') => 'rink-unavailable',
            str_contains($message, 'time range is invalid'), str_contains($message, 'time is invalid') => 'invalid-time',
            str_contains($message, 'already over') => 'in-the-past',
            str_contains($message, 'kurzfristig') => 'too-short-notice',
            str_contains($message, 'too far away') => 'too-far-ahead',
            str_contains($message, 'minutes at once') => 'too-long',
            str_contains($message, 'hidden') => 'day-hidden',
            default => 'error: '.$message,
        };
    }

    return ['check' => $check, 'outcome' => $outcome];
}

function checkCancel($services, PDO $pdo, array $check, array &$users): array
{
    [, $as, $label] = $check;

    actAs($services, $pdo, $as, $users);

    $booking = $services->get('Booking\Manager\BookingManager')->get($users['booking:'.$label]);
    $cancellable = $services->create('Square\Service\SquareValidator')->isCancellable($booking);

    return ['check' => $check, 'outcome' => $cancellable ? 'cancellable' : 'not-cancellable'];
}

/** The greens overview as the original app's home page computes it. */
function overview($services): array
{
    $sessions = $services->get('User\Manager\UserSessionManager');
    (new ReflectionProperty($sessions, 'user'))->setValue($sessions, null);

    $controller = $services->get('ControllerManager')->get('Frontend\Controller\Index');
    $controller->getPluginManager()->setAllowOverride(true);
    $controller->getPluginManager()->setService('redirectBack', new class extends AbstractPlugin
    {
        public function __invoke()
        {
            return $this;
        }

        public function setOrigin()
        {
            return $this;
        }
    });

    $method = new ReflectionMethod($controller, 'greensOverview');
    $days = $method->invoke($controller)->getVariable('days');

    return array_map(fn (array $day) => [
        'date' => $day['date']->format('Y-m-d'),
        'closed' => $day['closed'],
        'free' => $day['available'],
        'slots' => $day['slots'],
        'events' => $day['events'],
    ], $days);
}
