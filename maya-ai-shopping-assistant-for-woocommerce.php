<?php
/**
 * Plugin Name: Maya AI Shopping Assistant for WooCommerce
 * Plugin URI:  https://github.com/fahdi/maya-ai-shopping-assistant-for-woocommerce
 * Description: AI-powered shopping assistant for WooCommerce — answers questions and manages the cart using Claude or Kimi K2.
 * Version:     1.0.3
 * Author:      Fahdi Murtaza
 * Author URI:  https://github.com/fahdi
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: maya-ai-shopping-assistant-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MAYAAI_VERSION', '1.0.3' );
define( 'MAYAAI_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAYAAI_URL', plugin_dir_url( __FILE__ ) );

require_once MAYAAI_PATH . 'includes/class-tools.php';
require_once MAYAAI_PATH . 'includes/class-api-handler.php';
require_once MAYAAI_PATH . 'includes/admin-settings.php';

final class Mayaai_Chatbot {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init',         [ $this, 'register_routes' ] );
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_footer',             [ $this, 'render_widget' ] );
		add_action( 'admin_menu',            [ $this, 'add_admin_menu' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'mayaai/v1', '/message', [
			'methods'             => 'POST',
			'callback'            => [ Mayaai_API_Handler::instance(), 'handle_message' ],
			'permission_callback' => [ $this, 'check_nonce' ],
		] );
		register_rest_route( 'mayaai/v1', '/stream', [
			'methods'             => 'POST',
			'callback'            => [ Mayaai_API_Handler::instance(), 'handle_stream' ],
			'permission_callback' => [ $this, 'check_nonce' ],
		] );
	}

	public function check_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	private function has_api_key(): bool {
		$provider = get_option( 'mayaai_provider', 'anthropic' );
		$key      = ( 'moonshot' === $provider )
			? get_option( 'mayaai_moonshot_api_key', '' )
			: get_option( 'mayaai_anthropic_api_key', '' );
		return ! empty( $key );
	}

	public function enqueue_assets(): void {
		if ( ! $this->has_api_key() ) {
			return;
		}

		wp_enqueue_style(
			'mayaai-chatbot',
			MAYAAI_URL . 'assets/css/chatbot.css',
			[],
			MAYAAI_VERSION
		);

		wp_enqueue_script(
			'mayaai-chatbot',
			MAYAAI_URL . 'assets/js/chatbot.js',
			[],
			MAYAAI_VERSION,
			true
		);

		wp_localize_script( 'mayaai-chatbot', 'mayaaiChatbot', [
			'apiUrl'      => rest_url( 'mayaai/v1/message' ),
			'streamUrl'   => rest_url( 'mayaai/v1/stream' ),
			'provider'    => get_option( 'mayaai_provider', 'anthropic' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'botName'     => get_option( 'mayaai_bot_name', __( 'Store Assistant', 'maya-ai-shopping-assistant-for-woocommerce' ) ),
			'greeting'    => get_option( 'mayaai_greeting', __( 'Hi! How can I help you today?', 'maya-ai-shopping-assistant-for-woocommerce' ) ),
			'accentColor' => get_option( 'mayaai_accent_color', '#2563eb' ),
			'i18n'        => [
				'openChat'           => __( 'Open chat assistant', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'closeChat'          => __( 'Close chat', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'sendMessage'        => __( 'Send message', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'yourMessage'        => __( 'Your message', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'chatDialogLabel'    => __( 'Chat with store assistant', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'placeholder'        => __( 'Ask me anything…', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'connectionError'    => __( 'Connection error. Please try again.', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'genericError'       => __( 'Something went wrong. Please try again.', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'noResponseStream'   => __( 'No response received. Please try again.', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'noResponseRegular'  => __( 'No response. Please try again.', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'toolWorking'        => __( 'Working…', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'toolSearchProducts' => __( 'Searching products…', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'toolGetDetails'     => __( 'Getting product details…', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'toolAddToCart'      => __( 'Adding to cart…', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'toolViewCart'       => __( 'Checking your cart…', 'maya-ai-shopping-assistant-for-woocommerce' ),
				'toolRemoveFromCart' => __( 'Removing from cart…', 'maya-ai-shopping-assistant-for-woocommerce' ),
			],
		] );
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'settings_page_maya-ai-shopping-assistant-for-woocommerce' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'mayaai-admin',
			MAYAAI_URL . 'assets/js/admin-settings.js',
			[],
			MAYAAI_VERSION,
			true
		);
	}

	public function render_widget(): void {
		if ( ! $this->has_api_key() ) {
			return;
		}
		echo '<div id="mayaai-chatbot-root"></div>';
	}

	public function add_admin_menu(): void {
		add_options_page(
			esc_html__( 'Maya AI Shopping Assistant', 'maya-ai-shopping-assistant-for-woocommerce' ),
			esc_html__( 'Maya AI Assistant', 'maya-ai-shopping-assistant-for-woocommerce' ),
			'manage_options',
			'maya-ai-shopping-assistant-for-woocommerce',
			'mayaai_settings_page'
		);
	}
}

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>' .
				esc_html__( 'Maya AI Shopping Assistant', 'maya-ai-shopping-assistant-for-woocommerce' ) .
				'</strong> ' .
				esc_html__( 'requires WooCommerce to be active.', 'maya-ai-shopping-assistant-for-woocommerce' ) .
				'</p></div>';
		} );
		return;
	}
	Mayaai_Chatbot::instance();
} );
