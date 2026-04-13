<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all options stored by AI Chatbot for WooCommerce.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wc_ai_chatbot_options = [
	'wc_ai_chatbot_provider',
	'wc_ai_chatbot_anthropic_api_key',
	'wc_ai_chatbot_anthropic_model',
	'wc_ai_chatbot_moonshot_api_key',
	'wc_ai_chatbot_moonshot_model',
	'wc_ai_chatbot_bot_name',
	'wc_ai_chatbot_greeting',
	'wc_ai_chatbot_system_prompt',
	'wc_ai_chatbot_accent_color',
];

foreach ( $wc_ai_chatbot_options as $wc_ai_chatbot_option ) {
	delete_option( $wc_ai_chatbot_option );
}
