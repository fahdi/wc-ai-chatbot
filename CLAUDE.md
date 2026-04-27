# Maya AI Shopping Assistant for WooCommerce — Developer Knowledge Base

Plugin folder: `maya-ai-shopping-assistant-for-woocommerce/`
Main file: `maya-ai-shopping-assistant-for-woocommerce.php`
GitHub: https://github.com/fahdi/maya-ai-shopping-assistant-for-woocommerce
Current version: 1.0.3
Slug (WP.org): `maya-ai-shopping-assistant-for-woocommerce` (pending approval)
Text domain: `maya-ai-shopping-assistant-for-woocommerce` (must equal slug)

---

## Naming Conventions

| Concept | Pattern | Examples |
|---|---|---|
| PHP constants | `MAYAAI_*` | `MAYAAI_VERSION`, `MAYAAI_PATH`, `MAYAAI_URL` |
| PHP classes | `Mayaai_*` | `Mayaai_Chatbot`, `Mayaai_API_Handler`, `Mayaai_Tools` |
| PHP functions | `mayaai_*` | `mayaai_settings_page` |
| Option keys | `mayaai_*` | `mayaai_provider`, `mayaai_anthropic_api_key` |
| Nonce action | `mayaai_settings` |
| Submit button name | `mayaai_save` |
| JS handle (script/style) | `mayaai-chatbot`, `mayaai-admin` |
| JS localized var | `window.mayaaiChatbot` |
| REST namespace | `mayaai/v1` |
| HTML root id | `mayaai-chatbot-root` |
| Admin tbody ids | `mayaai-anthropic`, `mayaai-moonshot` |

The `MAYAAI` / `mayaai_` prefix derives from **M**aya **AI** Shopping Assistant for **W**ooCommerce. It's 6 characters, distinct, and uses no common words — passes WP.org's "at least 4 chars, distinct and unique" prefix requirement.

---

## File Structure

```
maya-ai-shopping-assistant-for-woocommerce/
├── maya-ai-shopping-assistant-for-woocommerce.php   # Bootstrap: header, Mayaai_Chatbot class, REST routes
├── includes/
│   ├── class-api-handler.php                         # Mayaai_API_Handler — agents, API calls, SSE streaming, tools specs
│   ├── class-tools.php                               # Mayaai_Tools — WooCommerce operations executed by the AI
│   └── admin-settings.php                            # mayaai_settings_page() — provider, keys, widget config
├── assets/
│   ├── js/chatbot.js                                 # Frontend widget — vanilla JS, no dependencies
│   ├── js/admin-settings.js                          # Admin provider toggle (extracted from inline <script>)
│   └── css/chatbot.css                               # Widget styles with CSS custom properties
├── tests/
│   ├── bootstrap.php                                 # PHPUnit bootstrap: loads stubs + plugin classes
│   ├── stubs/wc-stubs.php                            # WP_Error, WC_Product, WC_Cart stubs for tests
│   └── unit/
│       ├── ApiHandlerTest.php                        # 15 tests: sanitize_messages(), tool_specs()
│       └── ToolsTest.php                             # 21 tests: all 5 WooCommerce tools
├── languages/
│   └── index.php                                     # Placeholder — WP.org auto-loads translations
├── readme.txt                                        # WordPress.org format
├── README.md                                         # GitHub format
└── uninstall.php                                     # Deletes all options on plugin uninstall
```

---

## Architecture

### Request Flow

**Anthropic (non-streaming):**
```
JS sendRegular()
  → POST /wp-json/mayaai/v1/message
    → Mayaai_API_Handler::handle_message()
      → run_anthropic_agent()         # loop: tool_use → end_turn
        → call_anthropic()            # wp_remote_post to api.anthropic.com
        → Mayaai_Tools::execute()     # WooCommerce operations
      → returns { message, messages }
  ← JS appendMessage('bot', reply)
     renderMarkdown() applied
```

**Moonshot (SSE streaming):**
```
JS sendStreaming()
  → POST /wp-json/mayaai/v1/stream
    → Mayaai_API_Handler::handle_stream()      # bypasses WP REST buffering
      → run_stream_agent()                      # loop: stream → execute tools → stream again
        → stream_one_turn()                     # raw cURL — only way to do SSE
          → emits SSE: chunk / tool / error
        → Mayaai_Tools::execute()               # between turns
      → emits SSE: done
  ← JS reads ReadableStream, parses SSE events
     'chunk' → append text (plain)
     'tool'  → show TOOL_LABELS status
     'done'  → re-render fullText with renderMarkdown()
     'error' → show message
```

### Why raw cURL for streaming

`wp_remote_post()` buffers the entire response before returning. SSE requires
writing chunks to the browser as they arrive. The two non-streaming paths
(`call_anthropic`, `call_moonshot`) both use `wp_remote_post()` correctly.
The cURL block has `phpcs:disable/enable` with this explanation so WP.org
reviewers understand the necessity.

---

## AI Providers

