<?php

namespace DigitalLabs\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use DigitalLabs\Core\Acl\AclItem;

class Acl
{
    /**
     * acl items.
     */
    protected array $items = [];

    /**
     * Memoised acl config.
     */
    protected ?array $aclConfigCache = null;

    /**
     * Memoised route => key map.
     */
    protected ?Collection $rolesCache = null;

    /**
     * Add a new acl item.
     */
    public function addItem(AclItem $aclItem): void
    {
        $this->items[] = $aclItem;
    }

    /**
     * Get all acl items.
     */
    public function getItems(): Collection
    {
        if (! $this->items) {
            $this->prepareAclItems();
        }

        return collect($this->items)
            ->sortBy('sort');
    }

    /**
     * Acl Config.
     */
    private function getAclConfig(): array
    {
        if ($this->aclConfigCache) {
            return $this->aclConfigCache;
        }

        $this->aclConfigCache = config('acl');

        return $this->aclConfigCache;
    }

    /**
     * Get all roles as a route => permission-key map.
     *
     * An entry's `route` may be a single route name or an array of them, so
     * one permission can cover both its read screen and its mutating routes
     * without creating a second permission key.
     */
    public function getRoles(): Collection
    {
        if ($this->rolesCache) {
            return $this->rolesCache;
        }

        $this->rolesCache = collect($this->getAclConfig())
            ->flatMap(fn ($role) => collect(Arr::wrap($role['route']))
                ->mapWithKeys(fn ($route) => [$route => $role['key']])
            );

        return $this->rolesCache;
    }

    /**
     * Prepare acl items.
     */
    private function prepareAclItems(): void
    {
        $aclWithDotNotation = [];

        foreach ($this->getAclConfig() as $item) {
            $aclWithDotNotation[$item['key']] = $item;
        }

        $acl = Arr::undot(Arr::dot($aclWithDotNotation));

        foreach ($acl as $aclItemKey => $aclItem) {
            $subAclItems = $this->processSubAclItems($aclItem);

            $this->addItem(new AclItem(
                key: $aclItemKey,
                name: trans($aclItem['name']),
                route: Arr::first(Arr::wrap($aclItem['route'])),
                sort: $aclItem['sort'],
                children: $subAclItems,
            ));
        }
    }

    /**
     * Process sub acl items.
     */
    private function processSubAclItems($aclItem): Collection
    {
        return collect($aclItem)
            ->sortBy('sort')
            ->filter(fn ($value) => is_array($value))
            ->map(function ($subAclItem) {
                $subSubAclItems = $this->processSubAclItems($subAclItem);

                return new AclItem(
                    key: $subAclItem['key'],
                    name: trans($subAclItem['name']),
                    route: Arr::first(Arr::wrap($subAclItem['route'])),
                    sort: $subAclItem['sort'],
                    children: $subSubAclItems,
                );
            });
    }
}
