<?php
/**
 * Plugin Name: WC AI Chatbot
 * Plugin URI:  https://github.com/fahdi/wc-ai-chatbot
 * Description: AI-powered shopping assistant for WooCommerce — answers questions and manages the cart using Claude or Kimi K2.
 * Version:     1.0.0
 * Author:      Fahdi Murtaza
 * Author URI:  https://github.com/fahdi
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-chatbot
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_AI_CHATBOT_VERSION', '1.0.0' );
define( 'WC_AI_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_AI_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

require_once WC_AI_CHATBOT_PATH . 'includes/class-tools.php';
require_once WC_AI_CHATBOT_PATH . 'includes/class-api-handler.php';
require_once WC_AI_CHATBOT_PATH . 'includes/admin-settings.php';

final class WC_AI_Chatbot {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init',               [ $this, 'load_textdomain' ] );
		add_action( 'rest_api_init',      [ $this, 'register_routes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer',          [ $this, 'render_widget' ] );
		add_action( 'admin_menu',         [ $this, 'add_admin_menu' ] );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-ai-chatbot',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	public function register_routes(): void {
		register_rest_route( 'wc-chatbot/v1', '/message', [
			'methods'             => 'POST',
			'callback'            => [ WC_AI_Chatbot_API_Handler::instance(), 'handle_message' ],
			'permission_callback' => [ $this, 'check_nonce' ],
		] );
		register_rest_route( 'wc-chatbot/v1', '/stream', [
			'methods'             => 'POST',
			'callback'            => [ WC_AI_Chatbot_API_Handler::instance(), 'handle_stream' ],
			'permission_callback' => [ $this, 'check_nonce' ],
		] );
	}

	public function check_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	private function has_api_key(): bool {
		$provider = get_option( 'wc_ai_chatbot_provider', 'anthropic' );
		$key      = ( 'moonshot' === $provider )
			? get_option( 'wc_ai_chatbot_moonshot_api_key', '' )
			: get_option( 'wc_ai_chatbot_anthropic_api_key', '' );
		return ! empty( $key );
	}

	public function enqueue_assets(): void {
		if ( ! $this->has_api_key() ) {
			return;
		}

		wp_enqueue_style(
			'wc-ai-chatbot',
			WC_AI_CHATBOT_URL . 'assets/css/chatbot.css',
			[],
			WC_AI_CHATBOT_VERSION
		);

		wp_enqueue_script(
			'wc-ai-chatbot',
			WC_AI_CHATBOT_URL . 'assets/js/chatbot.js',
			[],
			WC_AI_CHATBOT_VERSION,
			true
		);

		wp_localize_script( 'wc-ai-chatbot', 'wcAIChatbot', [
			'apiUrl'      => rest_url( 'wc-chatbot/v1/message' ),
			'streamUrl'   => rest_url( 'wc-chatbot/v1/stream' ),
			'provider'    => get_option( 'wc_ai_chatbot_provider', 'anthropic' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'botName'     => get_option( 'wc_ai_chatbot_bot_name', __( 'Store Assistant', 'wc-ai-chatbot' ) ),
			'greeting'    => get_option( 'wc_ai_chatbot_greeting', __( 'Hi! How can I help you today?', 'wc-ai-chatbot' ) ),
			'accentColor' => get_option( 'wc_ai_chatbot_accent_color', '#2563eb' ),
		] );
	}

	public function render_widget(): void {
		if ( ! $this->has_api_key() ) {
			return;
		}
		echo '<div id="wc-ai-chatbot-root"></div>';
	}

	public function add_admin_menu(): void {
		add_options_page(
			esc_html__( 'AI Chatbot Settings', 'wc-ai-chatbot' ),
			esc_html__( 'AI Chatbot', 'wc-ai-chatbot' ),
			'manage_options',
			'wc-ai-chatbot',
			'wc_ai_chatbot_settings_page'
		);
	}
}

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>' .
				esc_html__( 'WC AI Chatbot', 'wc-ai-chatbot' ) .
				'</strong> ' .
				esc_html__( 'requires WooCommerce to be active.', 'wc-ai-chatbot' ) .
				'</p></div>';
		} );
		return;
	}
	WC_AI_Chatbot::instance();
} );
