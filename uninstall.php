<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all options stored by Maya AI Shopping Assistant for WooCommerce.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$mayaai_options = [
	'mayaai_provider',
	'mayaai_anthropic_api_key',
	'mayaai_anthropic_model',
	'mayaai_moonshot_api_key',
	'mayaai_moonshot_model',
	'mayaai_bot_name',
	'mayaai_greeting',
	'mayaai_system_prompt',
	'mayaai_accent_color',
];

foreach ( $mayaai_options as $mayaai_option ) {
	delete_option( $mayaai_option );
}
