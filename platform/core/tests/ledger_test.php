<?php
use Onay\Core\Contract\LedgerInterface;
use Onay\Core\Exception\InsufficientBalanceException;
use Onay\Core\Wallet\PdoLedger;

function yeniDefter(string $file = ':memory:'): array
{
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(file_get_contents(__DIR__ . '/../schema/ledger.sql'));
    $pdo->exec("INSERT INTO users (id, email) VALUES (1, 'musteri@ornek.com')");

    return [new PdoLedger($pdo), $pdo];
}

T::group('PdoLedger — cuzdan defteri');

T::it('yukleme bakiyeyi artirir', function () {
    [$ledger] = yeniDefter();
    $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'havale-1');

    T::same(10000, $ledger->balanceMinor(1));
});

T::it('harcama bakiyeyi dusurur', function () {
    [$ledger] = yeniDefter();
    $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'havale-1');
    $ledger->debit(1, 1250, LedgerInterface::TYPE_SPEND, 'siparis-1', 'number_order', 1);

    T::same(8750, $ledger->balanceMinor(1));
});

T::it('bakiye eksiye dusemez', function () {
    [$ledger] = yeniDefter();
    $ledger->credit(1, 1000, LedgerInterface::TYPE_LOAD, 'havale-1');

    T::throws(
        InsufficientBalanceException::class,
        fn () => $ledger->debit(1, 1001, LedgerInterface::TYPE_SPEND, 'siparis-1')
    );
    T::same(1000, $ledger->balanceMinor(1), 'basarisiz harcama bakiyeyi bozmamali');
});

T::it('ayni idempotency anahtari ikinci kez para yuklemez', function () {
    [$ledger, $pdo] = yeniDefter();

    $ilk = $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'havale-42');
    $ikinci = $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'havale-42');

    T::same($ilk, $ikinci, 'ayni hareketin id\'si donmeli');
    T::same(10000, $ledger->balanceMinor(1), 'bakiye bir kez artmali');
    T::same(1, (int) $pdo->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn());
});

T::it('ayni siparis iki kez ucretlendirilemez', function () {
    [$ledger] = yeniDefter();
    $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'havale-1');

    $ledger->debit(1, 1250, LedgerInterface::TYPE_SPEND, 'siparis-77-spend', 'number_order', 77);
    $ledger->debit(1, 1250, LedgerInterface::TYPE_SPEND, 'siparis-77-spend', 'number_order', 77);

    T::same(8750, $ledger->balanceMinor(1));
});

T::it('iade harcamayi geri verir ama kaydi silmez', function () {
    [$ledger, $pdo] = yeniDefter();
    $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'havale-1');
    $ledger->debit(1, 1250, LedgerInterface::TYPE_SPEND, 'siparis-9-spend', 'number_order', 9);
    $ledger->credit(1, 1250, LedgerInterface::TYPE_REFUND, 'siparis-9-refund', 'number_order', 9);

    T::same(10000, $ledger->balanceMinor(1));
    T::same(3, (int) $pdo->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn(), 'hareketler silinmez, eklenir');
});

T::it('her hareket o andaki bakiyeyi saklar', function () {
    [$ledger, $pdo] = yeniDefter();
    $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'a');
    $ledger->debit(1, 3000, LedgerInterface::TYPE_SPEND, 'b');

    $rows = $pdo->query('SELECT balance_after FROM wallet_transactions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    T::same(['10000', '7000'], array_map('strval', $rows));
});

T::it('defter toplami ile onbellek kolonu her zaman esit', function () {
    [$ledger] = yeniDefter();
    $ledger->credit(1, 10000, LedgerInterface::TYPE_LOAD, 'a');
    $ledger->debit(1, 1250, LedgerInterface::TYPE_SPEND, 'b');
    $ledger->credit(1, 1250, LedgerInterface::TYPE_REFUND, 'c');
    $ledger->debit(1, 400, LedgerInterface::TYPE_SPEND, 'd');

    T::true($ledger->verifyIntegrity(1));
});

T::it('negatif ya da sifir tutar reddedilir', function () {
    [$ledger] = yeniDefter();
    T::throws(InvalidArgumentException::class, fn () => $ledger->credit(1, 0, LedgerInterface::TYPE_LOAD, 'x'));
    T::throws(InvalidArgumentException::class, fn () => $ledger->debit(1, -5, LedgerInterface::TYPE_SPEND, 'y'));
});
