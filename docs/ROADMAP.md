# Concierge Bot — Roadmap

Known issues and improvements for the WhatsApp AI concierge, ordered by impact.

---

## High Priority

### 1. Preference corrections are silently ignored
**File:** `WebhookController::extractPreferences()` lines 199-213

Every field check has a `!$booking->pref_bed_type` guard. Once a preference is stored, it is locked. If the guest says "actually make it double instead of twin," the change is dropped silently.

**Fix:** Remove the "only set if empty" guard. Allow the extractor to overwrite existing values. Add a `pref_changed` flag or log so staff can see when a preference was updated mid-conversation.

---

### 2. No graceful degradation when AI fails
**File:** `WebhookController::handleGuestReply()` lines 175-188

If `GuestReplyAgent` throws, the guest gets zero reply. The exception is caught, logged, and the method returns 200. The guest stares at silence with no indication anything went wrong.

**Fix:** Add a fallback message in the catch block: "I'm having a small technical issue — our team has been notified and will get back to you shortly." Also send an alert to the staff phone number so they know a guest is waiting.

---

### 3. Voice notes are dead ends
**File:** `WebhookController::handleNonTextMessage()` line 527

"I can only read text messages" is a brick wall. Voice notes are extremely common on WhatsApp, especially from non-English speakers. Every voice note is a lost conversation.

**Fix:** Integrate speech-to-text (e.g. OpenAI Whisper or similar). Transcribe the voice note, then feed the transcription into the normal `handleGuestReply` flow. Fall back to the text-only message if transcription fails.

---

### 4. No conversation handback from manager
**File:** `WebhookController::handle()` line 106

The bot goes silent in `handover_human` state, but there is no implemented mechanism for the manager to hand the conversation back to the bot. Once the manager takes over, the bot stays dead unless someone manually updates the database.

**Fix:** Implement a `DONE-{BookingID}` handler (or similar) that the manager can send via WhatsApp to resume bot automation. Reset `conversation_state` to `preferences_complete` and send a notification confirming handback.

---

## Medium Priority

### 5. Upsell timing is context-blind
**File:** `UpsellBroadcastJob`

Offers are sent based purely on day-of-stay timing rules. The job does not check whether the guest just complained, has an unresolved service request, is mid-conversation, or what time it is in the guest's local timezone.

**Fix:** Add pre-send checks: skip if there is an unresolved service request in the last N hours, skip if the last guest message was less than 30 minutes ago (mid-conversation), and respect quiet hours based on property timezone.

---

### 6. FollowUpJob doesn't know when silence is expected
**File:** `FollowUpJob`

The follow-up job fires after 12 hours of guest silence regardless of context. If the bot's last message was a confirmation ("You're all set!"), no reply is expected. The bot sends an unnecessary nudge.

**Fix:** Before sending a follow-up, check the last outbound message. If it was a closing/confirmation message (preferences complete briefing, "you're all set," etc.), skip the follow-up or extend the silence window.

---

### 7. WhatsApp 24-hour session window
**File:** `TwoChatService::sendMessage()`

WhatsApp requires approved message templates for messages sent outside a 24-hour session window. Proactive messages (upsell broadcasts, follow-ups) could silently fail if sent outside the window without using a template. The code does not distinguish between session messages and template messages.

**Fix:** Track last inbound message timestamp per booking. If more than 24 hours have passed, use a pre-approved template message via the 2Chat template API instead of a freeform message.

---

### 8. Conversation window is still 10 messages for non-preference context
**File:** `GuestReplyAgent::messages()` line 164

Preferences are now always in the prompt (fixed), but general conversation context beyond 10 messages is lost. If a guest discusses a restaurant recommendation in message 5 and asks "what was that restaurant?" in message 25, the bot has no memory of it.

**Fix:** Consider either increasing the window to 20 (adds ~500 tokens per call) or implementing periodic conversation summarization — condense older messages into a summary block that persists in the prompt alongside the recent 10.

---

## Low Priority

### 9. No check-out flow
There is no automated message when a guest checks out. No thank-you, no feedback request, no "rate your stay" prompt. The conversation just stops.

**Fix:** Add a check-out job triggered by `booking_status` changing to `checked_out`. Send a warm farewell message, optionally ask for a review, and offer to help with future bookings.

---

### 10. Error swallowing across the board
**Files:** `extractPreferences()`, `detectAndNotifyServiceRequest()`, `regenerateCustomerProfile()`, `sendPreferencesBriefing()`

All catch `\Throwable` and log warnings. If any consistently fail (bad API key, quota exhaustion), the system degrades silently. No alerting, no circuit breaker.

**Fix:** Integrate Sentry or similar error tracking. Add a threshold alert: if the same agent fails N times in M minutes, notify the staff/admin. Consider a health check endpoint that reports agent success rates from `system_logs`.
