# AI Chatbot for WooCommerce — Developer Knowledge Base

Plugin folder: `wc-ai-chatbot/`
Main file: `wc-ai-chatbot.php`
GitHub: https://github.com/fahdi/wc-ai-chatbot
Current version: 1.0.2

---

## File Structure

```
wc-ai-chatbot/
├── wc-ai-chatbot.php              # Bootstrap: plugin header, WC_AI_Chatbot class, REST routes
├── includes/
│   ├── class-api-handler.php      # All AI logic: agents, API calls, SSE streaming, tools specs
│   ├── class-tools.php            # WooCommerce operations executed by the AI
│   └── admin-settings.php        # Settings page (provider, keys, widget config)
├── assets/
│   ├── js/chatbot.js              # Frontend widget — vanilla JS, no dependencies
│   └── css/chatbot.css            # Widget styles with CSS custom properties
├── tests/
│   ├── bootstrap.php              # PHPUnit bootstrap: loads stubs + plugin classes
│   ├── stubs/wc-stubs.php         # WP_Error, WC_Product, WC_Cart stubs for tests
│   └── unit/
│       ├── ApiHandlerTest.php     # 15 tests: sanitize_messages(), tool_specs()
│       └── ToolsTest.php          # 21 tests: all 5 WooCommerce tools
├── languages/
│   └── index.php                  # Placeholder — WP.org auto-loads translations
├── readme.txt                     # WordPress.org format
├── README.md                      # GitHub format (includes Playground badge)
└── uninstall.php                  # Deletes all options on plugin uninstall
```

---

## Architecture

### Request Flow

**Anthropic (non-streaming):**
```
JS sendRegular()
  → POST /wp-json/wc-chatbot/v1/message
    → handle_message()
      → run_anthropic_agent()         # loop: tool_use → end_turn
        → call_anthropic()            # wp_remote_post to api.anthropic.com
        → Tools::execute()            # WooCommerce operations
      → returns { message, messages }
  ← JS appendMessage('bot', reply)
     renderMarkdown() applied
```

**Moonshot (SSE streaming):**
```
JS sendStreaming()
  → POST /wp-json/wc-chatbot/v1/stream
    → handle_stream()                 # bypasses WP REST buffering
      → run_stream_agent()            # loop: stream → execute tools → stream again
        → stream_one_turn()           # raw cURL — only way to do SSE
          → emits SSE: chunk / tool / error
        → Tools::execute()            # between turns
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
- **Option key:** `wc_ai_chatbot_anthropic_api_key`
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
- **Option key:** `wc_ai_chatbot_moonshot_api_key`
- **Models (live on api.moonshot.ai):**
  - `kimi-k2-thinking-turbo` — recommended default
  - `kimi-k2-thinking` — full reasoning
  - `kimi-k2.5` — latest, vision + reasoning
  - `kimi-k2-0905-preview`, `kimi-k2-turbo-preview`
  - `moonshot-v1-auto`, `moonshot-v1-8k`, `moonshot-v1-32k`, `moonshot-v1-128k`

---

## WooCommerce Tools

Five tools are defined in `tool_specs()` (canonical) and mapped to two formats:
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

All stored under the `wc_ai_chatbot_` prefix:

| Option | Default | Description |
|---|---|---|
| `wc_ai_chatbot_provider` | `anthropic` | `anthropic` or `moonshot` |
| `wc_ai_chatbot_anthropic_api_key` | `''` | Anthropic key (sk-ant-…) |
| `wc_ai_chatbot_anthropic_model` | `claude-haiku-4-5-20251001` | Claude model ID |
| `wc_ai_chatbot_moonshot_api_key` | `''` | Moonshot key (sk-…) |
| `wc_ai_chatbot_moonshot_model` | `kimi-k2-thinking-turbo` | Kimi model ID |
| `wc_ai_chatbot_bot_name` | `Store Assistant` | Widget header name |
| `wc_ai_chatbot_greeting` | `Hi! How can I help you today?` | First bot message |
| `wc_ai_chatbot_system_prompt` | `''` | Custom prompt appended to default |
| `wc_ai_chatbot_accent_color` | `#2563eb` | Widget header/button color |

