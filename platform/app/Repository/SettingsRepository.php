<?php
namespace Onay\App\Repository;

use Onay\App\Kernel\Database;

/**
 * Site ayarlari. Anahtar-deger tutulur: yeni bir alan eklemek icin sema
 * degistirmek gerekmez. Istek basina bir kez okunup bellekte tutulur.
 */
final class SettingsRepository
{
    /** @var array<string,string>|null */
    private static ?array $onbellek = null;

    public const VARSAYILAN = [
        'site_title'       => 'Sanal Numara',
        'site_tagline'     => 'Tek kullanimlik sanal numara ile SMS onayi',
        'site_description' => 'Kendi numaranizi paylasmadan, dakikalar icinde dogrulama kodunuzu alin.',
        'contact_email'    => '',
        'contact_phone'    => '',
        'whatsapp'         => '',
        'telegram'         => '',
        'twitter'          => '',
        'instagram'        => '',
        'footer_text'      => '',
        'announcement'     => '',
    ];

    /** @return array<string,string> */
    public function all(): array
    {
        if (self::$onbellek !== null) {
            return self::$onbellek;
        }

        $kayitlar = [];
        foreach (Database::pdo()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $r) {
            $kayitlar[$r['setting_key']] = (string) $r['setting_value'];
        }

        return self::$onbellek = $kayitlar + self::VARSAYILAN;
    }

    public function get(string $anahtar, string $varsayilan = ''): string
    {
        $deger = $this->all()[$anahtar] ?? $varsayilan;

        return $deger === '' ? $varsayilan : $deger;
    }

    /** @param array<string,string> $degerler */
    public function save(array $degerler): void
    {
        $stmt = Database::pdo()->prepare(
            Database::upsert('site_settings', ['setting_key', 'setting_value'], ['setting_key'], ['setting_value'])
        );

        foreach ($degerler as $anahtar => $deger) {
            $stmt->execute([$anahtar, $deger]);
        }

        self::$onbellek = null;
    }

    public static function unutOnbellek(): void
    {
        self::$onbellek = null;
    }
}
