<?php
defined( 'ABSPATH' ) || exit;

function wc_ai_chatbot_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['wc_ai_chatbot_save'] ) && check_admin_referer( 'wc_ai_chatbot_settings' ) ) {
		update_option( 'wc_ai_chatbot_provider',          sanitize_text_field( wp_unslash( $_POST['provider']          ?? 'anthropic' ) ) );
		update_option( 'wc_ai_chatbot_anthropic_api_key', sanitize_text_field( wp_unslash( $_POST['anthropic_api_key'] ?? '' ) ) );
		update_option( 'wc_ai_chatbot_anthropic_model',   sanitize_text_field( wp_unslash( $_POST['anthropic_model']   ?? 'claude-haiku-4-5-20251001' ) ) );
		update_option( 'wc_ai_chatbot_moonshot_api_key',  sanitize_text_field( wp_unslash( $_POST['moonshot_api_key']  ?? '' ) ) );
		update_option( 'wc_ai_chatbot_moonshot_model',    sanitize_text_field( wp_unslash( $_POST['moonshot_model']    ?? 'kimi-k2-thinking-turbo' ) ) );
		update_option( 'wc_ai_chatbot_bot_name',          sanitize_text_field( wp_unslash( $_POST['bot_name']          ?? 'Store Assistant' ) ) );
		update_option( 'wc_ai_chatbot_greeting',          sanitize_textarea_field( wp_unslash( $_POST['greeting']      ?? 'Hi! How can I help you today?' ) ) );
		update_option( 'wc_ai_chatbot_system_prompt',     sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ?? '' ) ) );
		update_option( 'wc_ai_chatbot_accent_color',      sanitize_hex_color( wp_unslash( $_POST['accent_color']       ?? '#2563eb' ) ) );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wc-ai-chatbot' ) . '</p></div>';
	}

	$provider        = get_option( 'wc_ai_chatbot_provider',          'anthropic' );
	$anthropic_key   = get_option( 'wc_ai_chatbot_anthropic_api_key', '' );
	$anthropic_model = get_option( 'wc_ai_chatbot_anthropic_model',   'claude-haiku-4-5-20251001' );
	$moonshot_key    = get_option( 'wc_ai_chatbot_moonshot_api_key',  '' );
	$moonshot_model  = get_option( 'wc_ai_chatbot_moonshot_model',    'kimi-k2-thinking-turbo' );
	$bot_name        = get_option( 'wc_ai_chatbot_bot_name',          'Store Assistant' );
	$greeting        = get_option( 'wc_ai_chatbot_greeting',          'Hi! How can I help you today?' );
	$system_prompt   = get_option( 'wc_ai_chatbot_system_prompt',     '' );
	$accent_color    = get_option( 'wc_ai_chatbot_accent_color',      '#2563eb' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Chatbot Settings', 'wc-ai-chatbot' ); ?></h1>

		<form method="post">
			<?php wp_nonce_field( 'wc_ai_chatbot_settings' ); ?>

			<h2 class="title"><?php esc_html_e( 'Provider', 'wc-ai-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><label for="provider"><?php esc_html_e( 'AI Provider', 'wc-ai-chatbot' ); ?></label></th>
					<td>
						<select id="provider" name="provider" onchange="wcChatbotToggleProvider(this.value)">
							<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (Claude)', 'wc-ai-chatbot' ); ?></option>
							<option value="moonshot"  <?php selected( $provider, 'moonshot' );  ?>><?php esc_html_e( 'Moonshot AI (Kimi)', 'wc-ai-chatbot' ); ?></option>
						</select>
					</td>
				</tr>

				<!-- Anthropic fields -->
				<tbody id="wc-chatbot-anthropic" style="<?php echo 'anthropic' !== $provider ? 'display:none' : ''; ?>">
					<tr>
						<th scope="row"><label for="anthropic_api_key"><?php esc_html_e( 'Anthropic API Key', 'wc-ai-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="anthropic_api_key" name="anthropic_api_key"
								value="<?php echo esc_attr( $anthropic_key ); ?>" class="regular-text" autocomplete="new-password">
							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to Anthropic console */
									esc_html__( 'Get your key from %s.', 'wc-ai-chatbot' ),
									'<a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="anthropic_model"><?php esc_html_e( 'Claude Model', 'wc-ai-chatbot' ); ?></label></th>
						<td>
							<select id="anthropic_model" name="anthropic_model">
								<option value="claude-haiku-4-5-20251001" <?php selected( $anthropic_model, 'claude-haiku-4-5-20251001' ); ?>>
									<?php esc_html_e( 'Claude Haiku — Fast & affordable (recommended)', 'wc-ai-chatbot' ); ?>
								</option>
								<option value="claude-sonnet-4-6" <?php selected( $anthropic_model, 'claude-sonnet-4-6' ); ?>>
									<?php esc_html_e( 'Claude Sonnet — Balanced performance', 'wc-ai-chatbot' ); ?>
								</option>
								<option value="claude-opus-4-6" <?php selected( $anthropic_model, 'claude-opus-4-6' ); ?>>
									<?php esc_html_e( 'Claude Opus — Most capable', 'wc-ai-chatbot' ); ?>
								</option>
							</select>
						</td>
					</tr>
				</tbody>

				<!-- Moonshot / Kimi fields -->
				<tbody id="wc-chatbot-moonshot" style="<?php echo 'moonshot' !== $provider ? 'display:none' : ''; ?>">
					<tr>
						<th scope="row"><label for="moonshot_api_key"><?php esc_html_e( 'Moonshot API Key', 'wc-ai-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="moonshot_api_key" name="moonshot_api_key"
								value="<?php echo esc_attr( $moonshot_key ); ?>" class="regular-text" autocomplete="new-password">
							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to Moonshot platform */
									esc_html__( 'Get your key from %s.', 'wc-ai-chatbot' ),
									'<a href="https://platform.moonshot.ai" target="_blank" rel="noopener">platform.moonshot.ai</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="moonshot_model"><?php esc_html_e( 'Kimi Model', 'wc-ai-chatbot' ); ?></label></th>
						<td>
							<select id="moonshot_model" name="moonshot_model">
								<optgroup label="<?php esc_attr_e( 'Kimi K2', 'wc-ai-chatbot' ); ?>">
									<option value="kimi-k2-thinking-turbo" <?php selected( $moonshot_model, 'kimi-k2-thinking-turbo' ); ?>><?php esc_html_e( 'kimi-k2-thinking-turbo — Reasoning, fast (recommended)', 'wc-ai-chatbot' ); ?></option>
									<option value="kimi-k2-thinking"       <?php selected( $moonshot_model, 'kimi-k2-thinking' );       ?>><?php esc_html_e( 'kimi-k2-thinking — Reasoning, full', 'wc-ai-chatbot' ); ?></option>
									<option value="kimi-k2.5"              <?php selected( $moonshot_model, 'kimi-k2.5' );              ?>><?php esc_html_e( 'kimi-k2.5 — Latest, vision + reasoning', 'wc-ai-chatbot' ); ?></option>
									<option value="kimi-k2-0905-preview"   <?php selected( $moonshot_model, 'kimi-k2-0905-preview' );   ?>>kimi-k2-0905-preview</option>
									<option value="kimi-k2-turbo-preview"  <?php selected( $moonshot_model, 'kimi-k2-turbo-preview' );  ?>>kimi-k2-turbo-preview</option>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'Moonshot V1', 'wc-ai-chatbot' ); ?>">
									<option value="moonshot-v1-auto"  <?php selected( $moonshot_model, 'moonshot-v1-auto' );  ?>><?php esc_html_e( 'moonshot-v1-auto — Auto context', 'wc-ai-chatbot' ); ?></option>
									<option value="moonshot-v1-8k"    <?php selected( $moonshot_model, 'moonshot-v1-8k' );    ?>>moonshot-v1-8k</option>
									<option value="moonshot-v1-32k"   <?php selected( $moonshot_model, 'moonshot-v1-32k' );   ?>>moonshot-v1-32k</option>
									<option value="moonshot-v1-128k"  <?php selected( $moonshot_model, 'moonshot-v1-128k' );  ?>>moonshot-v1-128k</option>
								</optgroup>
							</select>
						</td>
					</tr>
				</tbody>

			</table>

			<h2 class="title"><?php esc_html_e( 'Widget', 'wc-ai-chatbot' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bot_name"><?php esc_html_e( 'Bot Name', 'wc-ai-chatbot' ); ?></label></th>
					<td>
						<input type="text" id="bot_name" name="bot_name"
							value="<?php echo esc_attr( $bot_name ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="greeting"><?php esc_html_e( 'Greeting Message', 'wc-ai-chatbot' ); ?></label></th>
					<td>
						<textarea id="greeting" name="greeting" class="large-text" rows="2"><?php echo esc_textarea( $greeting ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="accent_color"><?php esc_html_e( 'Accent Color', 'wc-ai-chatbot' ); ?></label></th>
					<td>
						<input type="color" id="accent_color" name="accent_color"
							value="<?php echo esc_attr( $accent_color ); ?>">
					</td>
				</tr>
			</table>

			<h2 class="title">
				<?php esc_html_e( 'System Prompt', 'wc-ai-chatbot' ); ?>
				<span style="font-weight:400;font-size:13px;"><?php esc_html_e( '(optional)', 'wc-ai-chatbot' ); ?></span>
			</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="system_prompt"><?php esc_html_e( 'Custom Prompt', 'wc-ai-chatbot' ); ?></label></th>
					<td>
						<textarea id="system_prompt" name="system_prompt" class="large-text" rows="7"><?php echo esc_textarea( $system_prompt ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Leave blank to use the default prompt. Add store policies, shipping info, FAQs, or tone guidelines here.', 'wc-ai-chatbot' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( esc_html__( 'Save Settings', 'wc-ai-chatbot' ), 'primary', 'wc_ai_chatbot_save' ); ?>
		</form>
	</div>

	<script>
	function wcChatbotToggleProvider(val) {
		document.getElementById('wc-chatbot-anthropic').style.display = val === 'anthropic' ? '' : 'none';
		document.getElementById('wc-chatbot-moonshot').style.display  = val === 'moonshot'  ? '' : 'none';
	}
	</script>
	<?php
}
