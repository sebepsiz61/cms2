<?php
namespace Onay\App\Service;

use Onay\App\Kernel\Config;
use Onay\App\Kernel\Database;

/**
 * Saglayici maliyetinden musteri fiyatini uretir.
 *
 * Fiyat siparis aninda hesaplanip donduruldugu icin burasi saf bir hesaptir:
 * ayni girdi her zaman ayni cikti. Kur ve marj degistiginde gecmis siparisler
 * etkilenmez.
 */
final class PricingService
{
    /** @var array<string, array{margin_percent:?int, fixed_price_minor:?int}>|null */
    private ?array $rules = null;

    public function sellPriceMinor(int $costMinor, string $currency, string $countryCode, string $serviceCode): int
    {
        $rule = $this->ruleFor($countryCode, $serviceCode);

        // Sabit fiyat kurali varsa maliyet ve kur hesaba girmez.
        if ($rule !== null && $rule['fixed_price_minor'] !== null) {
            return (int) $rule['fixed_price_minor'];
        }

        $costInOurCurrency = $this->convert($costMinor, $currency);
        $margin = $rule['margin_percent'] ?? (int) Config::get('pricing.margin_percent', 40);

        $price = (int) round($costInOurCurrency * (100 + $margin) / 100);

        // Yuzde marj ucuz numaralarda kurus kadar kar birakabilir; asgari kar korur.
        $minProfit = (int) Config::get('pricing.min_profit_minor', 0);
        $price = max($price, $costInOurCurrency + $minProfit);

        return $this->roundUp($price);
    }

    public function convert(int $amountMinor, string $currency): int
    {
        if ($currency === Config::get('currency.code', 'TRY')) {
            return $amountMinor;
        }

        $rate = Config::get('rates.' . $currency);

        if (!is_numeric($rate)) {
            throw new \RuntimeException(
                'Kur tanimsiz: ' . $currency . '. config/config.php icindeki rates bolumune ekleyin.'
            );
        }

        return (int) round($amountMinor * (float) $rate);
    }

    private function roundUp(int $priceMinor): int
    {
        $step = (int) Config::get('pricing.round_to_minor', 0);

        return $step > 1 ? (int) (ceil($priceMinor / $step) * $step) : $priceMinor;
    }

    /** @return array{margin_percent:?int, fixed_price_minor:?int}|null */
    private function ruleFor(string $countryCode, string $serviceCode): ?array
    {
        if ($this->rules === null) {
            $this->rules = [];
            foreach (Database::pdo()->query('SELECT * FROM pricing_rules')->fetchAll() as $row) {
                $key = ($row['country_code'] ?? '') . '|' . ($row['service_code'] ?? '');
                $this->rules[$key] = [
                    'margin_percent'    => $row['margin_percent'] === null ? null : (int) $row['margin_percent'],
                    'fixed_price_minor' => $row['fixed_price_minor'] === null ? null : (int) $row['fixed_price_minor'],
                ];
            }
        }

        // Ozelden genele: ulke+servis, sonra yalnizca servis, sonra yalnizca ulke.
        foreach ([$countryCode . '|' . $serviceCode, '|' . $serviceCode, $countryCode . '|'] as $key) {
            if (isset($this->rules[$key])) {
                return $this->rules[$key];
            }
        }

        return null;
    }
}
