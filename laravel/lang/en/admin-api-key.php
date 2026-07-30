<?php

return [
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'key' => 'Key',
        'status' => 'Status',
        'model_mappings' => 'Model Mappings',
        'allowed_channels' => 'Allowed Channels',
        'not_allowed_channels' => 'Forbidden Channels',
        'allowed_groups' => 'Allowed Groups',
        'not_allowed_groups' => 'Forbidden Groups',
        'allowed_tags' => 'Allowed Tags',
        'not_allowed_tags' => 'Forbidden Tags',
        'rate_limit' => 'Rate Limit',
        'expires_at' => 'Expires At',
        'last_used_at' => 'Last Used At',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',

        // Rate limit sub-fields
        'requests_per_minute' => 'Requests Per Minute',
        'requests_per_day' => 'Requests Per Day',
        'tokens_per_day' => 'Tokens Per Day',

        // Detail page special fields
        'available_models' => 'Available Models',
    ],

    'labels' => [
        'title' => 'API Key Management',
        'key_help' => 'API key, please save it properly after creation',
        'name_help' => 'API key name for easy identification',
        'allowed_channels_help' => 'Select allowed channels, leave empty for no restriction',
        'not_allowed_channels_help' => 'Select forbidden channels',
        'allowed_groups_help' => 'Group whitelist: channel must belong to one of these groups, leave empty for no restriction',
        'not_allowed_groups_help' => 'Group blacklist: channels in these groups are forbidden',
        'allowed_tags_help' => 'Tag whitelist: channel must have one of these tags, leave empty for no restriction',
        'not_allowed_tags_help' => 'Tag blacklist: channels with these tags are forbidden',
        'rate_limit_help' => 'Configure API key rate limits',
        'requests_per_minute_help' => 'Maximum requests per minute, 0 for no limit',
        'requests_per_day_help' => 'Maximum requests per day, 0 for no limit',
        'tokens_per_day_help' => 'Maximum tokens per day, 0 for no limit',
        'expires_at_help' => 'Leave empty for no expiration',
        'model_mappings_help' => 'Configure model alias mapping. Format: alias => actual model name. Example: cd-coding-latest => gpt-4',

        // Display text
        'no_limit' => 'No Limit',
        'none' => 'None',
        'no_available_models' => 'No Available Models',
        'times_per_minute' => 'times/min',
        'times_per_day' => 'times/day',
        'tokens_per_day' => 'tokens/day',
        'actual_model' => 'Actual Model',
    ],

    'options' => [
        'status' => [
            'active' => 'Active',
            'revoked' => 'Revoked',
            'expired' => 'Expired',
        ],
    ],
];