All options are deleted by `uninstall.php` when the plugin is removed.

---

## Frontend Widget

`assets/js/chatbot.js` — vanilla JS, no dependencies, IIFE-wrapped.

**Config injected via `wp_localize_script` as `window.wcAIChatbot`:**
`apiUrl`, `streamUrl`, `provider`, `nonce`, `botName`, `greeting`, `accentColor`

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

## REST API

**Namespace:** `wc-chatbot/v1`
**Authentication:** `X-WP-Nonce` header with `wp_rest` action nonce

| Endpoint | Method | Handler | Used by |
|---|---|---|---|
| `/message` | POST | `handle_message()` | Anthropic provider |
| `/stream` | POST | `handle_stream()` | Moonshot provider |

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

## Test Suite

**Runner:** `vendor/bin/phpunit --testdox`
**Stack:** PHPUnit 10.5 + Brain\Monkey 2.x + Mockery 1.x
**Coverage:** 36 tests, 117 assertions — all green

**Key patterns:**
- `WC_AI_Chatbot_API_Handler` and `WC_AI_Chatbot_Tools` are singletons; tests reset
  the `$instance` property via `ReflectionProperty` before each test
- `sanitize_textarea_field` is NOT stubbed in `ApiHandlerTest::setUp()` — tests that
  need pass-through add `Functions\when()->returnArg()` per-test; the sanitization
  test uses `Functions\expect()->once()` to assert the function is called
- Mockery stubs on `mockProduct()` use `->byDefault()` so individual tests can
  override `is_visible` / `is_in_stock` without double-stub conflicts

**Running tests (Local by Flywheel PHP):**
```bash
cd /Users/isupercoder/websites/woocommerce-demo/app/public/wp-content/plugins/wc-ai-chatbot
vendor/bin/phpunit --testdox
```
MySQL socket symlink required for WP-CLI:
```bash
ln -sf "/Users/isupercoder/Library/Application Support/Local/run/kwQ7_3vip/mysql/mysqld.sock" /tmp/mysql.sock
```

---

## WordPress.org Distribution

**Plugin name:** AI Chatbot for WooCommerce (renamed from "WC AI Chatbot" — "wc" is a restricted trademark)
**Slug (for WP.org submission):** `ai-chatbot-for-woocommerce`
**Text domain:** `wc-ai-chatbot`
**Playground preview:** https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fwordpress.org%2Fplugins%2Fwp-json%2Fplugins%2Fv1%2Fplugin%2Fwc-ai-chatbot%2Fblueprint.json%3Fzip_hash%3D27be4be624dce5f524e5529f9753bded%26type%3Dpcp

**Plugin Check status (v1.0.2):** all errors and warnings resolved
- cURL: `phpcs:disable/enable` with SSE justification — passes human review
- `wp_unslash()` on all `$_POST` reads
- `load_plugin_textdomain()` removed (WP 4.6+ auto-loads)
- Tags limited to 5
- `$wc_ai_chatbot_options` prefixed in `uninstall.php`

**Building a release zip:**
```bash
cd .../wp-content/plugins
zip -r wc-ai-chatbot-X.X.X.zip wc-ai-chatbot \
  --exclude "wc-ai-chatbot/.git/*" \
  --exclude "wc-ai-chatbot/vendor/*" \
  --exclude "wc-ai-chatbot/tests/*" \
  --exclude "wc-ai-chatbot/composer.json" \
  --exclude "wc-ai-chatbot/phpunit.xml" \
  --exclude "wc-ai-chatbot/phpunit.xml.bak" \
  --exclude "wc-ai-chatbot/.phpunit.result.cache" \
  --exclude "wc-ai-chatbot/.gitignore"
```
