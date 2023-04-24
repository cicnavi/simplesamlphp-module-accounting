<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accounting\Services;

use SimpleSAML\Module\accounting\Services\MenuManager\MenuItem;

class MenuManager
{
    /** @var array<MenuItem> */
    protected array $items = [];

    /**
     * Add MenuItems to the end of the list.
     * @param MenuItem ...$menuItems
     * @return void
     */
    public function addItems(MenuItem ...$menuItems): void
    {
        array_push($this->items, ...$menuItems);
    }

    /**
     * Add MenuItem to specific offset (position). If offset not set, MenuItem will be appended to the end.
     * @param MenuItem $menuItem
     * @param int|null $offset
     * @return void
     */
    public function addItem(MenuItem $menuItem, int $offset = null): void
    {
        $offset = $offset ?? count($this->items);

        array_splice($this->items, $offset, 0, [$menuItem]);
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
