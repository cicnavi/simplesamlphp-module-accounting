<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accounting\Helpers;

use Exception;
use SimpleSAML\Module;
use Throwable;

class SspModule
{
    /**
     * @throws Exception
     */
    public function isEnabled(string $moduleName): bool
    {
        try {
            return Module::isModuleEnabled($moduleName);
            // @codeCoverageIgnoreStart
        } catch (Throwable $exception) {
            $message = sprintf('Could not check if module %s is enabled', $moduleName);
            throw new Module\accounting\Exceptions\InvalidConfigurationException(
                $message,
                (int) $exception->getCode(),
                $exception
            );
        }
        // @codeCoverageIgnoreEnd
    }
}
