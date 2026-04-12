<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all options stored by WC AI Chatbot.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = [
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

foreach ( $options as $option ) {
	delete_option( $option );
}
