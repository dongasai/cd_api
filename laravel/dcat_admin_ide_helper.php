<?php

/**
 * A helper file for Dcat Admin, to provide autocomplete information to your IDE
 *
 * This file should not be included in your code, only analyzed by your IDE!
 *
 * @author jqh <841324345@qq.com>
 */

namespace Dcat\Admin {
    use Illuminate\Support\Collection;

    /**
     * @property Grid\Column|Collection quickCreateButton
     * @property Grid\Column|Collection created_at
     * @property Grid\Column|Collection detail
     * @property Grid\Column|Collection id
     * @property Grid\Column|Collection name
     * @property Grid\Column|Collection type
     * @property Grid\Column|Collection updated_at
     * @property Grid\Column|Collection version
     * @property Grid\Column|Collection is_enabled
     * @property Grid\Column|Collection extension
     * @property Grid\Column|Collection icon
     * @property Grid\Column|Collection order
     * @property Grid\Column|Collection parent_id
     * @property Grid\Column|Collection uri
     * @property Grid\Column|Collection menu_id
     * @property Grid\Column|Collection permission_id
     * @property Grid\Column|Collection http_method
     * @property Grid\Column|Collection http_path
     * @property Grid\Column|Collection slug
     * @property Grid\Column|Collection role_id
     * @property Grid\Column|Collection user_id
     * @property Grid\Column|Collection value
     * @property Grid\Column|Collection avatar
     * @property Grid\Column|Collection language
     * @property Grid\Column|Collection password
     * @property Grid\Column|Collection remember_token
     * @property Grid\Column|Collection username
     * @property Grid\Column|Collection allowed_channels
     * @property Grid\Column|Collection allowed_models
     * @property Grid\Column|Collection deleted_at
     * @property Grid\Column|Collection expires_at
     * @property Grid\Column|Collection key
     * @property Grid\Column|Collection last_used_at
     * @property Grid\Column|Collection model_mappings
     * @property Grid\Column|Collection not_allowed_channels
     * @property Grid\Column|Collection permissions
     * @property Grid\Column|Collection rate_limit
     * @property Grid\Column|Collection status
     * @property Grid\Column|Collection actual_model
     * @property Grid\Column|Collection api_key_id
     * @property Grid\Column|Collection api_key_name
     * @property Grid\Column|Collection billing_source
     * @property Grid\Column|Collection cache_read_tokens
     * @property Grid\Column|Collection cache_write_tokens
     * @property Grid\Column|Collection cached_key_prefix
     * @property Grid\Column|Collection channel_affinity
     * @property Grid\Column|Collection channel_id
     * @property Grid\Column|Collection channel_name
     * @property Grid\Column|Collection client_ip
     * @property Grid\Column|Collection completion_tokens
     * @property Grid\Column|Collection cost
     * @property Grid\Column|Collection error_message
     * @property Grid\Column|Collection error_type
     * @property Grid\Column|Collection finish_reason
     * @property Grid\Column|Collection first_token_ms
     * @property Grid\Column|Collection group_name
     * @property Grid\Column|Collection is_stream
     * @property Grid\Column|Collection latency_ms
     * @property Grid\Column|Collection metadata
     * @property Grid\Column|Collection prompt_tokens
     * @property Grid\Column|Collection quota
     * @property Grid\Column|Collection request_id
     * @property Grid\Column|Collection request_type
     * @property Grid\Column|Collection run_unid
     * @property Grid\Column|Collection source_protocol
     * @property Grid\Column|Collection status_code
     * @property Grid\Column|Collection target_protocol
     * @property Grid\Column|Collection total_tokens
     * @property Grid\Column|Collection user_agent
     * @property Grid\Column|Collection expiration
     * @property Grid\Column|Collection owner
     * @property Grid\Column|Collection hit_count
     * @property Grid\Column|Collection key_hash
     * @property Grid\Column|Collection key_hint
     * @property Grid\Column|Collection rule_id
     * @property Grid\Column|Collection include_group_in_key
     * @property Grid\Column|Collection key_combine_strategy
     * @property Grid\Column|Collection key_sources
     * @property Grid\Column|Collection last_hit_at
     * @property Grid\Column|Collection model_patterns
     * @property Grid\Column|Collection param_override_template
     * @property Grid\Column|Collection path_patterns
     * @property Grid\Column|Collection priority
     * @property Grid\Column|Collection skip_retry_on_failure
     * @property Grid\Column|Collection ttl_seconds
     * @property Grid\Column|Collection user_agent_patterns
     * @property Grid\Column|Collection audit_log_id
     * @property Grid\Column|Collection ended_at
     * @property Grid\Column|Collection fallback_reason
     * @property Grid\Column|Collection hop_index
     * @property Grid\Column|Collection is_final
     * @property Grid\Column|Collection is_success
     * @property Grid\Column|Collection skip_reason
     * @property Grid\Column|Collection started_at
     * @property Grid\Column|Collection upstream_body_snippet
     * @property Grid\Column|Collection upstream_body_snippet_response
     * @property Grid\Column|Collection upstream_headers
     * @property Grid\Column|Collection upstream_headers_response
     * @property Grid\Column|Collection upstream_latency_ms
     * @property Grid\Column|Collection upstream_method
     * @property Grid\Column|Collection upstream_status
     * @property Grid\Column|Collection upstream_url
     * @property Grid\Column|Collection group_id
     * @property Grid\Column|Collection config
     * @property Grid\Column|Collection context_length
     * @property Grid\Column|Collection display_name
     * @property Grid\Column|Collection is_default
     * @property Grid\Column|Collection mapped_model
     * @property Grid\Column|Collection model_name
     * @property Grid\Column|Collection multiplier
     * @property Grid\Column|Collection rpm_limit
     * @property Grid\Column|Collection base_url
     * @property Grid\Column|Collection full_url
     * @property Grid\Column|Collection method
     * @property Grid\Column|Collection path
     * @property Grid\Column|Collection provider
     * @property Grid\Column|Collection request_body
     * @property Grid\Column|Collection request_headers
     * @property Grid\Column|Collection request_log_id
     * @property Grid\Column|Collection request_size
     * @property Grid\Column|Collection response_body
     * @property Grid\Column|Collection response_body_chunks
     * @property Grid\Column|Collection response_headers
     * @property Grid\Column|Collection response_size
     * @property Grid\Column|Collection response_status
     * @property Grid\Column|Collection sent_at
     * @property Grid\Column|Collection ttfb_ms
     * @property Grid\Column|Collection usage
     * @property Grid\Column|Collection tag_id
     * @property Grid\Column|Collection color
     * @property Grid\Column|Collection user_agent_id
     * @property Grid\Column|Collection api_key
     * @property Grid\Column|Collection api_key_hash
     * @property Grid\Column|Collection avg_latency_ms
     * @property Grid\Column|Collection coding_account_id
     * @property Grid\Column|Collection failure_count
     * @property Grid\Column|Collection forward_headers
     * @property Grid\Column|Collection has_user_agent_restriction
     * @property Grid\Column|Collection inherit_mode
     * @property Grid\Column|Collection last_check_at
     * @property Grid\Column|Collection last_failure_at
     * @property Grid\Column|Collection last_success_at
     * @property Grid\Column|Collection status2
     * @property Grid\Column|Collection status2_remark
     * @property Grid\Column|Collection success_count
     * @property Grid\Column|Collection success_rate
     * @property Grid\Column|Collection total_cost
     * @property Grid\Column|Collection total_requests
     * @property Grid\Column|Collection weight
     * @property Grid\Column|Collection account_id
     * @property Grid\Column|Collection last_sync_at
     * @property Grid\Column|Collection last_usage_at
     * @property Grid\Column|Collection limit_5h
     * @property Grid\Column|Collection limit_monthly
     * @property Grid\Column|Collection limit_weekly
     * @property Grid\Column|Collection period_5h
     * @property Grid\Column|Collection period_monthly
     * @property Grid\Column|Collection period_offset
     * @property Grid\Column|Collection period_weekly
     * @property Grid\Column|Collection reset_day
     * @property Grid\Column|Collection threshold_critical
     * @property Grid\Column|Collection threshold_disable
     * @property Grid\Column|Collection threshold_warning
     * @property Grid\Column|Collection used_5h
     * @property Grid\Column|Collection used_monthly
     * @property Grid\Column|Collection used_weekly
     * @property Grid\Column|Collection from_status
     * @property Grid\Column|Collection quota_5h_limit
     * @property Grid\Column|Collection quota_5h_rate
     * @property Grid\Column|Collection quota_5h_used
     * @property Grid\Column|Collection quota_monthly_limit
     * @property Grid\Column|Collection quota_monthly_rate
     * @property Grid\Column|Collection quota_monthly_used
     * @property Grid\Column|Collection quota_weekly_limit
     * @property Grid\Column|Collection quota_weekly_rate
     * @property Grid\Column|Collection quota_weekly_used
     * @property Grid\Column|Collection reason
     * @property Grid\Column|Collection to_status
     * @property Grid\Column|Collection triggered_by
     * @property Grid\Column|Collection model_multiplier
     * @property Grid\Column|Collection quota_after_5h
     * @property Grid\Column|Collection quota_after_monthly
     * @property Grid\Column|Collection quota_after_weekly
     * @property Grid\Column|Collection quota_before_5h
     * @property Grid\Column|Collection quota_before_monthly
     * @property Grid\Column|Collection quota_before_weekly
     * @property Grid\Column|Collection requests
     * @property Grid\Column|Collection credentials
     * @property Grid\Column|Collection disabled_at
     * @property Grid\Column|Collection driver_class
     * @property Grid\Column|Collection platform
     * @property Grid\Column|Collection status_override
     * @property Grid\Column|Collection sync_error
     * @property Grid\Column|Collection sync_error_count
     * @property Grid\Column|Collection metric
     * @property Grid\Column|Collection period_ends_at
     * @property Grid\Column|Collection period_key
     * @property Grid\Column|Collection period_starts_at
     * @property Grid\Column|Collection period_type
     * @property Grid\Column|Collection used
     * @property Grid\Column|Collection tokens_input
     * @property Grid\Column|Collection tokens_output
     * @property Grid\Column|Collection tokens_total
     * @property Grid\Column|Collection window_id
     * @property Grid\Column|Collection ends_at
     * @property Grid\Column|Collection window_seconds
     * @property Grid\Column|Collection window_type
     * @property Grid\Column|Collection quota_snapshot
     * @property Grid\Column|Collection credits
     * @property Grid\Column|Collection prompts
     * @property Grid\Column|Collection connection
     * @property Grid\Column|Collection exception
     * @property Grid\Column|Collection failed_at
     * @property Grid\Column|Collection payload
     * @property Grid\Column|Collection queue
     * @property Grid\Column|Collection uuid
     * @property Grid\Column|Collection cancelled_at
     * @property Grid\Column|Collection failed_job_ids
     * @property Grid\Column|Collection failed_jobs
     * @property Grid\Column|Collection finished_at
     * @property Grid\Column|Collection pending_jobs
     * @property Grid\Column|Collection total_jobs
     * @property Grid\Column|Collection attempts
     * @property Grid\Column|Collection available_at
     * @property Grid\Column|Collection reserved_at
     * @property Grid\Column|Collection archive_enabled
     * @property Grid\Column|Collection archive_storage
     * @property Grid\Column|Collection mask_sensitive
     * @property Grid\Column|Collection max_body_length
     * @property Grid\Column|Collection retention_days
     * @property Grid\Column|Collection sample_rate
     * @property Grid\Column|Collection scope
     * @property Grid\Column|Collection scope_id
     * @property Grid\Column|Collection sensitive_patterns
     * @property Grid\Column|Collection store_generated_text
     * @property Grid\Column|Collection store_headers
     * @property Grid\Column|Collection store_messages
     * @property Grid\Column|Collection store_request_body
     * @property Grid\Column|Collection store_response_body
     * @property Grid\Column|Collection capabilities
     * @property Grid\Column|Collection common_name
     * @property Grid\Column|Collection hugging_face_id
     * @property Grid\Column|Collection pricing_completion
     * @property Grid\Column|Collection pricing_input_cache_read
     * @property Grid\Column|Collection pricing_prompt
     * @property Grid\Column|Collection assistant_response
     * @property Grid\Column|Collection prompt_preset_id
     * @property Grid\Column|Collection response_time_ms
     * @property Grid\Column|Collection system_prompt
     * @property Grid\Column|Collection test_type
     * @property Grid\Column|Collection user_message
     * @property Grid\Column|Collection after_data
     * @property Grid\Column|Collection before_data
     * @property Grid\Column|Collection ip
     * @property Grid\Column|Collection source
     * @property Grid\Column|Collection target
     * @property Grid\Column|Collection target_id
     * @property Grid\Column|Collection target_name
     * @property Grid\Column|Collection email
     * @property Grid\Column|Collection token
     * @property Grid\Column|Collection category
     * @property Grid\Column|Collection content
     * @property Grid\Column|Collection headers
     * @property Grid\Column|Collection sort_order
     * @property Grid\Column|Collection body_binary
     * @property Grid\Column|Collection body_text
     * @property Grid\Column|Collection content_length
     * @property Grid\Column|Collection content_type
     * @property Grid\Column|Collection has_sensitive
     * @property Grid\Column|Collection messages
     * @property Grid\Column|Collection model_params
     * @property Grid\Column|Collection prompt
     * @property Grid\Column|Collection query_string
     * @property Grid\Column|Collection sensitive_fields
     * @property Grid\Column|Collection upstream_model
     * @property Grid\Column|Collection error_code
     * @property Grid\Column|Collection error_details
     * @property Grid\Column|Collection generated_chunks
     * @property Grid\Column|Collection generated_text
     * @property Grid\Column|Collection response_type
     * @property Grid\Column|Collection status_message
     * @property Grid\Column|Collection upstream_provider
     * @property Grid\Column|Collection ip_address
     * @property Grid\Column|Collection last_activity
     * @property Grid\Column|Collection group
     * @property Grid\Column|Collection is_public
     * @property Grid\Column|Collection label
     * @property Grid\Column|Collection patterns
     * @property Grid\Column|Collection currency
     * @property Grid\Column|Collection email_verified_at
     * @property Grid\Column|Collection locale
     *
     * @method Grid\Column|Collection quickCreateButton(string $label = null)
     * @method Grid\Column|Collection created_at(string $label = null)
     * @method Grid\Column|Collection detail(string $label = null)
     * @method Grid\Column|Collection id(string $label = null)
     * @method Grid\Column|Collection name(string $label = null)
     * @method Grid\Column|Collection type(string $label = null)
     * @method Grid\Column|Collection updated_at(string $label = null)
     * @method Grid\Column|Collection version(string $label = null)
     * @method Grid\Column|Collection is_enabled(string $label = null)
     * @method Grid\Column|Collection extension(string $label = null)
     * @method Grid\Column|Collection icon(string $label = null)
     * @method Grid\Column|Collection order(string $label = null)
     * @method Grid\Column|Collection parent_id(string $label = null)
     * @method Grid\Column|Collection uri(string $label = null)
     * @method Grid\Column|Collection menu_id(string $label = null)
     * @method Grid\Column|Collection permission_id(string $label = null)
     * @method Grid\Column|Collection http_method(string $label = null)
     * @method Grid\Column|Collection http_path(string $label = null)
     * @method Grid\Column|Collection slug(string $label = null)
     * @method Grid\Column|Collection role_id(string $label = null)
     * @method Grid\Column|Collection user_id(string $label = null)
     * @method Grid\Column|Collection value(string $label = null)
     * @method Grid\Column|Collection avatar(string $label = null)
     * @method Grid\Column|Collection language(string $label = null)
     * @method Grid\Column|Collection password(string $label = null)
     * @method Grid\Column|Collection remember_token(string $label = null)
     * @method Grid\Column|Collection username(string $label = null)
     * @method Grid\Column|Collection allowed_channels(string $label = null)
     * @method Grid\Column|Collection allowed_models(string $label = null)
     * @method Grid\Column|Collection deleted_at(string $label = null)
     * @method Grid\Column|Collection expires_at(string $label = null)
     * @method Grid\Column|Collection key(string $label = null)
     * @method Grid\Column|Collection last_used_at(string $label = null)
     * @method Grid\Column|Collection model_mappings(string $label = null)
     * @method Grid\Column|Collection not_allowed_channels(string $label = null)
     * @method Grid\Column|Collection permissions(string $label = null)
     * @method Grid\Column|Collection rate_limit(string $label = null)
     * @method Grid\Column|Collection status(string $label = null)
     * @method Grid\Column|Collection actual_model(string $label = null)
     * @method Grid\Column|Collection api_key_id(string $label = null)
     * @method Grid\Column|Collection api_key_name(string $label = null)
     * @method Grid\Column|Collection billing_source(string $label = null)
     * @method Grid\Column|Collection cache_read_tokens(string $label = null)
     * @method Grid\Column|Collection cache_write_tokens(string $label = null)
     * @method Grid\Column|Collection cached_key_prefix(string $label = null)
     * @method Grid\Column|Collection channel_affinity(string $label = null)
     * @method Grid\Column|Collection channel_id(string $label = null)
     * @method Grid\Column|Collection channel_name(string $label = null)
     * @method Grid\Column|Collection client_ip(string $label = null)
     * @method Grid\Column|Collection completion_tokens(string $label = null)
     * @method Grid\Column|Collection cost(string $label = null)
     * @method Grid\Column|Collection error_message(string $label = null)
     * @method Grid\Column|Collection error_type(string $label = null)
     * @method Grid\Column|Collection finish_reason(string $label = null)
     * @method Grid\Column|Collection first_token_ms(string $label = null)
     * @method Grid\Column|Collection group_name(string $label = null)
     * @method Grid\Column|Collection is_stream(string $label = null)
     * @method Grid\Column|Collection latency_ms(string $label = null)
     * @method Grid\Column|Collection metadata(string $label = null)
     * @method Grid\Column|Collection prompt_tokens(string $label = null)
     * @method Grid\Column|Collection quota(string $label = null)
     * @method Grid\Column|Collection request_id(string $label = null)
     * @method Grid\Column|Collection request_type(string $label = null)
     * @method Grid\Column|Collection run_unid(string $label = null)
     * @method Grid\Column|Collection source_protocol(string $label = null)
     * @method Grid\Column|Collection status_code(string $label = null)
     * @method Grid\Column|Collection target_protocol(string $label = null)
     * @method Grid\Column|Collection total_tokens(string $label = null)
     * @method Grid\Column|Collection user_agent(string $label = null)
     * @method Grid\Column|Collection expiration(string $label = null)
     * @method Grid\Column|Collection owner(string $label = null)
     * @method Grid\Column|Collection hit_count(string $label = null)
     * @method Grid\Column|Collection key_hash(string $label = null)
     * @method Grid\Column|Collection key_hint(string $label = null)
     * @method Grid\Column|Collection rule_id(string $label = null)
     * @method Grid\Column|Collection include_group_in_key(string $label = null)
     * @method Grid\Column|Collection key_combine_strategy(string $label = null)
     * @method Grid\Column|Collection key_sources(string $label = null)
     * @method Grid\Column|Collection last_hit_at(string $label = null)
     * @method Grid\Column|Collection model_patterns(string $label = null)
     * @method Grid\Column|Collection param_override_template(string $label = null)
     * @method Grid\Column|Collection path_patterns(string $label = null)
     * @method Grid\Column|Collection priority(string $label = null)
     * @method Grid\Column|Collection skip_retry_on_failure(string $label = null)
     * @method Grid\Column|Collection ttl_seconds(string $label = null)
     * @method Grid\Column|Collection user_agent_patterns(string $label = null)
     * @method Grid\Column|Collection audit_log_id(string $label = null)
     * @method Grid\Column|Collection ended_at(string $label = null)
     * @method Grid\Column|Collection fallback_reason(string $label = null)
     * @method Grid\Column|Collection hop_index(string $label = null)
     * @method Grid\Column|Collection is_final(string $label = null)
     * @method Grid\Column|Collection is_success(string $label = null)
     * @method Grid\Column|Collection skip_reason(string $label = null)
     * @method Grid\Column|Collection started_at(string $label = null)
     * @method Grid\Column|Collection upstream_body_snippet(string $label = null)
     * @method Grid\Column|Collection upstream_body_snippet_response(string $label = null)
     * @method Grid\Column|Collection upstream_headers(string $label = null)
     * @method Grid\Column|Collection upstream_headers_response(string $label = null)
     * @method Grid\Column|Collection upstream_latency_ms(string $label = null)
     * @method Grid\Column|Collection upstream_method(string $label = null)
     * @method Grid\Column|Collection upstream_status(string $label = null)
     * @method Grid\Column|Collection upstream_url(string $label = null)
     * @method Grid\Column|Collection group_id(string $label = null)
     * @method Grid\Column|Collection config(string $label = null)
     * @method Grid\Column|Collection context_length(string $label = null)
     * @method Grid\Column|Collection display_name(string $label = null)
     * @method Grid\Column|Collection is_default(string $label = null)
     * @method Grid\Column|Collection mapped_model(string $label = null)
     * @method Grid\Column|Collection model_name(string $label = null)
     * @method Grid\Column|Collection multiplier(string $label = null)
     * @method Grid\Column|Collection rpm_limit(string $label = null)
     * @method Grid\Column|Collection base_url(string $label = null)
     * @method Grid\Column|Collection full_url(string $label = null)
     * @method Grid\Column|Collection method(string $label = null)
     * @method Grid\Column|Collection path(string $label = null)
     * @method Grid\Column|Collection provider(string $label = null)
     * @method Grid\Column|Collection request_body(string $label = null)
     * @method Grid\Column|Collection request_headers(string $label = null)
     * @method Grid\Column|Collection request_log_id(string $label = null)
     * @method Grid\Column|Collection request_size(string $label = null)
     * @method Grid\Column|Collection response_body(string $label = null)
     * @method Grid\Column|Collection response_body_chunks(string $label = null)
     * @method Grid\Column|Collection response_headers(string $label = null)
     * @method Grid\Column|Collection response_size(string $label = null)
     * @method Grid\Column|Collection response_status(string $label = null)
     * @method Grid\Column|Collection sent_at(string $label = null)
     * @method Grid\Column|Collection ttfb_ms(string $label = null)
     * @method Grid\Column|Collection usage(string $label = null)
     * @method Grid\Column|Collection tag_id(string $label = null)
     * @method Grid\Column|Collection color(string $label = null)
     * @method Grid\Column|Collection user_agent_id(string $label = null)
     * @method Grid\Column|Collection api_key(string $label = null)
     * @method Grid\Column|Collection api_key_hash(string $label = null)
     * @method Grid\Column|Collection avg_latency_ms(string $label = null)
     * @method Grid\Column|Collection coding_account_id(string $label = null)
     * @method Grid\Column|Collection failure_count(string $label = null)
     * @method Grid\Column|Collection forward_headers(string $label = null)
     * @method Grid\Column|Collection has_user_agent_restriction(string $label = null)
     * @method Grid\Column|Collection inherit_mode(string $label = null)
     * @method Grid\Column|Collection last_check_at(string $label = null)
     * @method Grid\Column|Collection last_failure_at(string $label = null)
     * @method Grid\Column|Collection last_success_at(string $label = null)
     * @method Grid\Column|Collection status2(string $label = null)
     * @method Grid\Column|Collection status2_remark(string $label = null)
     * @method Grid\Column|Collection success_count(string $label = null)
     * @method Grid\Column|Collection success_rate(string $label = null)
     * @method Grid\Column|Collection total_cost(string $label = null)
     * @method Grid\Column|Collection total_requests(string $label = null)
     * @method Grid\Column|Collection weight(string $label = null)
     * @method Grid\Column|Collection account_id(string $label = null)
     * @method Grid\Column|Collection last_sync_at(string $label = null)
     * @method Grid\Column|Collection last_usage_at(string $label = null)
     * @method Grid\Column|Collection limit_5h(string $label = null)
     * @method Grid\Column|Collection limit_monthly(string $label = null)
     * @method Grid\Column|Collection limit_weekly(string $label = null)
     * @method Grid\Column|Collection period_5h(string $label = null)
     * @method Grid\Column|Collection period_monthly(string $label = null)
     * @method Grid\Column|Collection period_offset(string $label = null)
     * @method Grid\Column|Collection period_weekly(string $label = null)
     * @method Grid\Column|Collection reset_day(string $label = null)
     * @method Grid\Column|Collection threshold_critical(string $label = null)
     * @method Grid\Column|Collection threshold_disable(string $label = null)
     * @method Grid\Column|Collection threshold_warning(string $label = null)
     * @method Grid\Column|Collection used_5h(string $label = null)
     * @method Grid\Column|Collection used_monthly(string $label = null)
     * @method Grid\Column|Collection used_weekly(string $label = null)
     * @method Grid\Column|Collection from_status(string $label = null)
     * @method Grid\Column|Collection quota_5h_limit(string $label = null)
     * @method Grid\Column|Collection quota_5h_rate(string $label = null)
     * @method Grid\Column|Collection quota_5h_used(string $label = null)
     * @method Grid\Column|Collection quota_monthly_limit(string $label = null)
     * @method Grid\Column|Collection quota_monthly_rate(string $label = null)
     * @method Grid\Column|Collection quota_monthly_used(string $label = null)
     * @method Grid\Column|Collection quota_weekly_limit(string $label = null)
     * @method Grid\Column|Collection quota_weekly_rate(string $label = null)
     * @method Grid\Column|Collection quota_weekly_used(string $label = null)
     * @method Grid\Column|Collection reason(string $label = null)
     * @method Grid\Column|Collection to_status(string $label = null)
     * @method Grid\Column|Collection triggered_by(string $label = null)
     * @method Grid\Column|Collection model_multiplier(string $label = null)
     * @method Grid\Column|Collection quota_after_5h(string $label = null)
     * @method Grid\Column|Collection quota_after_monthly(string $label = null)
     * @method Grid\Column|Collection quota_after_weekly(string $label = null)
     * @method Grid\Column|Collection quota_before_5h(string $label = null)
     * @method Grid\Column|Collection quota_before_monthly(string $label = null)
     * @method Grid\Column|Collection quota_before_weekly(string $label = null)
     * @method Grid\Column|Collection requests(string $label = null)
     * @method Grid\Column|Collection credentials(string $label = null)
     * @method Grid\Column|Collection disabled_at(string $label = null)
     * @method Grid\Column|Collection driver_class(string $label = null)
     * @method Grid\Column|Collection platform(string $label = null)
     * @method Grid\Column|Collection status_override(string $label = null)
     * @method Grid\Column|Collection sync_error(string $label = null)
     * @method Grid\Column|Collection sync_error_count(string $label = null)
     * @method Grid\Column|Collection metric(string $label = null)
     * @method Grid\Column|Collection period_ends_at(string $label = null)
     * @method Grid\Column|Collection period_key(string $label = null)
     * @method Grid\Column|Collection period_starts_at(string $label = null)
     * @method Grid\Column|Collection period_type(string $label = null)
     * @method Grid\Column|Collection used(string $label = null)
     * @method Grid\Column|Collection tokens_input(string $label = null)
     * @method Grid\Column|Collection tokens_output(string $label = null)
     * @method Grid\Column|Collection tokens_total(string $label = null)
     * @method Grid\Column|Collection window_id(string $label = null)
     * @method Grid\Column|Collection ends_at(string $label = null)
     * @method Grid\Column|Collection window_seconds(string $label = null)
     * @method Grid\Column|Collection window_type(string $label = null)
     * @method Grid\Column|Collection quota_snapshot(string $label = null)
     * @method Grid\Column|Collection credits(string $label = null)
     * @method Grid\Column|Collection prompts(string $label = null)
     * @method Grid\Column|Collection connection(string $label = null)
     * @method Grid\Column|Collection exception(string $label = null)
     * @method Grid\Column|Collection failed_at(string $label = null)
     * @method Grid\Column|Collection payload(string $label = null)
     * @method Grid\Column|Collection queue(string $label = null)
     * @method Grid\Column|Collection uuid(string $label = null)
     * @method Grid\Column|Collection cancelled_at(string $label = null)
     * @method Grid\Column|Collection failed_job_ids(string $label = null)
     * @method Grid\Column|Collection failed_jobs(string $label = null)
     * @method Grid\Column|Collection finished_at(string $label = null)
     * @method Grid\Column|Collection pending_jobs(string $label = null)
     * @method Grid\Column|Collection total_jobs(string $label = null)
     * @method Grid\Column|Collection attempts(string $label = null)
     * @method Grid\Column|Collection available_at(string $label = null)
     * @method Grid\Column|Collection reserved_at(string $label = null)
     * @method Grid\Column|Collection archive_enabled(string $label = null)
     * @method Grid\Column|Collection archive_storage(string $label = null)
     * @method Grid\Column|Collection mask_sensitive(string $label = null)
     * @method Grid\Column|Collection max_body_length(string $label = null)
     * @method Grid\Column|Collection retention_days(string $label = null)
     * @method Grid\Column|Collection sample_rate(string $label = null)
     * @method Grid\Column|Collection scope(string $label = null)
     * @method Grid\Column|Collection scope_id(string $label = null)
     * @method Grid\Column|Collection sensitive_patterns(string $label = null)
     * @method Grid\Column|Collection store_generated_text(string $label = null)
     * @method Grid\Column|Collection store_headers(string $label = null)
     * @method Grid\Column|Collection store_messages(string $label = null)
     * @method Grid\Column|Collection store_request_body(string $label = null)
     * @method Grid\Column|Collection store_response_body(string $label = null)
     * @method Grid\Column|Collection capabilities(string $label = null)
     * @method Grid\Column|Collection common_name(string $label = null)
     * @method Grid\Column|Collection hugging_face_id(string $label = null)
     * @method Grid\Column|Collection pricing_completion(string $label = null)
     * @method Grid\Column|Collection pricing_input_cache_read(string $label = null)
     * @method Grid\Column|Collection pricing_prompt(string $label = null)
     * @method Grid\Column|Collection assistant_response(string $label = null)
     * @method Grid\Column|Collection prompt_preset_id(string $label = null)
     * @method Grid\Column|Collection response_time_ms(string $label = null)
     * @method Grid\Column|Collection system_prompt(string $label = null)
     * @method Grid\Column|Collection test_type(string $label = null)
     * @method Grid\Column|Collection user_message(string $label = null)
     * @method Grid\Column|Collection after_data(string $label = null)
     * @method Grid\Column|Collection before_data(string $label = null)
     * @method Grid\Column|Collection ip(string $label = null)
     * @method Grid\Column|Collection source(string $label = null)
     * @method Grid\Column|Collection target(string $label = null)
     * @method Grid\Column|Collection target_id(string $label = null)
     * @method Grid\Column|Collection target_name(string $label = null)
     * @method Grid\Column|Collection email(string $label = null)
     * @method Grid\Column|Collection token(string $label = null)
     * @method Grid\Column|Collection category(string $label = null)
     * @method Grid\Column|Collection content(string $label = null)
     * @method Grid\Column|Collection headers(string $label = null)
     * @method Grid\Column|Collection sort_order(string $label = null)
     * @method Grid\Column|Collection body_binary(string $label = null)
     * @method Grid\Column|Collection body_text(string $label = null)
     * @method Grid\Column|Collection content_length(string $label = null)
     * @method Grid\Column|Collection content_type(string $label = null)
     * @method Grid\Column|Collection has_sensitive(string $label = null)
     * @method Grid\Column|Collection messages(string $label = null)
     * @method Grid\Column|Collection model_params(string $label = null)
     * @method Grid\Column|Collection prompt(string $label = null)
     * @method Grid\Column|Collection query_string(string $label = null)
     * @method Grid\Column|Collection sensitive_fields(string $label = null)
     * @method Grid\Column|Collection upstream_model(string $label = null)
     * @method Grid\Column|Collection error_code(string $label = null)
     * @method Grid\Column|Collection error_details(string $label = null)
     * @method Grid\Column|Collection generated_chunks(string $label = null)
     * @method Grid\Column|Collection generated_text(string $label = null)
     * @method Grid\Column|Collection response_type(string $label = null)
     * @method Grid\Column|Collection status_message(string $label = null)
     * @method Grid\Column|Collection upstream_provider(string $label = null)
     * @method Grid\Column|Collection ip_address(string $label = null)
     * @method Grid\Column|Collection last_activity(string $label = null)
     * @method Grid\Column|Collection group(string $label = null)
     * @method Grid\Column|Collection is_public(string $label = null)
     * @method Grid\Column|Collection label(string $label = null)
     * @method Grid\Column|Collection patterns(string $label = null)
     * @method Grid\Column|Collection currency(string $label = null)
     * @method Grid\Column|Collection email_verified_at(string $label = null)
     * @method Grid\Column|Collection locale(string $label = null)
     */
    class Grid {}

