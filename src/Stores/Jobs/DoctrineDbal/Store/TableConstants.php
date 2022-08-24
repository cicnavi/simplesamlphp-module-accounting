<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accounting\Stores\Jobs\DoctrineDbal\Store;

class TableConstants
{
    public const TABLE_NAME_JOBS = 'jobs';
    public const TABLE_NAME_FAILED_JOBS = 'failed_jobs';

    // Both tables have same columns.
    public const COLUMN_NAME_ID = 'id';
    public const COLUMN_NAME_PAYLOAD = 'payload';
    public const COLUMN_NAME_TYPE = 'type';
    public const COLUMN_NAME_CREATED_AT = 'created_at';

    public const COLUMN_TYPE_LENGTH = 1024;
}
