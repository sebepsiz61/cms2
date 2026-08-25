<?php
namespace Onay\Core\Provider;

use Onay\Core\Contract\NumberProviderInterface;

/**
 * Etkin saglayicilar ve oncelikleri. Yonetici panelinden saglayici acilip
 * kapatildiginda ya da sirasi degistiginde yalnizca bu kayit degisir.
 */
final class ProviderRegistry
{
    /** @var array<string, array{provider:NumberProviderInterface, priority:int, enabled:bool}> */
    private array $entries = [];

    public function register(NumberProviderInterface $provider, int $priority = 100, bool $enabled = true): self
    {
        $this->entries[$provider->name()] = [
            'provider' => $provider,
            'priority' => $priority,
            'enabled'  => $enabled,
        ];

        return $this;
    }

    public function get(string $name): NumberProviderInterface
    {
        if (!isset($this->entries[$name])) {
            throw new \InvalidArgumentException('Tanimsiz saglayici: ' . $name);
        }

        return $this->entries[$name]['provider'];
    }

    public function disable(string $name): void
    {
        if (isset($this->entries[$name])) {
            $this->entries[$name]['enabled'] = false;
        }
    }

    /** @return NumberProviderInterface[] oncelik sirasinda, yalnizca etkin olanlar */
    public function enabled(): array
    {
        $entries = array_filter($this->entries, static fn (array $e): bool => $e['enabled']);
        uasort($entries, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_values(array_map(static fn (array $e): NumberProviderInterface => $e['provider'], $entries));
    }
}
