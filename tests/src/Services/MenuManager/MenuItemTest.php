<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\accounting\Services\MenuManager;

use SimpleSAML\Module\accounting\Services\MenuManager\MenuItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \SimpleSAML\Module\accounting\Services\MenuManager\MenuItem
 */
class MenuItemTest extends TestCase
{
    public function testCanInstantiateMenuItem(): void
    {
        $menuItem = new MenuItem('hrefPath', 'label', 'iconAssetPath');
        $this->assertSame('hrefPath', $menuItem->getHrefPath());
        $this->assertSame('label', $menuItem->getLabel());
        $this->assertSame('iconAssetPath', $menuItem->getIconAssetPath());
    }
}