    class MiniGrid extends Grid {}

    /**
     * @property Show\Field|Collection quickCreateButton
     * @property Show\Field|Collection created_at
     * @property Show\Field|Collection detail
     * @property Show\Field|Collection id
     * @property Show\Field|Collection name
     * @property Show\Field|Collection type
     * @property Show\Field|Collection updated_at
     * @property Show\Field|Collection version
     * @property Show\Field|Collection is_enabled
     * @property Show\Field|Collection extension
     * @property Show\Field|Collection icon
     * @property Show\Field|Collection order
     * @property Show\Field|Collection parent_id
     * @property Show\Field|Collection uri
     * @property Show\Field|Collection menu_id
     * @property Show\Field|Collection permission_id
     * @property Show\Field|Collection http_method
     * @property Show\Field|Collection http_path
     * @property Show\Field|Collection slug
     * @property Show\Field|Collection role_id
     * @property Show\Field|Collection user_id
     * @property Show\Field|Collection value
     * @property Show\Field|Collection avatar
     * @property Show\Field|Collection language
     * @property Show\Field|Collection password
     * @property Show\Field|Collection remember_token
     * @property Show\Field|Collection username
     * @property Show\Field|Collection allowed_channels
     * @property Show\Field|Collection allowed_models
     * @property Show\Field|Collection deleted_at
     * @property Show\Field|Collection expires_at
     * @property Show\Field|Collection key
     * @property Show\Field|Collection last_used_at
     * @property Show\Field|Collection model_mappings
     * @property Show\Field|Collection not_allowed_channels
     * @property Show\Field|Collection permissions
     * @property Show\Field|Collection rate_limit
     * @property Show\Field|Collection status
     * @property Show\Field|Collection actual_model
     * @property Show\Field|Collection api_key_id
     * @property Show\Field|Collection api_key_name
     * @property Show\Field|Collection billing_source
     * @property Show\Field|Collection cache_read_tokens
     * @property Show\Field|Collection cache_write_tokens
     * @property Show\Field|Collection cached_key_prefix
     * @property Show\Field|Collection channel_affinity
     * @property Show\Field|Collection channel_id
     * @property Show\Field|Collection channel_name
     * @property Show\Field|Collection client_ip
     * @property Show\Field|Collection completion_tokens
     * @property Show\Field|Collection cost
     * @property Show\Field|Collection error_message
     * @property Show\Field|Collection error_type
     * @property Show\Field|Collection finish_reason
     * @property Show\Field|Collection first_token_ms
     * @property Show\Field|Collection group_name
     * @property Show\Field|Collection is_stream
     * @property Show\Field|Collection latency_ms
     * @property Show\Field|Collection metadata
     * @property Show\Field|Collection prompt_tokens
     * @property Show\Field|Collection quota
     * @property Show\Field|Collection request_id
     * @property Show\Field|Collection request_type
     * @property Show\Field|Collection run_unid
     * @property Show\Field|Collection source_protocol
     * @property Show\Field|Collection status_code
     * @property Show\Field|Collection target_protocol
     * @property Show\Field|Collection total_tokens
     * @property Show\Field|Collection user_agent
     * @property Show\Field|Collection expiration
     * @property Show\Field|Collection owner
     * @property Show\Field|Collection hit_count
     * @property Show\Field|Collection key_hash
     * @property Show\Field|Collection key_hint
     * @property Show\Field|Collection rule_id
     * @property Show\Field|Collection include_group_in_key
     * @property Show\Field|Collection key_combine_strategy
     * @property Show\Field|Collection key_sources
     * @property Show\Field|Collection last_hit_at
     * @property Show\Field|Collection model_patterns
     * @property Show\Field|Collection param_override_template
     * @property Show\Field|Collection path_patterns
     * @property Show\Field|Collection priority
     * @property Show\Field|Collection skip_retry_on_failure
     * @property Show\Field|Collection ttl_seconds
     * @property Show\Field|Collection user_agent_patterns
     * @property Show\Field|Collection audit_log_id
     * @property Show\Field|Collection ended_at
     * @property Show\Field|Collection fallback_reason
     * @property Show\Field|Collection hop_index
     * @property Show\Field|Collection is_final
     * @property Show\Field|Collection is_success
     * @property Show\Field|Collection skip_reason
     * @property Show\Field|Collection started_at
     * @property Show\Field|Collection upstream_body_snippet
     * @property Show\Field|Collection upstream_body_snippet_response
     * @property Show\Field|Collection upstream_headers
     * @property Show\Field|Collection upstream_headers_response
     * @property Show\Field|Collection upstream_latency_ms
     * @property Show\Field|Collection upstream_method
     * @property Show\Field|Collection upstream_status
     * @property Show\Field|Collection upstream_url
     * @property Show\Field|Collection group_id
     * @property Show\Field|Collection config
     * @property Show\Field|Collection context_length
     * @property Show\Field|Collection display_name
     * @property Show\Field|Collection is_default
     * @property Show\Field|Collection mapped_model
     * @property Show\Field|Collection model_name
     * @property Show\Field|Collection multiplier
     * @property Show\Field|Collection rpm_limit
     * @property Show\Field|Collection base_url
     * @property Show\Field|Collection full_url
     * @property Show\Field|Collection method
     * @property Show\Field|Collection path
     * @property Show\Field|Collection provider
     * @property Show\Field|Collection request_body
     * @property Show\Field|Collection request_headers
     * @property Show\Field|Collection request_log_id
     * @property Show\Field|Collection request_size
     * @property Show\Field|Collection response_body
     * @property Show\Field|Collection response_body_chunks
     * @property Show\Field|Collection response_headers
     * @property Show\Field|Collection response_size
     * @property Show\Field|Collection response_status
     * @property Show\Field|Collection sent_at
     * @property Show\Field|Collection ttfb_ms
     * @property Show\Field|Collection usage
     * @property Show\Field|Collection tag_id
     * @property Show\Field|Collection color
     * @property Show\Field|Collection user_agent_id
     * @property Show\Field|Collection api_key
     * @property Show\Field|Collection api_key_hash
     * @property Show\Field|Collection avg_latency_ms
     * @property Show\Field|Collection coding_account_id
     * @property Show\Field|Collection failure_count
     * @property Show\Field|Collection forward_headers
     * @property Show\Field|Collection has_user_agent_restriction
     * @property Show\Field|Collection inherit_mode
     * @property Show\Field|Collection last_check_at
     * @property Show\Field|Collection last_failure_at
     * @property Show\Field|Collection last_success_at
     * @property Show\Field|Collection status2
     * @property Show\Field|Collection status2_remark
     * @property Show\Field|Collection success_count
     * @property Show\Field|Collection success_rate
     * @property Show\Field|Collection total_cost
     * @property Show\Field|Collection total_requests
     * @property Show\Field|Collection weight
     * @property Show\Field|Collection account_id
     * @property Show\Field|Collection last_sync_at
     * @property Show\Field|Collection last_usage_at
     * @property Show\Field|Collection limit_5h
     * @property Show\Field|Collection limit_monthly
     * @property Show\Field|Collection limit_weekly
     * @property Show\Field|Collection period_5h
     * @property Show\Field|Collection period_monthly
     * @property Show\Field|Collection period_offset
     * @property Show\Field|Collection period_weekly
     * @property Show\Field|Collection reset_day
     * @property Show\Field|Collection threshold_critical
     * @property Show\Field|Collection threshold_disable
     * @property Show\Field|Collection threshold_warning
     * @property Show\Field|Collection used_5h
     * @property Show\Field|Collection used_monthly
     * @property Show\Field|Collection used_weekly
     * @property Show\Field|Collection from_status
     * @property Show\Field|Collection quota_5h_limit
     * @property Show\Field|Collection quota_5h_rate
     * @property Show\Field|Collection quota_5h_used
     * @property Show\Field|Collection quota_monthly_limit
     * @property Show\Field|Collection quota_monthly_rate
     * @property Show\Field|Collection quota_monthly_used
     * @property Show\Field|Collection quota_weekly_limit
     * @property Show\Field|Collection quota_weekly_rate
     * @property Show\Field|Collection quota_weekly_used
     * @property Show\Field|Collection reason
     * @property Show\Field|Collection to_status
     * @property Show\Field|Collection triggered_by
     * @property Show\Field|Collection model_multiplier
     * @property Show\Field|Collection quota_after_5h
     * @property Show\Field|Collection quota_after_monthly
     * @property Show\Field|Collection quota_after_weekly
     * @property Show\Field|Collection quota_before_5h
     * @property Show\Field|Collection quota_before_monthly
     * @property Show\Field|Collection quota_before_weekly
     * @property Show\Field|Collection requests
     * @property Show\Field|Collection credentials
     * @property Show\Field|Collection disabled_at
     * @property Show\Field|Collection driver_class
     * @property Show\Field|Collection platform
     * @property Show\Field|Collection status_override
     * @property Show\Field|Collection sync_error
     * @property Show\Field|Collection sync_error_count
     * @property Show\Field|Collection metric
     * @property Show\Field|Collection period_ends_at
     * @property Show\Field|Collection period_key
     * @property Show\Field|Collection period_starts_at
     * @property Show\Field|Collection period_type
     * @property Show\Field|Collection used
     * @property Show\Field|Collection tokens_input
     * @property Show\Field|Collection tokens_output
     * @property Show\Field|Collection tokens_total
     * @property Show\Field|Collection window_id
     * @property Show\Field|Collection ends_at
     * @property Show\Field|Collection window_seconds
     * @property Show\Field|Collection window_type
     * @property Show\Field|Collection quota_snapshot
     * @property Show\Field|Collection credits
     * @property Show\Field|Collection prompts
     * @property Show\Field|Collection connection
     * @property Show\Field|Collection exception
     * @property Show\Field|Collection failed_at
     * @property Show\Field|Collection payload
     * @property Show\Field|Collection queue
     * @property Show\Field|Collection uuid
     * @property Show\Field|Collection cancelled_at
     * @property Show\Field|Collection failed_job_ids
     * @property Show\Field|Collection failed_jobs
     * @property Show\Field|Collection finished_at
     * @property Show\Field|Collection pending_jobs
     * @property Show\Field|Collection total_jobs
     * @property Show\Field|Collection attempts
     * @property Show\Field|Collection available_at
     * @property Show\Field|Collection reserved_at
     * @property Show\Field|Collection archive_enabled
     * @property Show\Field|Collection archive_storage
     * @property Show\Field|Collection mask_sensitive
     * @property Show\Field|Collection max_body_length
     * @property Show\Field|Collection retention_days
     * @property Show\Field|Collection sample_rate
     * @property Show\Field|Collection scope
     * @property Show\Field|Collection scope_id
     * @property Show\Field|Collection sensitive_patterns
     * @property Show\Field|Collection store_generated_text
     * @property Show\Field|Collection store_headers
     * @property Show\Field|Collection store_messages
     * @property Show\Field|Collection store_request_body
     * @property Show\Field|Collection store_response_body
     * @property Show\Field|Collection capabilities
     * @property Show\Field|Collection common_name
     * @property Show\Field|Collection hugging_face_id
     * @property Show\Field|Collection pricing_completion
     * @property Show\Field|Collection pricing_input_cache_read
     * @property Show\Field|Collection pricing_prompt
     * @property Show\Field|Collection assistant_response
     * @property Show\Field|Collection prompt_preset_id
     * @property Show\Field|Collection response_time_ms
     * @property Show\Field|Collection system_prompt
     * @property Show\Field|Collection test_type
     * @property Show\Field|Collection user_message
     * @property Show\Field|Collection after_data
     * @property Show\Field|Collection before_data
     * @property Show\Field|Collection ip
     * @property Show\Field|Collection source
     * @property Show\Field|Collection target
     * @property Show\Field|Collection target_id
     * @property Show\Field|Collection target_name
     * @property Show\Field|Collection email
     * @property Show\Field|Collection token
     * @property Show\Field|Collection category
     * @property Show\Field|Collection content
     * @property Show\Field|Collection headers
     * @property Show\Field|Collection sort_order
     * @property Show\Field|Collection body_binary
     * @property Show\Field|Collection body_text
     * @property Show\Field|Collection content_length
     * @property Show\Field|Collection content_type
     * @property Show\Field|Collection has_sensitive
     * @property Show\Field|Collection messages
     * @property Show\Field|Collection model_params
     * @property Show\Field|Collection prompt
     * @property Show\Field|Collection query_string
     * @property Show\Field|Collection sensitive_fields
     * @property Show\Field|Collection upstream_model
     * @property Show\Field|Collection error_code
     * @property Show\Field|Collection error_details
     * @property Show\Field|Collection generated_chunks
     * @property Show\Field|Collection generated_text
     * @property Show\Field|Collection response_type
     * @property Show\Field|Collection status_message
     * @property Show\Field|Collection upstream_provider
     * @property Show\Field|Collection ip_address
     * @property Show\Field|Collection last_activity
     * @property Show\Field|Collection group
     * @property Show\Field|Collection is_public
     * @property Show\Field|Collection label
     * @property Show\Field|Collection patterns
     * @property Show\Field|Collection currency
     * @property Show\Field|Collection email_verified_at
     * @property Show\Field|Collection locale
     *
     * @method Show\Field|Collection quickCreateButton(string $label = null)
     * @method Show\Field|Collection created_at(string $label = null)
     * @method Show\Field|Collection detail(string $label = null)
     * @method Show\Field|Collection id(string $label = null)
     * @method Show\Field|Collection name(string $label = null)
     * @method Show\Field|Collection type(string $label = null)
     * @method Show\Field|Collection updated_at(string $label = null)
     * @method Show\Field|Collection version(string $label = null)
     * @method Show\Field|Collection is_enabled(string $label = null)
     * @method Show\Field|Collection extension(string $label = null)
     * @method Show\Field|Collection icon(string $label = null)
     * @method Show\Field|Collection order(string $label = null)
     * @method Show\Field|Collection parent_id(string $label = null)
     * @method Show\Field|Collection uri(string $label = null)
     * @method Show\Field|Collection menu_id(string $label = null)
     * @method Show\Field|Collection permission_id(string $label = null)
     * @method Show\Field|Collection http_method(string $label = null)
     * @method Show\Field|Collection http_path(string $label = null)
     * @method Show\Field|Collection slug(string $label = null)
     * @method Show\Field|Collection role_id(string $label = null)
     * @method Show\Field|Collection user_id(string $label = null)
     * @method Show\Field|Collection value(string $label = null)
     * @method Show\Field|Collection avatar(string $label = null)
     * @method Show\Field|Collection language(string $label = null)
     * @method Show\Field|Collection password(string $label = null)
     * @method Show\Field|Collection remember_token(string $label = null)
     * @method Show\Field|Collection username(string $label = null)
     * @method Show\Field|Collection allowed_channels(string $label = null)
     * @method Show\Field|Collection allowed_models(string $label = null)
     * @method Show\Field|Collection deleted_at(string $label = null)
     * @method Show\Field|Collection expires_at(string $label = null)
     * @method Show\Field|Collection key(string $label = null)
     * @method Show\Field|Collection last_used_at(string $label = null)
     * @method Show\Field|Collection model_mappings(string $label = null)
     * @method Show\Field|Collection not_allowed_channels(string $label = null)
     * @method Show\Field|Collection permissions(string $label = null)
     * @method Show\Field|Collection rate_limit(string $label = null)
     * @method Show\Field|Collection status(string $label = null)
     * @method Show\Field|Collection actual_model(string $label = null)
     * @method Show\Field|Collection api_key_id(string $label = null)
     * @method Show\Field|Collection api_key_name(string $label = null)
     * @method Show\Field|Collection billing_source(string $label = null)
     * @method Show\Field|Collection cache_read_tokens(string $label = null)
     * @method Show\Field|Collection cache_write_tokens(string $label = null)
     * @method Show\Field|Collection cached_key_prefix(string $label = null)
     * @method Show\Field|Collection channel_affinity(string $label = null)
     * @method Show\Field|Collection channel_id(string $label = null)
     * @method Show\Field|Collection channel_name(string $label = null)
     * @method Show\Field|Collection client_ip(string $label = null)
     * @method Show\Field|Collection completion_tokens(string $label = null)
     * @method Show\Field|Collection cost(string $label = null)
     * @method Show\Field|Collection error_message(string $label = null)
     * @method Show\Field|Collection error_type(string $label = null)
     * @method Show\Field|Collection finish_reason(string $label = null)
     * @method Show\Field|Collection first_token_ms(string $label = null)
     * @method Show\Field|Collection group_name(string $label = null)
     * @method Show\Field|Collection is_stream(string $label = null)
     * @method Show\Field|Collection latency_ms(string $label = null)
     * @method Show\Field|Collection metadata(string $label = null)
     * @method Show\Field|Collection prompt_tokens(string $label = null)
     * @method Show\Field|Collection quota(string $label = null)
     * @method Show\Field|Collection request_id(string $label = null)
     * @method Show\Field|Collection request_type(string $label = null)
     * @method Show\Field|Collection run_unid(string $label = null)
     * @method Show\Field|Collection source_protocol(string $label = null)
     * @method Show\Field|Collection status_code(string $label = null)
     * @method Show\Field|Collection target_protocol(string $label = null)
     * @method Show\Field|Collection total_tokens(string $label = null)
     * @method Show\Field|Collection user_agent(string $label = null)
     * @method Show\Field|Collection expiration(string $label = null)
     * @method Show\Field|Collection owner(string $label = null)
     * @method Show\Field|Collection hit_count(string $label = null)
     * @method Show\Field|Collection key_hash(string $label = null)
     * @method Show\Field|Collection key_hint(string $label = null)
     * @method Show\Field|Collection rule_id(string $label = null)
     * @method Show\Field|Collection include_group_in_key(string $label = null)
     * @method Show\Field|Collection key_combine_strategy(string $label = null)
     * @method Show\Field|Collection key_sources(string $label = null)
     * @method Show\Field|Collection last_hit_at(string $label = null)
     * @method Show\Field|Collection model_patterns(string $label = null)
     * @method Show\Field|Collection param_override_template(string $label = null)
     * @method Show\Field|Collection path_patterns(string $label = null)
     * @method Show\Field|Collection priority(string $label = null)
     * @method Show\Field|Collection skip_retry_on_failure(string $label = null)
     * @method Show\Field|Collection ttl_seconds(string $label = null)
     * @method Show\Field|Collection user_agent_patterns(string $label = null)
     * @method Show\Field|Collection audit_log_id(string $label = null)
     * @method Show\Field|Collection ended_at(string $label = null)
     * @method Show\Field|Collection fallback_reason(string $label = null)
     * @method Show\Field|Collection hop_index(string $label = null)
     * @method Show\Field|Collection is_final(string $label = null)
     * @method Show\Field|Collection is_success(string $label = null)
     * @method Show\Field|Collection skip_reason(string $label = null)
     * @method Show\Field|Collection started_at(string $label = null)
     * @method Show\Field|Collection upstream_body_snippet(string $label = null)
     * @method Show\Field|Collection upstream_body_snippet_response(string $label = null)
     * @method Show\Field|Collection upstream_headers(string $label = null)
     * @method Show\Field|Collection upstream_headers_response(string $label = null)
     * @method Show\Field|Collection upstream_latency_ms(string $label = null)
     * @method Show\Field|Collection upstream_method(string $label = null)
     * @method Show\Field|Collection upstream_status(string $label = null)
     * @method Show\Field|Collection upstream_url(string $label = null)
     * @method Show\Field|Collection group_id(string $label = null)
     * @method Show\Field|Collection config(string $label = null)
     * @method Show\Field|Collection context_length(string $label = null)
     * @method Show\Field|Collection display_name(string $label = null)
     * @method Show\Field|Collection is_default(string $label = null)
     * @method Show\Field|Collection mapped_model(string $label = null)
     * @method Show\Field|Collection model_name(string $label = null)
     * @method Show\Field|Collection multiplier(string $label = null)
     * @method Show\Field|Collection rpm_limit(string $label = null)
     * @method Show\Field|Collection base_url(string $label = null)
     * @method Show\Field|Collection full_url(string $label = null)
     * @method Show\Field|Collection method(string $label = null)
     * @method Show\Field|Collection path(string $label = null)
     * @method Show\Field|Collection provider(string $label = null)
     * @method Show\Field|Collection request_body(string $label = null)
     * @method Show\Field|Collection request_headers(string $label = null)
     * @method Show\Field|Collection request_log_id(string $label = null)
     * @method Show\Field|Collection request_size(string $label = null)
     * @method Show\Field|Collection response_body(string $label = null)
     * @method Show\Field|Collection response_body_chunks(string $label = null)
     * @method Show\Field|Collection response_headers(string $label = null)
     * @method Show\Field|Collection response_size(string $label = null)
     * @method Show\Field|Collection response_status(string $label = null)
     * @method Show\Field|Collection sent_at(string $label = null)
     * @method Show\Field|Collection ttfb_ms(string $label = null)
     * @method Show\Field|Collection usage(string $label = null)
     * @method Show\Field|Collection tag_id(string $label = null)
     * @method Show\Field|Collection color(string $label = null)
     * @method Show\Field|Collection user_agent_id(string $label = null)
     * @method Show\Field|Collection api_key(string $label = null)
     * @method Show\Field|Collection api_key_hash(string $label = null)
     * @method Show\Field|Collection avg_latency_ms(string $label = null)
     * @method Show\Field|Collection coding_account_id(string $label = null)
     * @method Show\Field|Collection failure_count(string $label = null)
     * @method Show\Field|Collection forward_headers(string $label = null)
     * @method Show\Field|Collection has_user_agent_restriction(string $label = null)
     * @method Show\Field|Collection inherit_mode(string $label = null)
     * @method Show\Field|Collection last_check_at(string $label = null)
     * @method Show\Field|Collection last_failure_at(string $label = null)
     * @method Show\Field|Collection last_success_at(string $label = null)
     * @method Show\Field|Collection status2(string $label = null)
     * @method Show\Field|Collection status2_remark(string $label = null)
     * @method Show\Field|Collection success_count(string $label = null)
     * @method Show\Field|Collection success_rate(string $label = null)
     * @method Show\Field|Collection total_cost(string $label = null)
     * @method Show\Field|Collection total_requests(string $label = null)
     * @method Show\Field|Collection weight(string $label = null)
     * @method Show\Field|Collection account_id(string $label = null)
     * @method Show\Field|Collection last_sync_at(string $label = null)
     * @method Show\Field|Collection last_usage_at(string $label = null)
     * @method Show\Field|Collection limit_5h(string $label = null)
     * @method Show\Field|Collection limit_monthly(string $label = null)
     * @method Show\Field|Collection limit_weekly(string $label = null)
     * @method Show\Field|Collection period_5h(string $label = null)
     * @method Show\Field|Collection period_monthly(string $label = null)
     * @method Show\Field|Collection period_offset(string $label = null)
     * @method Show\Field|Collection period_weekly(string $label = null)
     * @method Show\Field|Collection reset_day(string $label = null)
     * @method Show\Field|Collection threshold_critical(string $label = null)
     * @method Show\Field|Collection threshold_disable(string $label = null)
     * @method Show\Field|Collection threshold_warning(string $label = null)
     * @method Show\Field|Collection used_5h(string $label = null)
     * @method Show\Field|Collection used_monthly(string $label = null)
     * @method Show\Field|Collection used_weekly(string $label = null)
     * @method Show\Field|Collection from_status(string $label = null)
     * @method Show\Field|Collection quota_5h_limit(string $label = null)
     * @method Show\Field|Collection quota_5h_rate(string $label = null)
     * @method Show\Field|Collection quota_5h_used(string $label = null)
     * @method Show\Field|Collection quota_monthly_limit(string $label = null)
     * @method Show\Field|Collection quota_monthly_rate(string $label = null)
     * @method Show\Field|Collection quota_monthly_used(string $label = null)
     * @method Show\Field|Collection quota_weekly_limit(string $label = null)
     * @method Show\Field|Collection quota_weekly_rate(string $label = null)
     * @method Show\Field|Collection quota_weekly_used(string $label = null)
     * @method Show\Field|Collection reason(string $label = null)
     * @method Show\Field|Collection to_status(string $label = null)
     * @method Show\Field|Collection triggered_by(string $label = null)
     * @method Show\Field|Collection model_multiplier(string $label = null)
     * @method Show\Field|Collection quota_after_5h(string $label = null)
     * @method Show\Field|Collection quota_after_monthly(string $label = null)
     * @method Show\Field|Collection quota_after_weekly(string $label = null)
     * @method Show\Field|Collection quota_before_5h(string $label = null)
     * @method Show\Field|Collection quota_before_monthly(string $label = null)
     * @method Show\Field|Collection quota_before_weekly(string $label = null)
     * @method Show\Field|Collection requests(string $label = null)
     * @method Show\Field|Collection credentials(string $label = null)
     * @method Show\Field|Collection disabled_at(string $label = null)
     * @method Show\Field|Collection driver_class(string $label = null)
     * @method Show\Field|Collection platform(string $label = null)
     * @method Show\Field|Collection status_override(string $label = null)
     * @method Show\Field|Collection sync_error(string $label = null)
     * @method Show\Field|Collection sync_error_count(string $label = null)
     * @method Show\Field|Collection metric(string $label = null)
     * @method Show\Field|Collection period_ends_at(string $label = null)
     * @method Show\Field|Collection period_key(string $label = null)
     * @method Show\Field|Collection period_starts_at(string $label = null)
     * @method Show\Field|Collection period_type(string $label = null)
     * @method Show\Field|Collection used(string $label = null)
     * @method Show\Field|Collection tokens_input(string $label = null)
     * @method Show\Field|Collection tokens_output(string $label = null)
     * @method Show\Field|Collection tokens_total(string $label = null)
     * @method Show\Field|Collection window_id(string $label = null)
     * @method Show\Field|Collection ends_at(string $label = null)
     * @method Show\Field|Collection window_seconds(string $label = null)
     * @method Show\Field|Collection window_type(string $label = null)
     * @method Show\Field|Collection quota_snapshot(string $label = null)
     * @method Show\Field|Collection credits(string $label = null)
     * @method Show\Field|Collection prompts(string $label = null)
     * @method Show\Field|Collection connection(string $label = null)
     * @method Show\Field|Collection exception(string $label = null)
     * @method Show\Field|Collection failed_at(string $label = null)
     * @method Show\Field|Collection payload(string $label = null)
     * @method Show\Field|Collection queue(string $label = null)
     * @method Show\Field|Collection uuid(string $label = null)
     * @method Show\Field|Collection cancelled_at(string $label = null)
     * @method Show\Field|Collection failed_job_ids(string $label = null)
     * @method Show\Field|Collection failed_jobs(string $label = null)
     * @method Show\Field|Collection finished_at(string $label = null)
     * @method Show\Field|Collection pending_jobs(string $label = null)
     * @method Show\Field|Collection total_jobs(string $label = null)
     * @method Show\Field|Collection attempts(string $label = null)
     * @method Show\Field|Collection available_at(string $label = null)
     * @method Show\Field|Collection reserved_at(string $label = null)
     * @method Show\Field|Collection archive_enabled(string $label = null)
     * @method Show\Field|Collection archive_storage(string $label = null)
     * @method Show\Field|Collection mask_sensitive(string $label = null)
     * @method Show\Field|Collection max_body_length(string $label = null)
     * @method Show\Field|Collection retention_days(string $label = null)
     * @method Show\Field|Collection sample_rate(string $label = null)
     * @method Show\Field|Collection scope(string $label = null)
     * @method Show\Field|Collection scope_id(string $label = null)
     * @method Show\Field|Collection sensitive_patterns(string $label = null)
     * @method Show\Field|Collection store_generated_text(string $label = null)
     * @method Show\Field|Collection store_headers(string $label = null)
     * @method Show\Field|Collection store_messages(string $label = null)
     * @method Show\Field|Collection store_request_body(string $label = null)
     * @method Show\Field|Collection store_response_body(string $label = null)
     * @method Show\Field|Collection capabilities(string $label = null)
     * @method Show\Field|Collection common_name(string $label = null)
     * @method Show\Field|Collection hugging_face_id(string $label = null)
     * @method Show\Field|Collection pricing_completion(string $label = null)
     * @method Show\Field|Collection pricing_input_cache_read(string $label = null)
     * @method Show\Field|Collection pricing_prompt(string $label = null)
     * @method Show\Field|Collection assistant_response(string $label = null)
     * @method Show\Field|Collection prompt_preset_id(string $label = null)
     * @method Show\Field|Collection response_time_ms(string $label = null)
     * @method Show\Field|Collection system_prompt(string $label = null)
     * @method Show\Field|Collection test_type(string $label = null)
     * @method Show\Field|Collection user_message(string $label = null)
     * @method Show\Field|Collection after_data(string $label = null)
     * @method Show\Field|Collection before_data(string $label = null)
     * @method Show\Field|Collection ip(string $label = null)
     * @method Show\Field|Collection source(string $label = null)
     * @method Show\Field|Collection target(string $label = null)
     * @method Show\Field|Collection target_id(string $label = null)
     * @method Show\Field|Collection target_name(string $label = null)
     * @method Show\Field|Collection email(string $label = null)
     * @method Show\Field|Collection token(string $label = null)
     * @method Show\Field|Collection category(string $label = null)
     * @method Show\Field|Collection content(string $label = null)
     * @method Show\Field|Collection headers(string $label = null)
     * @method Show\Field|Collection sort_order(string $label = null)
     * @method Show\Field|Collection body_binary(string $label = null)
     * @method Show\Field|Collection body_text(string $label = null)
     * @method Show\Field|Collection content_length(string $label = null)
     * @method Show\Field|Collection content_type(string $label = null)
     * @method Show\Field|Collection has_sensitive(string $label = null)
     * @method Show\Field|Collection messages(string $label = null)
     * @method Show\Field|Collection model_params(string $label = null)
     * @method Show\Field|Collection prompt(string $label = null)
     * @method Show\Field|Collection query_string(string $label = null)
     * @method Show\Field|Collection sensitive_fields(string $label = null)
     * @method Show\Field|Collection upstream_model(string $label = null)
     * @method Show\Field|Collection error_code(string $label = null)
     * @method Show\Field|Collection error_details(string $label = null)
     * @method Show\Field|Collection generated_chunks(string $label = null)
     * @method Show\Field|Collection generated_text(string $label = null)
     * @method Show\Field|Collection response_type(string $label = null)
     * @method Show\Field|Collection status_message(string $label = null)
     * @method Show\Field|Collection upstream_provider(string $label = null)
     * @method Show\Field|Collection ip_address(string $label = null)
     * @method Show\Field|Collection last_activity(string $label = null)
     * @method Show\Field|Collection group(string $label = null)
     * @method Show\Field|Collection is_public(string $label = null)
     * @method Show\Field|Collection label(string $label = null)
     * @method Show\Field|Collection patterns(string $label = null)
     * @method Show\Field|Collection currency(string $label = null)
     * @method Show\Field|Collection email_verified_at(string $label = null)
     * @method Show\Field|Collection locale(string $label = null)
     */
    class Show {}

    class Form {}

}

namespace Dcat\Admin\Grid {
    /**
     * @method $this copyableValue(...$params)
     * @method $this multiFields(...$params)
     */
    class Column {}

    class Filter {}
}

namespace Dcat\Admin\Show {
    class Field {}
}