### Anthropic Claude
- **Endpoint:** `https://api.anthropic.com/v1/messages`
- **Auth header:** `x-api-key: {key}`
- **Tool format:** `input_schema` (JSON Schema inside `tools` array)
- **Tool loop:** response `stop_reason === 'tool_use'` → execute → append `tool_result` → repeat
- **Option key:** `mayaai_anthropic_api_key`
- **Models:** `claude-haiku-4-5-20251001`, `claude-sonnet-4-6`, `claude-opus-4-6`
- **Content filtering:** Haiku is aggressive — if "Output blocked by content filtering policy" appears, switch to Sonnet

### Moonshot AI (Kimi K2)
- **Endpoint:** `https://api.moonshot.ai/v1/chat/completions` (NOT `.cn` — international platform)
- **Auth header:** `Authorization: Bearer {key}`
- **API keys from:** https://platform.moonshot.ai
- **Tool format:** OpenAI-compatible (`parameters`, `type: "function"`)
- **System message:** prepended as first message (`role: system`) — not a top-level field
- **Tool loop:** `finish_reason === 'tool_calls'` → execute → append `tool` role message → repeat
- **Streaming:** SSE with `stream: true`, delta chunks, `[DONE]` sentinel
- **Option key:** `mayaai_moonshot_api_key`

---

## WooCommerce Tools

Five tools are defined in `Mayaai_API_Handler::tool_specs()` (canonical) and mapped to two formats:
- `get_anthropic_tools()` → uses `input_schema`
- `get_openai_tools()` → uses `parameters` + `type: "function"`

| Tool | Input | Key output fields |
|---|---|---|
| `search_products` | query, category, min_price, max_price, limit (≤10) | found, products[]{id, name, price, in_stock, url} |
| `get_product_details` | product_id (required) | full product data, variations[] for variable products, url |
| `add_to_cart` | product_id (required), quantity, variation_id | success, cart_item_key, cart_url, checkout_url |
| `view_cart` | — | items[], subtotal, total, cart_url, checkout_url |
| `remove_from_cart` | cart_item_key (required) | success, new_total |

### Cart session in REST context
WooCommerce doesn't initialise the cart for REST requests by default.
`handle_message()` and `handle_stream()` both call `wc_load_cart()` before
any tool execution.

---

## Linking in Chat Responses

The system prompt instructs the AI to:
1. Format every product name as `[Product Name](url)` using the `url` field from tool results
2. After `add_to_cart` success: append `[View Cart](cart_url) · [Checkout](checkout_url)`
3. When checkout is requested: include `[Proceed to Checkout](checkout_url)`

The JS `renderMarkdown(text)` function handles rendering:
1. Escapes all HTML first (XSS-safe)
2. Converts `[text](url)` → `<a>` — same-origin URLs only
3. Converts `**text**` → `<strong>`
4. Converts `\n` → `<br>`

Streaming path: text arrives as plain during `chunk` events, `renderMarkdown()`
is applied on `done` to the final assembled `fullText`.

---

## WordPress Options

All stored under the `mayaai_` prefix:

| Option | Default | Description |
|---|---|---|
| `mayaai_provider` | `anthropic` | `anthropic` or `moonshot` |
| `mayaai_anthropic_api_key` | `''` | Anthropic key (sk-ant-…) |
| `mayaai_anthropic_model` | `claude-haiku-4-5-20251001` | Claude model ID |
| `mayaai_moonshot_api_key` | `''` | Moonshot key (sk-…) |
| `mayaai_moonshot_model` | `kimi-k2-thinking-turbo` | Kimi model ID |
| `mayaai_bot_name` | `Store Assistant` | Widget header name |
| `mayaai_greeting` | `Hi! How can I help you today?` | First bot message |
| `mayaai_system_prompt` | `''` | Custom prompt appended to default |
| `mayaai_accent_color` | `#2563eb` | Widget header/button color |

All options are deleted by `uninstall.php` when the plugin is removed.

---

## Frontend Widget

`assets/js/chatbot.js` — vanilla JS, no dependencies, IIFE-wrapped.

**Config injected via `wp_localize_script` as `window.mayaaiChatbot`:**
- `apiUrl`, `streamUrl`, `provider`, `nonce`
- `botName`, `greeting`, `accentColor`
- `i18n` — translatable strings for all UI labels, error messages, and tool status labels

**Key functions:**
- `sendMessage()` — routes to `sendStreaming()` or `sendRegular()` based on `cfg.provider`
- `sendStreaming()` — fetch + ReadableStream, parses SSE `data:` lines
- `sendRegular()` — fetch + JSON, uses `e.message` from WP REST error response
- `appendMessage(role, text)` — `textContent` for user, `innerHTML = renderMarkdown()` for bot
- `appendEmptyBotBubble()` — returns the bubble element for direct streaming updates
- `renderMarkdown(text)` — safe HTML escape → link conversion → bold → newlines
- `esc(str)` — HTML escape helper (used in widget HTML construction only)

**CSS custom properties (set from JS on `documentElement`):**
- `--chatbot-accent` — from `cfg.accentColor`
- `--chatbot-accent-dark` — auto-darkened 20 points via `darkenHex()`

---

## Admin Settings

