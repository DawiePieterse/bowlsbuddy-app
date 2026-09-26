# Reference values from the original app

`tests/Feature/ReferenceValuesTest.php` checks that this app answers booking questions the way the original
Bowls Buddy app does. The scenarios are in `tests/Reference/scenarios.php`; the original app's answers are in
`tests/Reference/reference-values.json`, recorded by `capture.php` in this folder.

Record them again after adding or changing a scenario:

1. Check out the original app on the branch with the greens code, and install its packages (its locked
   Zend Framework packages predate PHP 8, hence the flag):

   ```bash
   git clone -b claude/bold-tesla-kb88te https://github.com/DawiePieterse/bowlsbuddy ../bowlsbuddy
   cd ../bowlsbuddy
   composer install --ignore-platform-reqs
   ```

2. Give it an **empty scratch database**. Every scenario wipes it.

   ```bash
   mysql -e 'CREATE DATABASE bb_reference CHARACTER SET utf8mb4'
   mysql bb_reference < data/db/ep3-bs.sql
   ```

3. Set it up: `config/init.php` from `config/init.php.dist` with the time zone changed to
   `Africa/Johannesburg`, and `config/autoload/local.php` from `config/autoload/local.php.dist` with the
   scratch database's details.

4. Back in this app, record the values and run the comparison:

   ```bash
   php scripts/reference/capture.php ../bowlsbuddy
   vendor/bin/pest tests/Feature/ReferenceValuesTest.php
   ```

The recording keeps the moment it was made (`captured_at`); the test travels back to it, so answers about
past and future slots stay comparable. `capture.php` only reads the original app's code and never changes it.
