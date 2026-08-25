<?php
require __DIR__ . '/bootstrap.php';

foreach (['catalog_mapper_test.php', 'provider_test.php', 'manager_test.php', 'ledger_test.php', 'refund_policy_test.php'] as $file) {
    require __DIR__ . '/' . $file;
}

echo "\n" . str_repeat('-', 52) . "\n";
printf("%d gecti, %d basarisiz\n", T::$passed, T::$failed);

exit(T::$failed === 0 ? 0 : 1);