Located in `includes/admin-settings.php` — `mayaai_settings_page()`.

Page hook: `settings_page_maya-ai-shopping-assistant-for-woocommerce`
The main plugin's `enqueue_admin_assets()` checks for this hook string before enqueueing `mayaai-admin` (the provider toggle script).

Form fields use `mayaai_settings` nonce and `mayaai_save` submit button name. All `$_POST` reads pass through `wp_unslash()` and the appropriate sanitize callback.

The provider toggle JS (extracted from a previously-inline `<script>` block to satisfy WP.org review) lives in `assets/js/admin-settings.js`. It binds a `change` listener on the `#provider` select and toggles `#mayaai-anthropic` / `#mayaai-moonshot` visibility.

---

## REST API

**Namespace:** `mayaai/v1`
**Authentication:** `X-WP-Nonce` header with `wp_rest` action nonce

| Endpoint | Method | Handler | Used by |
|---|---|---|---|
| `/message` | POST | `Mayaai_API_Handler::handle_message()` | Anthropic provider |
| `/stream` | POST | `Mayaai_API_Handler::handle_stream()` | Moonshot provider |

`handle_stream()` bypasses WordPress REST buffering via:
```php
add_filter( 'rest_pre_serve_request', '__return_true' );
header( 'Content-Type: text/event-stream' );
header( 'X-Accel-Buffering: no' );  // disables nginx buffering
```

**SSE event types emitted:**
- `chunk` → `{ type, content }` — text delta
- `tool` → `{ type, name }` — tool executing (shows status label in UI)
- `error` → `{ type, message }` — error, streaming aborted
- `done` → `{ type }` — all turns complete

---

## Internationalization

Every user-facing string is wrapped in a gettext function with the `maya-ai-shopping-assistant-for-woocommerce` text domain.

**PHP:**
- `__()` / `esc_html__()` / `esc_html_e()` / `esc_attr_e()` — labels, headings, settings page
- `sprintf( __( '... %s ...', '...' ), $var )` — error messages with dynamic content (with `/* translators: */` comments)
- `Mayaai_API_Handler` — all `WP_Error` messages, including HTTP error formatters
- `Mayaai_Tools` — all tool result messages (success, error, hint)

**JS:**
- All UI labels, ARIA labels, placeholders, error messages, and tool status labels are passed via `wp_localize_script` as `mayaaiChatbot.i18n.*`
- The JS file uses `i18n.foo || 'fallback English'` to be defensive against missing keys

**Text domain matching:** WP.org review requires the text domain to exactly match the plugin slug. Both are `maya-ai-shopping-assistant-for-woocommerce`.

---

## Test Suite

**Runner:** `vendor/bin/phpunit --testdox`
**Stack:** PHPUnit 10.5 + Brain\Monkey 2.x + Mockery 1.x
**Coverage:** 36 tests, 117 assertions

**Class refs in tests:**
- `Mayaai_API_Handler::class` (was `WC_AI_Chatbot_API_Handler`)
- `Mayaai_Tools::class` (was `WC_AI_Chatbot_Tools`)
- Reflection on `Mayaai_*::$instance` to reset singletons between tests

**Running tests (Local by Flywheel PHP):**
```bash
cd /Users/isupercoder/websites/woocommerce-demo/app/public/wp-content/plugins/maya-ai-shopping-assistant-for-woocommerce
vendor/bin/phpunit --testdox
```

---

## WordPress.org Distribution

**Status:** Pending review. Submitted as `wc-ai-chatbot` (Apr 13, 2026), pended on Apr 23 with name/prefix issues. Renamed to "Maya AI Shopping Assistant for WooCommerce" on Apr 28 — slug change request submitted with v1.0.3 upload.

**v1.0.3 Plugin Check fixes:**
- Plugin name renamed (was too generic, collided with existing plugins)
- Slug change requested explicitly in upload comment + email reply
- All `wc_*` / `WC_*` prefixes replaced with `mayaai_*` / `MAYAAI_*` / `Mayaai_*`
- `Requires Plugins: woocommerce` header added
- Inline `<script>` in admin settings replaced with externally-enqueued `assets/js/admin-settings.js`
- All user-facing strings wrapped in gettext functions; JS strings passed via `wp_localize_script.i18n`
- Text domain matches slug exactly (per past TableCrafter review feedback)

**Building a release zip:**
```bash
cd /Users/isupercoder/Code/github
zip -r maya-ai-shopping-assistant-for-woocommerce-1.0.3.zip maya-ai-shopping-assistant-for-woocommerce \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/.git/*" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/vendor/*" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/tests/*" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/composer.json" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/phpunit.xml" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/phpunit.xml.bak" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/.phpunit.result.cache" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/.gitignore" \
  --exclude "maya-ai-shopping-assistant-for-woocommerce/CLAUDE.md"
```

**Reply to WP.org reviewer pattern (from past EventCrafter/LeadCrafter approvals):**
- Brief and direct — no copy-pasted AI fluff (reviewer flags this explicitly)
- Bullet list of categories addressed (don't enumerate every change)
- Slug change must be requested *explicitly* in the email body AND in the upload comment
