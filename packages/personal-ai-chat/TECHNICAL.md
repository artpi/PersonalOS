# Personal AI Chat contract

AI Chat is an independent private WpApp at `/ai-chat/`. Store conversations as
Knowledge and use the [shared contract](../../docs/knowledge-contract.md) for
ownership, metadata and vocabulary.

Generation remains server-side and provider-agnostic through package REST
endpoints and WordPress AI Client/Connectors. Keep provider credentials in
Connectors rather than package-local credentials. Save user/assistant turns in
conversation Knowledge as `pos/ai-message` blocks; preserve meaningful model and
response metadata (`pos_model`, `pos_last_response_id`).

Use the package's PHP class and block metadata for exact REST and block schemas.
Do not restore old monolith `/pos/v1/openai/*` transport or root editor assets.

Verification must exercise conversation creation and a response through the real
UI, persistence, permissions and configured/unconfigured provider states. Inspect
the actual abilities response before array operations; a response-shape mismatch
previously crashed creation. Activation/route registration does not prove this
flow works, and this contract does not claim the observed bug is fixed.
