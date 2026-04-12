<?php
defined( 'ABSPATH' ) || exit;

/**
 * Executes WooCommerce tool calls on behalf of the AI agent.
 */
final class WC_AI_Chatbot_Tools {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Route a tool call by name to the appropriate method.
	 *
	 * @param string $name  Tool name.
	 * @param array  $input Tool input from Claude.
	 * @return array Result to send back as tool_result content.
	 */
	public function execute( string $name, array $input ): array {
		switch ( $name ) {
			case 'search_products':
				return $this->search_products( $input );
			case 'get_product_details':
				return $this->get_product_details( $input );
			case 'add_to_cart':
				return $this->add_to_cart( $input );
			case 'view_cart':
				return $this->view_cart();
			case 'remove_from_cart':
				return $this->remove_from_cart( $input );
			default:
				return [ 'error' => "Unknown tool: {$name}" ];
		}
	}

	// -------------------------------------------------------------------------
	// Tool implementations
	// -------------------------------------------------------------------------

	private function search_products( array $input ): array {
		$args = [
			'status'  => 'publish',
			'limit'   => min( (int) ( $input['limit'] ?? 5 ), 10 ),
			'orderby' => 'relevance',
		];

		if ( ! empty( $input['query'] ) ) {
			$args['s'] = sanitize_text_field( $input['query'] );
		}

		if ( ! empty( $input['category'] ) ) {
			$args['category'] = [ sanitize_text_field( $input['category'] ) ];
		}

		if ( isset( $input['min_price'] ) ) {
			$args['min_price'] = (float) $input['min_price'];
		}

		if ( isset( $input['max_price'] ) ) {
			$args['max_price'] = (float) $input['max_price'];
		}

		$products = wc_get_products( $args );

		if ( empty( $products ) ) {
			return [
				'found'    => 0,
				'products' => [],
				'message'  => 'No products found matching your search.',
			];
		}

		return [
			'found'    => count( $products ),
			'products' => array_map( [ $this, 'format_product_summary' ], $products ),
		];
	}

	private function get_product_details( array $input ): array {
		$product_id = absint( $input['product_id'] ?? 0 );
		$product    = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_visible() ) {
			return [ 'error' => 'Product not found.' ];
		}

		$data = [
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'price'             => wc_price( $product->get_price() ),
			'regular_price'     => wc_price( $product->get_regular_price() ),
			'sale_price'        => $product->is_on_sale() ? wc_price( $product->get_sale_price() ) : null,
			'description'       => wp_strip_all_tags( $product->get_description() ),
			'short_description' => wp_strip_all_tags( $product->get_short_description() ),
			'sku'               => $product->get_sku(),
			'in_stock'          => $product->is_in_stock(),
			'stock_qty'         => $product->get_stock_quantity(),
			'type'              => $product->get_type(),
			'url'               => get_permalink( $product->get_id() ),
			'categories'        => wp_list_pluck(
				get_the_terms( $product_id, 'product_cat' ) ?: [],
				'name'
			),
		];

		if ( $product->is_type( 'variable' ) ) {
			$variations = [];
			foreach ( $product->get_available_variations() as $var ) {
				$v = wc_get_product( $var['variation_id'] );
				if ( $v ) {
					$variations[] = [
						'variation_id' => $v->get_id(),
						'attributes'   => $var['attributes'],
						'price'        => wc_price( $v->get_price() ),
						'in_stock'     => $v->is_in_stock(),
					];
				}
			}
			$data['variations'] = $variations;
		}

		return $data;
	}

	private function add_to_cart( array $input ): array {
		$product_id   = absint( $input['product_id'] ?? 0 );
		$quantity     = max( 1, absint( $input['quantity'] ?? 1 ) );
		$variation_id = absint( $input['variation_id'] ?? 0 );

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_visible() ) {
			return [ 'success' => false, 'error' => 'Product not found.' ];
		}

		if ( ! $product->is_in_stock() ) {
			return [
				'success' => false,
				'error'   => $product->get_name() . ' is currently out of stock.',
			];
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );

		if ( $cart_item_key ) {
			return [
				'success'       => true,
				'message'       => "Added {$quantity}x {$product->get_name()} to your cart.",
				'cart_item_key' => $cart_item_key,
				'cart_total'    => wp_strip_all_tags( WC()->cart->get_cart_total() ),
				'cart_url'      => wc_get_cart_url(),
				'checkout_url'  => wc_get_checkout_url(),
			];
		}

		return [ 'success' => false, 'error' => 'Could not add to cart. The product may require a variation to be selected.' ];
	}

	private function view_cart(): array {
		$cart = WC()->cart;

		if ( $cart->is_empty() ) {
			return [ 'empty' => true, 'message' => 'Your cart is empty.' ];
		}

		$items = [];
		foreach ( $cart->get_cart() as $key => $item ) {
			/** @var WC_Product $product */
			$product = $item['data'];
			$items[] = [
				'cart_item_key' => $key,
				'product_id'    => $item['product_id'],
				'name'          => $product->get_name(),
				'quantity'      => $item['quantity'],
				'price'         => wc_price( $product->get_price() ),
				'line_total'    => wc_price( $item['line_total'] ),
			];
		}

		return [
			'empty'        => false,
			'items'        => $items,
			'item_count'   => $cart->get_cart_contents_count(),
			'subtotal'     => wp_strip_all_tags( $cart->get_cart_subtotal() ),
			'total'        => wp_strip_all_tags( $cart->get_cart_total() ),
			'cart_url'     => wc_get_cart_url(),
			'checkout_url' => wc_get_checkout_url(),
		];
	}

	private function remove_from_cart( array $input ): array {
		$key = sanitize_text_field( $input['cart_item_key'] ?? '' );

		if ( empty( $key ) ) {
			return [ 'success' => false, 'error' => 'Cart item key is required.' ];
		}

		$cart_contents = WC()->cart->get_cart();

		if ( ! isset( $cart_contents[ $key ] ) ) {
			return [ 'success' => false, 'error' => 'Item not found in cart.' ];
		}

		$product_name = $cart_contents[ $key ]['data']->get_name();

		if ( WC()->cart->remove_cart_item( $key ) ) {
			return [
				'success'   => true,
				'message'   => "Removed {$product_name} from your cart.",
				'new_total' => wp_strip_all_tags( WC()->cart->get_cart_total() ),
			];
		}

		return [ 'success' => false, 'error' => 'Could not remove the item.' ];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function format_product_summary( WC_Product $product ): array {
		return [
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'price'             => wc_price( $product->get_price() ),
			'on_sale'           => $product->is_on_sale(),
			'in_stock'          => $product->is_in_stock(),
			'short_description' => wp_strip_all_tags( $product->get_short_description() ),
			'url'               => get_permalink( $product->get_id() ),
		];
	}
}
