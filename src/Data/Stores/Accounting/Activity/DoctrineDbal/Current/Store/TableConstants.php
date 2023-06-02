<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store;

use SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Current\Store\TableConstants
    as BaseTableConstants;

class TableConstants
{
    // Table 'authentication_event'.
    public const TABLE_NAME_AUTHENTICATION_EVENT = 'authentication_event';
    public const TABLE_ALIAS_AUTHENTICATION_EVENT = BaseTableConstants::TABLE_PREFIX . 'ae';
    public const TABLE_AUTHENTICATION_EVENT_COLUMN_NAME_ID = 'id';
    public const TABLE_AUTHENTICATION_EVENT_COLUMN_NAME_SP_ID = 'sp_id';
    public const TABLE_AUTHENTICATION_EVENT_COLUMN_NAME_USER_VERSION_ID = 'user_version_id';
    public const TABLE_AUTHENTICATION_EVENT_COLUMN_NAME_HAPPENED_AT = 'happened_at';
    public const TABLE_AUTHENTICATION_EVENT_COLUMN_NAME_CLIENT_IP_ADDRESS = 'client_ip_address';
    public const TABLE_AUTHENTICATION_EVENT_COLUMN_NAME_AUTHENTICATION_PROTOCOL_DESIGNATION =
        'authentication_protocol_designation';
    public const TABLE_AUTHENTICATION_EVENT_COLUMN_NAME_CREATED_AT = 'created_at';
}
