# \[DRAFT\] Overview

# E-Conciergerie \- Development Proposal Overview

**Version:** 1.2   
**Date:** March 24, 2026   
**Property:** Riad Larbi Khalis (4 suites)   
**Status:** Draft \- Pending Stakeholder Input

---

## 1\. Executive Summary

This proposal outlines the development plan for E-Conciergerie, a system of AI agents that automates the management of Riad Larbi Khalis through WhatsApp and Google Sheets. The system covers guest communication, staff coordination, financial tracking, reporting, and cancellation recovery.

The full E-Conciergerie vision includes 9 agents. This proposal divides them into two delivery phases based on what can be built within a 3-week timeline by a single developer, and what requires additional time or resources.

**Phase 1 (3 weeks, included in this engagement)** delivers the complete operational backbone of the concierge system. After Phase 1, Riad Larbi Khalis has a fully functioning AI concierge that handles guest communication from booking to checkout, upsells during stays, tracks all finances, produces weekly and monthly reports, and responds to cancellations with intelligent recovery plans.

**Phase 2 (additional scope, two delivery options)** covers dynamic pricing via PriceLabs integration and automated marketing (social media content and paid ads). These are revenue optimization and growth features that enhance the system but are not required for daily concierge operations.

---

## 2\. What Phase 1 Delivers (3 Weeks)

Phase 1 covers 7 of the 9 agents, grouped into the proposals listed below. Each proposal is a self-contained document with scope, sequence diagrams, data model, failure modes, and stakeholder questions.

| Proposal | Agents Covered | What It Does |
| :---- | :---- | :---- |
| Proposal 1: Guest Welcome \+ Staff Briefing | Agent 1 \+ Agent 2 | Detects new bookings from Lodgify. Sends a personalized WhatsApp greeting as "Yasmine." Collects 4 guest preferences through multi-turn conversation in any language. Handles follow-ups, escalations, and manager handoff. Generates bilingual staff briefing (French \+ Arabic) and delivers to manager and owner. Handles missing phone numbers, group bookings, unassigned suites, last-minute arrivals. Archives completed stays. |
| Proposal 2: Upsell | Agent 3 | Sends timed WhatsApp offers during the stay (breakfast, hammam, excursions, dinner). Adapts to stay duration. Handles acceptance, decline, questions, custom requests, and off-menu inquiries. Notifies manager instantly on acceptance. Logs everything for revenue tracking. |
| Proposal 3: Cancellations | Agent 6 | Detects booking cancellations within 15 minutes. Alerts manager and owner with full details. Generates a contextual recovery action plan using Claude (pricing suggestions, minimum stay adjustments, platform tactics). Stops all automated messaging for the cancelled guest. Logs revenue loss. |
| Proposal 4: Monthly Report \+ Accounting | Agent 7 \+ Agent 8 | Logs every financial event (booking revenue, upsell revenue, manager-entered expenses). Sends weekly financial snapshot to owner every Monday. Generates monthly Excel export for the accountant on the 1st. Sends comprehensive monthly performance report covering bookings, revenue, upsell, expenses, occupancy rate, and suite performance. |

**Agent 9 (Orchestrator)** is not listed as a separate proposal because its monitoring capabilities are built incrementally into every other agent. Each scenario writes to the System Log tab, includes error handlers that alert the manager, and tracks conversation states. A dedicated Orchestrator scenario (daily health check at 7 AM) will be added as the final step of Phase 1, reading from the System Log and scenario status data that all other agents produce.

### What the Owner Gets After Phase 1

After 3 weeks, the day-to-day operation of Riad Larbi Khalis is automated:

- Every new guest receives a warm, personalized WhatsApp welcome within 15 minutes of booking.  
- Guest preferences are collected automatically in any language.  
- The manager and owner receive a complete bilingual briefing for every guest.  
- During stays, guests receive well-timed upsell offers. Acceptances trigger instant staff notifications.  
- Every booking, upsell sale, and expense is logged automatically.  
- The owner receives a weekly financial snapshot every Monday and a full monthly report with Excel export on the 1st.  
- Cancellations are detected within 15 minutes with an intelligent recovery plan delivered to the owner's phone.  
- If anything goes wrong, the manager is alerted immediately. The system logs every event for debugging.  
- All of this runs 24/7 on cloud servers. No computer needs to be on. No app to install.

This covers the full concierge operational cycle: booking intake, guest communication, staff coordination, upselling, financial tracking, reporting, and cancellation response.

---

## 3\. What Phase 2 Covers (Agent 4 and Agent 5\)

Two agents are not included in Phase 1\. To be transparent: these agents require integrations and complexity that a single developer cannot deliver within 3 weeks alongside the core system. Rather than rush them and deliver poorly, I am being upfront about this limitation and offering two options to deliver them properly.

| Agent | What It Does | Why It Cannot Fit in 3 Weeks |
| :---- | :---- | :---- |
| Agent 4 (Dynamic Pricing) | Adjusts room prices daily based on season, occupancy, and local events. Connects to PriceLabs and Lodgify. Sends pricing recommendations to owner with approve/override option. | Requires PriceLabs account setup, PriceLabs-Lodgify integration onboarding, and a pricing strategy definition (base rates, min/max bounds, event calendar). PriceLabs is a separate product with its own learning curve and configuration. |
| Agent 5 (Content and Ads) | Staff send photos to a WhatsApp group. System auto-publishes to Instagram and Facebook. When occupancy drops, system launches targeted Meta ad campaigns. | Requires Meta Business Suite, Facebook Page, Instagram Professional account, Meta Marketing API access (with ads\_management permission), ad account with payment method, audience targeting configuration, and a content moderation review gate. The ad automation alone is a significant build. |

These agents are revenue optimization and growth features. Riad Larbi Khalis operates fully without them. The core concierge operations (guest communication, upselling, financial tracking, reporting, cancellation response) are all delivered in Phase 1\.

### Phase 2 Delivery Options

I can deliver Agent 4 and Agent 5, but not within the Phase 1 timeline. Two options are available:

**Option A: Add a developer, keep the timeline short.**

A second developer joins the project after Phase 1 is delivered and tested. Both developers work in parallel: one on Agent 4 (Dynamic Pricing \+ PriceLabs), one on Agent 5 (Content and Ads \+ Meta APIs). Estimated delivery: 2 to 3 weeks after Phase 1 completion.

This option is faster but increases the development budget. It also requires coordination between developers and knowledge transfer on the existing architecture.

**Option B: Keep one developer, extend the timeline.**

I continue with Phase 2 sequentially after delivering Phase 1\. Agent 4 first, then Agent 5\. Total Phase 2 delivery: 5 weeks after Phase 1 completion.

This option takes longer but maintains full architectural continuity. No knowledge transfer, no coordination overhead. One person built it, one person extends it.

|  | Option A | Option B |
| :---- | :---- | :---- |
| Developers | 2 | 1 |
| Phase 2 timeline | 2 to 3 weeks | 5 weeks |
| Total project timeline (Phase 1 \+ 2\) | 5 to 6 weeks | 8 weeks |
| Estimated budget (Phase 2 only) | Rp 16,000,000 to Rp 20,000,000 | Rp 16,000,000 to Rp 20,000,000 |
| Coordination overhead | Yes | None |
| Architecture consistency | Requires knowledge transfer | Full continuity |

The stakeholder does not need to decide on Phase 2 before Phase 1 begins. Phase 1 is self-contained and delivers full operational value independently.

---

## 5\. Commercial Terms

### Phase 1 Budget

| Item | Scope | Budget |
| :---- | :---- | :---- |
| Phase 1 | Agent 1, 2, 3, 6, 7, 8 (full concierge operations) | **Rp 8,000,000** (fixed, scoped to proposals delivered) |

This is a fixed-price engagement scoped to the agent proposals listed above. The budget covers development, testing, staging environment setup, production deployment, and scenario export backups to GitHub.

The budget does not cover third-party service costs (Make.com subscription, 2Chat subscription, Anthropic API credits, Lodgify community connector fee). These are paid directly by the client.

### Phase 2 Budget (Estimated)

| Option | Scope | Timeline | Estimated Budget |
| :---- | :---- | :---- | :---- |
| Option A: Add a developer, keep timeline | Agent 4 (Dynamic Pricing \+ PriceLabs) \+ Agent 5 (Content and Ads \+ Meta APIs) delivered in parallel | 2 to 3 weeks after Phase 1 | **Rp 16,000,000 to Rp 20,000,000** |
| Option B: Keep one developer, extend timeline | Same scope, delivered sequentially by the original developer | 5 weeks after Phase 1 | **Rp 16,000,000 to Rp 20,000,000** |

Phase 2 budget is an estimate and will be finalized in a separate proposal once Phase 1 is delivered and the stakeholder confirms Phase 2 scope. The budget range accounts for the complexity of Meta API integration (Agent 5\) and PriceLabs onboarding (Agent 4), both of which depend on external vendor setup timelines.

---

## 6\. Delivery Strategy

The development follows a **1 week, 1 agent group** cadence. Each week includes both development and testing for that week's agent group. No agent is considered delivered until its acceptance tests pass in the staging environment.

| Week | Agent Group | Development | Testing |
| :---- | :---- | :---- | :---- |
| Week 1 | Agent 1 \+ 2 (Guest Welcome \+ Staff Briefing) | Build all 6 scenarios, configure integrations | Run all 11 acceptance tests in staging |
| Week 2 | Agent 3 (Upsell) \+ Agent 6 (Cancellations) | Build upsell scheduler, reply handler, cancellation branch | Test upsell flow end-to-end, test cancellation detection and alerts |
| Week 3 | Agent 7 \+ 8 (Reporting \+ Accounting) \+ Agent 9 (Orchestrator) \+ Go-Live | Build revenue logger, expense input, weekly/monthly reports, health check | Full system end-to-end test, migrate to production |

This timeline assumes all stakeholder questions across all proposals are answered before development begins. Delays in stakeholder input directly delay the timeline.

---

## 7\. Adjustment Policy

This proposal exists so the stakeholder and the developer share the same understanding of what will be built before any work begins. The proposals document every flow, every edge case, every failure mode, and every sequence diagram for this reason.

**Adjustments within scope are welcome.** Changes to wording, message tone, timing intervals, category names, or similar configuration-level adjustments are normal and expected. These do not affect the budget or timeline as long as they do not break an existing flow or introduce a new one.

**New flows are new developments.** If during review the stakeholder identifies an edge case, a new workflow, or a requirement that is not documented in any of the delivered proposals, that is new scope. New scope requires additional development time and budget, negotiated separately.

This is why the proposals are detailed. They are not documentation for documentation's sake. They are the contract definition of what "done" means.

**What counts as an adjustment (included):**

- Changing the follow-up interval from 12 hours to 8 hours.  
- Adjusting the Yasmine persona tone from "warm" to "more formal."  
- Changing expense categories from "cleaning, food, maintenance" to "nettoyage, alimentation, entretien."  
- Reordering the upsell offer schedule.  
- Changing the weekly snapshot day from Monday to Sunday.

**What counts as a new flow (additional scope):**

- Adding a new conversation state or routing branch that changes how messages are processed.  
- Adding a guest feedback collection step after checkout (not in any current proposal).  
- Adding multi-property support (the entire system is scoped to a single property).  
- Adding payment processing or invoice generation.  
- Adding integration with a tool not in the tech stack (e.g., a CRM, an external calendar system).

**The stakeholder's responsibility before we start:** Read every proposal. Review every sequence diagram. Check every edge case listed in the failure modes tables. If there is a scenario the stakeholder expects the system to handle that is not documented, raise it now. Once we agree on the proposals and begin development, the scope is locked.

---

## 8\. Working Arrangement and On-Site Visits

This engagement is fully remote. All development, testing, and deployment is done remotely using the cloud-based tools listed in the tech stack.

If an on-site visit to Bali is needed (for example, for system handover, staff training, or collaborative troubleshooting), I am available to travel under the following conditions:

- Visits must be planned in advance. I cannot accommodate last-minute travel requests.  
- On-site visits are scheduled on weekends or holidays, coordinated with my current work arrangement (shift swap with a colleague).  
- Accommodation during on-site visits is provided by the stakeholder. Travel costs are discussed and agreed upon separately before the visit is confirmed.

On-site visits are not required for Phase 1 delivery. The entire system can be built, tested, and deployed remotely. However, if the stakeholder prefers a face-to-face handover or wants on-site staff training for the manager, this option is available with proper planning.

---

## 9\. Agreement and Next Steps

The following steps must be completed before development begins.

| Step | Action | Owner |
| :---- | :---- | :---- |
| 1 | Stakeholders review all proposal documents (Proposals 1 through 4). | Stakeholder |
| 2 | Stakeholders answer all questions in each proposal (fill in the "Answer" column and return). | Stakeholder |
| 3 | Stakeholder raises any missing edge cases, flows, or requirements not covered in the proposals. | Stakeholder |
| 4 | The developer incorporates stakeholder feedback and finalizes proposals. | Developer |
| 5 | Both parties agree on the final proposals. This can be a gentlemen's agreement or a formal contract, but a clear written confirmation is required. | Both |
| 6 | Stakeholder confirms all prerequisite accounts are set up (Make.com, Lodgify test, 2Chat, Anthropic, Google, GitHub). | Stakeholder |
| 7 | Development begins. Scope is locked. | Developer |

**Why written agreement matters:** The proposals define every flow the system will handle. Once agreed, the developer builds exactly what is documented. No back-and-forth on scope during development. No "can you also add this" mid-sprint. This protects both sides: the stakeholder knows exactly what they are getting, and the developer knows exactly what they need to deliver. The result is on-time delivery with no surprises.

---

## 10\. Tech Stack

All agents are built on the same infrastructure. No custom server hosting required. Everything runs on managed cloud services.

| Tool | Role |
| :---- | :---- |
| Make.com | Workflow orchestration. All scenarios run here. |
| Claude AI (Anthropic) | Message generation, preference extraction, classification, report formatting, recovery plan generation. |
| 2Chat | WhatsApp messaging bridge. Sends and receives messages via API. Recommended to migrate to WABA (WhatsApp Business API) connection for production stability and interactive button support. |
| Google Sheets | Shared data layer. Booking data, conversation state, accounting transactions, upsell logs, system logs. |
| Lodgify | Booking management system. Source of booking data via community connector on Make.com. |
| GitHub | Version control for prompts, Make.com scenario exports (JSON blueprints), sheet templates, and documentation. |

**Phase 2 additions:** PriceLabs (Agent 4), Meta Business Suite / Graph API / Marketing API (Agent 5).

---

## 11\. Proposal Documents

Each proposal below is a standalone document with full technical detail. They should be read in order.

| \# | Document | Status |
| :---- | :---- | :---- |
| 1 | Agent 1 \+ 2: Guest Welcome \+ Staff Briefing | Delivered |
| 2 | Agent 3: Upsell | Delivered |
| 3 | Agent 6: Cancellations | Delivered |
| 4 | Agent 7 \+ 8: Monthly Report \+ Accounting | Delivered |
| 5 | Agent 4 \+ 6 (with PriceLabs): Dynamic Pricing \+ Enhanced Cancellations | Phase 2 (not yet written) |
| 6 | Agent 5: Content and Ads | Phase 2 (not yet written) |

---

## 12\. Stakeholder Questions (Consolidated)

Each individual proposal contains its own detailed stakeholder questions. The following are cross-cutting questions that affect the entire engagement.

| \# | Question | Context | Answer |
| :---- | :---- | :---- | :---- |
| 1 | Is the 3-week timeline for Phase 1 acceptable? | Development begins after all stakeholder questions are answered. Delays in input delay the timeline. |  |
| 2 | Are all prerequisite accounts available or in progress? | Make.com, Lodgify (test), 2Chat, Anthropic, Google, GitHub. See Proposal 1 for full list. |  |
| 3 | Is the client willing to migrate to 2Chat WABA before go-live? | The QR code WhatsApp connection works for development but is unstable and non-compliant for production. WABA provides interactive buttons, stable connection, and compliance with WhatsApp policies. Requires Meta Business verification (1 to 2 weeks). |  |
| 4 | Does the client want to proceed with Phase 2? If yes, which option? | Option A: add a developer, keep timeline (2 to 3 weeks), higher budget. Option B: keep one developer, extend timeline (5 weeks), same budget range. Decision not required before Phase 1 starts. |  |
| 5 | Who is the primary point of contact for stakeholder questions? | We need timely answers to proceed. One person who can make decisions on property operations, pricing, brand tone, and service offerings. |  |
| 6 | Does the stakeholder agree to the adjustment policy? | Adjustments within existing flows are included. New flows or edge cases not documented in the proposals are additional scope. See Section 7 for definitions. |  |

# \[DRAFT\] Agent 1  & 2 \- Yasmine

# E-Conciergerie \- Agent 1 (Guest Welcome): Development Proposal

**Version:** 2.1   
**Date:** March 24, 2026   
**Property:** Riad Larbi Khalis (4 suites)   
**Status:** Draft \- Pending Stakeholder Input

---

## 1\. Goal

Automate the first point of contact with every guest who books a stay. When a new booking arrives in Lodgify, the system initiates a WhatsApp conversation as "Yasmine" (the digital concierge), collects four specific preferences (arrival time, bed type, airport transfer, and special requests), and stores them in a structured format.

Once all four preferences are collected, the system generates a bilingual staff briefing (French and Arabic) and delivers it to the manager and owner via WhatsApp. This briefing (originally scoped as Agent 2\) is embedded directly into the reply handling flow to eliminate unnecessary latency and cross-scenario dependencies.

The system must handle multi-turn conversations in any language, tolerate unstructured human replies, queue messages during nighttime hours, follow up on silence, escalate to a human when it cannot resolve the conversation, and allow the manager to resume automation after manual intervention.

No human intervention is required for the happy path. A human only gets involved when something goes wrong.

---

## 2\. Prerequisites

The following accounts and access must be set up before development begins. Missing any of these is a hard blocker.

| \# | Requirement | Purpose | Notes |
| :---- | :---- | :---- | :---- |
| 1 | Make.com account | Workflow orchestration. All scenarios run here. | Core plan or higher recommended. Free plan has operation limits and imprecise scheduling. |
| 2 | Lodgify account (test) | Source of booking data. Must be able to create test bookings with phone numbers. | Also requires purchasing one of the community Lodgify connectors for Make.com (Codex Solutions, MAXMEL Tech, or Synergetic). The connector must support a "new booking" trigger and return guest phone numbers. Verify before purchasing. |
| 3 | 2Chat account | Bridge between Make.com and WhatsApp. Enables sending and receiving WhatsApp messages via webhooks and REST API. | 2Chat supports two connection modes: (1) **WhatsApp Web** via QR code scan, which is fast to set up but limited (no interactive buttons, no message templates, connection drops if the phone goes offline, risk of account ban for automated messaging without official API), and (2) **WhatsApp Business API (WABA)**, which is the official Meta-certified path with full capabilities (interactive buttons, approved message templates, stable connection, no ban risk). **We recommend WABA** for production use. The QR code method is acceptable for initial development and testing, but the system should migrate to WABA before going live with real guests. 2Chat is a Meta-certified provider and supports both modes, so no provider change is needed for the migration. Must also verify webhook limits (single or multiple URLs) as this affects scenario architecture. |
| 4 | Anthropic API key | Powers the Claude module in Make.com for message generation and preference extraction. | Clients need their own Anthropic account with billing enabled. Estimated cost at MVP volume (approximately 50 bookings per month): under $10 per month. Recommended model: claude-sonnet-4-20250514. |
| 5 | Google account | Google Sheets as the data layer. | A dedicated service account is preferred over a personal account. The spreadsheet must be created directly in Google Sheets, not uploaded from Excel (Make.com sometimes cannot detect uploaded files). |
| 6 | GitHub repository | Version control for prompts, Make.com scenario exports (JSON blueprints), sheet templates, and documentation. | Make.com scenarios are exportable as JSON. Storing them in Git enables diffing, rollback, and change history. Prompts are maintained here and referenced by Make.com, not hardcoded into modules. |
| 7 | Test phone numbers | Minimum 2: one simulating the guest, one for manager alerts. Must be real phone numbers that can receive WhatsApp messages. | Phone numbers must be in international E.164 format (e.g., \+33612345678, \+212661234567). |

### Environments

Two separate environments are required. Testing in production means real guests receive broken messages.

- **Development / Staging:** Test Lodgify property (with fake suites and bookings), test WhatsApp number linked via 2Chat, test Google Sheet, development Make.com scenarios. All development and QA happens here.  
- **Production:** Real Lodgify property, production WhatsApp number, production Google Sheet, production Make.com scenarios. Only promoted after all acceptance tests pass. The migration from staging to production is a single change: swap the Lodgify API key.

---

## 3\. Scope

### In Scope

- Detect new bookings from Lodgify via Make.com scheduled trigger (every 15 minutes).  
- Validate booking data: filter cancelled/declined bookings, handle missing phone numbers, detect group bookings, handle unassigned suites, and handle last-minute arrivals.  
- Format and normalize guest phone numbers to international E.164 format.  
- Prevent duplicate processing by checking both Active and Archived booking tabs.  
- Detect guest language from booking country data.  
- Send a personalized WhatsApp greeting via 2Chat.  
- Handle guest replies: extract preferences from free-form text in any language.  
- Track conversation state per guest in Google Sheets.  
- Handle non-text messages (images, voice notes) with a polite text-only request.  
- Prevent duplicate webhook processing via message ID tracking.  
- Enforce single-execution concurrency on the reply handler to prevent race conditions.  
- Queue outbound messages during nighttime hours (10pm to 7am) and send at 8am.  
- Generate and send bilingual staff briefing (French and Arabic) when all preferences are collected.  
- Send follow-up messages on silence (up to 2 follow-ups, configurable intervals).  
- Escalate to manager via WhatsApp alert on timeout, uninterpretable replies, or frustrated guests.  
- Provide a DONE handler allowing the manager to resume automation after manual intervention.  
- Archive completed stays (move from Active to Archived tab after checkout).  
- Log every conversation turn and system event for debugging and audit.

### Dependent Scope

The following agents depend on Agent 1 being functional before they can operate. They are not part of this development phase, but their requirements inform the design decisions made here.

| Agent | Dependency on Agent 1 |
| :---- | :---- |
| Agent 3 (Upsell) | Relies on guest language, check-in date, and suite information that Agent 1 captures and stores. Upsell messages must not start until Agent 1 has finished preference collection (state \= PREFERENCES\_COMPLETE). |
| Agent 4 (Dynamic Pricing) | Uses booking volume and occupancy data from the Active Bookings sheet that Agent 1 populates. |
| Agent 6 (Cancellations) | Depends on the Lodgify trigger and booking state tracking established in Agent 1\. |
| Agent 9 (Orchestrator) | Monitors Agent 1 conversation states and the System Log tab to detect stuck or stale bookings. The state machine and logging designed in Agent 1 must support this monitoring. |

---

## 4\. Data Model (Google Sheets)

One Google Sheets spreadsheet named "Riad Larbi Khalis \- Bookings" with five tabs. All six scenarios read from and write to this shared file. Scenarios cannot communicate with each other directly; Google Sheets is the shared state layer.

### Tab: Active Bookings

Stores one row per active booking. Contains 19 columns covering guest contact information from Lodgify, the four extracted preferences, conversation state, follow-up tracking, phone validation status, nighttime message queue, and duplicate detection.

Key state-tracking columns:

- **Conversation State** (Column L): Drives all routing logic. Values: WAITING\_PREFERENCES, PREFERENCES\_PARTIAL, PREFERENCES\_COMPLETE, ISSUE\_DETECTED, HANDOVER\_HUMAN, PHONE\_MISSING, GROUP\_BOOKING, SUITE\_PENDING, CHECKED\_OUT, CANCELLED.  
- **Follow-up Count** (Column M): Integer, 0 to 2\. Incremented by the follow-up scenario. Escalation triggers at 2\.  
- **Phone Confirmed** (Column N): "yes" or "no". Tracks whether the phone number was present and validated at intake.  
- **Pending Response / Pending Response Time** (Columns Q, R): Used to queue outbound messages during nighttime hours.  
- **Last Message ID** (Column S): Stores the most recent inbound message ID from 2Chat to prevent duplicate webhook processing.

### Tab: Archived Bookings

Identical 19-column structure. Completed stays are moved here after checkout. Keeps the Active tab small and fast.

### Tab: Accounting

Reserved for Agent 8\. Not populated by Agent 1\.

### Tab: Upsell Log

Reserved for Agent 3\. Not populated by Agent 1\.

### Tab: System Log

Every error, escalation, and notable system event is recorded here with timestamp, event type, booking ID, and details. Used by Agent 9 (Orchestrator) for health monitoring and by developers for debugging.

### Migration Note

Google Sheets is adequate for the MVP with a single property and low booking volume (under approximately 50 bookings per month). However, Sheets has real limitations: no transactions, no foreign keys, no concurrent write safety beyond the concurrency lock on Make.com, API rate limits (60 requests per minute), and a 10 million cell cap.

The Sheet structure is designed as flat, normalized tables with primary keys and foreign key relationships so it maps directly to database tables. Do not use merged cells, conditional formatting as logic, or formulas that drive behavior.

Migration milestones:

- **5 to 6 properties:** Upgrade Make.com to Core plan for quota and precise scheduling.  
- **10 properties:** Migrate from 2Chat to official Meta WhatsApp Cloud API (approximately 3 hours, only endpoint URLs and API keys change).  
- **50 properties:** Migrate from Google Sheets to PostgreSQL/Supabase (approximately 3 days, only data modules change, all logic stays).

---

## 5\. Make.com Scenarios

Six scenarios. Each is a separate Make.com scenario with its own trigger and specific responsibility.

| \# | Scenario Name | Trigger | Schedule | Purpose |
| :---- | :---- | :---- | :---- | :---- |
| 1 | RLK \- New Booking Flow | Lodgify polling | Every 15 minutes | Detects new bookings, validates data, handles edge cases (missing phone, group booking, no suite, last-minute arrival), formats phone, prevents duplicates, detects language, generates and sends Yasmine greeting via 2Chat. |
| 2 | RLK \- Incoming Replies | 2Chat webhook (instant) | Real-time | Processes guest replies, deduplicates webhooks, rejects non-text messages, enforces concurrency, extracts preferences via Claude, updates state, routes by state (complete/partial/issue/handover), queues nighttime messages, sends bilingual staff briefing on completion. |
| 3 | RLK \- DONE Handler | 2Chat webhook or branch in Scenario 2 | Real-time | Processes "DONE-{BookingID}" messages from the manager to resume automation after manual intervention. Validates booking ID, confirms paused state, resets to PREFERENCES\_PARTIAL. |
| 4 | RLK \- Follow-up Flow | Scheduled | Daily at 2:00 AM | Finds guests in WAITING\_PREFERENCES or PREFERENCES\_PARTIAL state who have not replied. Sends up to 2 follow-up messages with varied phrasing. Escalates to HANDOVER\_HUMAN after 2 unanswered follow-ups. Skips guests with check-in less than 3 days away. |
| 5 | RLK \- Checkout Archive | Scheduled | Daily at 2:30 AM | Finds bookings where checkout date has passed. Notifies manager, marks CHECKED\_OUT, copies row to Archived Bookings, deletes from Active Bookings (only after confirming archive succeeded). |
| 6 | RLK \- Pending Response Sender | Scheduled | Every 15 minutes | Finds rows with queued nighttime messages where the scheduled send time has passed. Sends via 2Chat, clears the queue columns. |

### Scenario Architecture Note

Scenario 3 (DONE Handler) can be implemented as either a standalone scenario with its own webhook trigger, or as a branch inside Scenario 2\. The choice depends on the 2Chat plan's webhook limit. If the plan allows only one webhook URL, the DONE handler must be a branch inside Scenario 2 that filters by sender phone (manager) and message prefix ("DONE-"). If multiple webhooks are supported, a standalone scenario is cleaner.

---

## 6\. Sequence Diagrams

### Case 1: New Booking \- Happy Path (Phone Present)

### ![][image1]

### Case 2: New Booking \- Phone Missing

### ![][image2]

### Case 3: New Booking \- Group or Suite Missing

### ![][image3]

### Case 4: Guest Replies \- All Preferences in One Message

### ![][image4]

### Case 5: Guest Replies \- Partial Preferences (Multi-Turn)

### ![][image5]

### Case 6: Guest Sends Non-Text Message

### ![][image6]

### Case 7: Escalation \- Angry or Uninterpretable Guest

### ![][image7]

### Case 8: Manager Sends DONE to Resume Automation

### ![][image8]

### Case 9: Guest Does Not Reply \- Follow-Up Sequence

### ![][image9]

### Case 10: Checkout Archive

### ![][image10]

### Case 11: Nighttime Message Queue

### ![][image11]

### Case 12: End-to-End Lifecycle

![][image12]  
---

## 7\. Claude Prompt Design

Each Claude API call receives a structured prompt containing the guest context (name, suite, dates, current preferences, missing fields) and the full conversation history.

Claude is instructed to respond with a conversational message followed by a structured data block at the end of its response. This structured block is then parsed using regex patterns to extract state, preferences, and language.

Example of expected Claude output:

| Merci beaucoup\! Tout est noté pour votre séjour...STATE: PREFERENCES\_COMPLETEARRIVAL TIME: 15:00BED TYPE: doubleAIRPORT TRANSFER: noSPECIAL REQUESTS: baby cot neededLANGUAGE: fr |
| :---- |

The structured block must always be the last content in Claude's response. The parsing patterns extract:

- ConversationState (PREFERENCES\_PARTIAL, PREFERENCES\_COMPLETE, ISSUE\_DETECTED, HANDOVER\_HUMAN)  
- Each preference value (or NOT PROVIDED if not yet answered)  
- Detected language (only if changed from current)

The full system prompt, per-call context template, and parsing patterns will be maintained in the GitHub repository. Changes to prompts do not require modifying Make.com scenarios.

---

## 8\. Failure Modes

| Failure | Impact | Mitigation |
| :---- | :---- | :---- |
| Lodgify webhook has no phone number | Cannot contact guest | Row created with state \= PHONE\_MISSING. Manager alert with step-by-step instructions to manually add phone and resume. |
| Phone number in unexpected format | 2Chat rejects the message | Phone formatting logic cleans spaces, dashes, parentheses, handles Moroccan 06/07 prefix, ensures E.164 format before any send. |
| 2Chat message delivery fails | Guest never receives message | Retry with error handler (3 retries, 60s interval). On final failure, alert manager. If 2Chat returns 403/blocked status, alert manager that guest may have blocked the number. |
| 2Chat WhatsApp disconnects | All messaging stops | Scenario 9 (Orchestrator) will detect this. For Agent 1: error handlers on every 2Chat module alert the manager. |
| Claude API error or timeout | Conversation stalls | Retry with error handler (3 retries, 60s interval). On final failure, alert manager with raw guest message for manual handling. Log to System Log. |
| Claude returns response without structured block | Preferences not parsed | Regex parser returns empty ConversationState. Detected by filter. Fallback: set state to HANDOVER\_HUMAN, alert manager. |
| Duplicate webhook from 2Chat | Same message processed twice | Message ID stored in Column S. Scenario 2 checks incoming message ID against stored value. Duplicate is silently dropped. |
| Two rapid messages from same guest | Race condition on sheet row | Concurrency lock: Scenario 2 max simultaneous executions \= 1\. The second execution waits for the first to complete. |
| Guest sends image or voice note | Claude cannot process | Message type check before any processing. Alert manager to check 2Chat directly. |
| Unknown phone number sends message | No booking match | Guest lookup returns empty. The scenario stops silently. No reply sent to unknown numbers. |
| Guest replies after state is PREFERENCES\_COMPLETE | Unexpected message | State filter blocks automated processing. Message is ignored by the system. (Future improvement: acknowledge and direct to manager contact.) |
| Guest replies after state is HANDOVER\_HUMAN or ISSUE\_DETECTED | Bot should stay silent | State filter blocks processing. The manager is already handling it. |
| Guest replies during nighttime (10pm to 7am) | Message processed but response should be delayed | Response is generated but stored in Pending Response columns (Q, R). Scenario 6 sends it at 8am. Staff briefing still sends immediately regardless of time. |
| Booking exists in Archived tab | Re-booking or Lodgify sync artifact | Logged to System Log. Manager alerted. Row not re-created in Active. |
| Google Sheets API rate limit | Reads or writes fail | Unlikely at MVP volume. For scale: migration to database. |
| Manager sends malformed DONE message | Automation not resumed | DONE handler validates booking ID exists and is in a paused state. Send a clear error message back to the manager with the correct format. |

---

## 9\. Google Sheets vs. Database

Google Sheets is adequate for the MVP phase with a single property and low booking volume (under approximately 50 bookings per month). The owner benefits from direct spreadsheet visibility without needing a dashboard.

However, Sheets has real limitations: no transactions, no foreign keys, no concurrent write safety beyond the Make.com concurrency lock, API rate limits (60 requests per minute), and a 10 million cell cap. The Checkout Archive scenario (Scenario 5\) mitigates growth on the Active tab, but the Archived tab will grow indefinitely.

Migration triggers:

- Multiple properties sharing the system.  
- Volume exceeds approximately 200 bookings per month.  
- Conversation log or archived bookings tab exceeds thousands of rows.  
- Need for complex queries ("all guests checking in tomorrow who have not completed preferences" currently requires iterating all rows).

The migration path is clean because the Sheet structure is designed as flat, normalized tables. When the time comes: create PostgreSQL/Supabase tables matching the Sheet schema, replace Make.com Google Sheets modules with HTTP modules calling an API, optionally keep Sheets as a read-only sync for owner visibility.

To protect this migration path: do not use merged cells, conditional formatting as logic, or formulas that drive behavior.

---

## 10\. Acceptance Tests

All tests must pass in the staging environment before production deployment.

| \# | Test | Action | Expected Result |
| :---- | :---- | :---- | :---- |
| 1 | Complete normal flow | Create booking with phone. Reply with all 4 preferences. | Welcome arrives. Preferences saved. Confirmation sent. Bilingual briefing sent to manager and owner. State \= PREFERENCES\_COMPLETE. |
| 2 | Vague answer | Reply with ambiguous preferences ("maybe around 3pm, not sure about bed"). | State \= PREFERENCES\_PARTIAL. Follow-up asks only unanswered questions. No alert to manager. |
| 3 | Off-topic question | Reply with unrelated question ("Is there a rooftop terrace?"). | Yasmine answers question and steers back to preferences. State remains PREFERENCES\_PARTIAL. |
| 4 | Complaint / Issue | Reply with angry message. | State \= ISSUE\_DETECTED. Alert to manager and owner. No automated reply to guest. DONE handler works. |
| 5 | Ambiguous / Uninterpretable | Reply with single emoji or gibberish. | State \= HANDOVER\_HUMAN. Alert to manager. DONE handler works. |
| 6 | No reply / Follow-up | Create booking and do not reply. Trigger Scenario 4 manually. | Follow-up sent. After 2 unanswered, state \= HANDOVER\_HUMAN, manager alerted. |
| 7 | Mixed language | Reply in mixed French/English. | Yasmine responds in detected language. Preferences extracted correctly. |
| 8 | Duplicate booking | Change check-in date of existing booking in Lodgify. | Existing row updated. No duplicate row created. No second welcome message. |
| 9 | Personal message filter | Send WhatsApp from a number not in Google Sheet. | The scenario stops silently. No reply. No sheet changes. |
| 10 | Checkout archive | Set checkout date to yesterday. Trigger Scenario 5 manually. | Row archived, deleted from Active. Manager notified. |
| 11 | Error handling | Change Claude API URL to invalid endpoint. Create booking. | 3 retries. Error alert to manager. Entry in System Log. Fix URL, re-run, confirm recovery. |

---

## 11\. Questions for Stakeholder

Please fill in the "Answer" column and return this document before development begins.

| \# | Question | Context | Answer |
| :---- | :---- | :---- | :---- |
| 1 | What is the riad/property name? | Used in Yasmine's persona and all message templates. | **Riad Larbi Khalis** |
| 2 | What is Riad Larbi Khalis's WhatsApp sender number? | The number linked to 2Chat that guests will see messages from. Must be in E.164 format. |  |
| 3 | What is the manager's WhatsApp number? | All escalation alerts and staff briefings are sent to this fixed number. |  |
| 4 | What is the owner's WhatsApp number? | Briefings and critical alerts are also sent here. |  |
| 5 | How many suites/rooms does the property have? | Affects test data setup and booking scenarios. | **4 suites** |
| 6 | What are the suite/room names? | Used in greeting messages and briefings. |  |
| 7 | Is a 2Chat account already set up? | If not, this must be created and a WhatsApp number linked before development can begin. |  |
| 8 | Does the 2Chat plan allow multiple webhook URLs? | Determines whether the DONE Handler is a standalone scenario or a branch inside Scenario 2\. |  |
| 9 | Is there an existing Lodgify account with real bookings? | We need a separate test account for development. Do not test on the production account. |  |
| 10 | Which Lodgify community connector for Make.com will be used? | Three options: Codex Solutions, MAXMEL Tech, Synergetic. We must verify it supports a "new booking" trigger and returns phone numbers before purchasing. |  |
| 11 | What language should the first message use? | Sent before the guest replies, so we do not yet know their language. Options: always English, bilingual (English \+ French), or infer from guest country. |  |
| 12 | Should follow-ups respect guest timezone? | A message at 3 AM local time is a poor experience. If yes, we need timezone data from the booking. |  |
| 13 | What defines a "group booking"? | The client guide uses guests \> 2 as the threshold for manual handling. Is this correct? |  |
| 14 | Are there existing brand tone guidelines for Yasmine? | Affects prompt design. If none exist, we will define a warm, professional persona with language-specific register adjustments. |  |
| 15 | What nighttime hours should messages be queued? | The client guide uses 10 PM to 7 AM (Africa/Casablanca). Confirm or adjust. |  |

# \[DRAFT\] Agent 3 \- Upsell

# E-Conciergerie \- Agent 3 (Upsell): Development Proposal

**Version:** 1.2   
**Date:** March 24, 2026   
**Property:** Riad Larbi Khalis (4 suites)   
**Status:** Draft \- Pending Stakeholder Input   
**Dependency:** Requires Agent 1 \+ 2 to be fully operational before deployment.

---

## 1\. Goal

During each guest's stay at Riad Larbi Khalis, the system sends well-timed WhatsApp messages through Yasmine proposing paid services: breakfast, hammam, excursions, dinners, and other experiences. The messages are warm, personal, written in the guest's detected language, and never pushy.

When a guest accepts an offer, the manager receives an instant WhatsApp notification with the guest name, suite, accepted offer, and any relevant details. Every proposal and response is logged in the Upsell Log tab for revenue tracking.

The system must respect the guest's stay duration (shorter stays receive fewer offers), must never send upsell messages before Agent 1 has completed preference collection, and must never re-offer something the guest has already declined.

---

## 2\. Prerequisites

All Agent 1 prerequisites must already be in place. The following are additional requirements specific to Agent 3\.

| \# | Requirement | Purpose | Notes |
| :---- | :---- | :---- | :---- |
| 1 | Agent 1 \+ 2 fully operational | Agent 3 only activates for guests whose state is PREFERENCES\_COMPLETE. If Agent 1 is not working, Agent 3 has no guests to upsell. | Hard dependency. |
| 2 | Offer catalog defined by stakeholder | The system needs a concrete list of services with names, descriptions, and prices in MAD. Without this, no messages can be generated. | See Questions for Stakeholder, Section 10\. |
| 3 | Upsell timing rules confirmed | Which offers are sent at which point during the stay. The proposal includes a default schedule, but the stakeholder must confirm or adjust. | See Questions for Stakeholder, Section 10\. |
| 4 | No new tool accounts required | Agent 3 uses the same Make.com, 2Chat, Claude, and Google Sheets infrastructure as Agent 1\. No additional integrations. |  |

---

## 3\. Scope

### In Scope

- Maintain an offer catalog in a dedicated Google Sheets tab (editable by the owner).  
- Calculate a per-guest upsell schedule based on check-in date, check-out date, and stay duration.  
- Send timed upsell messages via 2Chat in the guest's detected language.  
- Handle guest replies to upsell offers: accept, decline, or ask a question.  
- On acceptance: instantly notify the manager via WhatsApp with actionable details.  
- On decline: mark the offer as declined, never re-offer the same service to that guest.  
- On question or unclear reply: answer naturally as Yasmine and re-present the offer.  
- Log every upsell proposal, response, and outcome in the Upsell Log tab.  
- Respect nighttime hours (reuse Agent 1's queuing logic: no messages between 10pm and 7am).  
- Skip upsell messages for guests in escalated or handover states.  
- Adapt the number of offers to stay duration (1-night stays receive fewer offers than week-long stays).

### Out of Scope

- Payment processing (the guest pays on-site, not through the system).  
- Automated calendar or resource booking for accepted services (manager handles this manually after notification).  
- Upsell revenue reporting (this is Agent 7's responsibility, reading from the Upsell Log tab that Agent 3 populates).

### Dependency on Agent 1

| Dependency | Detail |
| :---- | :---- |
| Guest record | Agent 3 reads guest name, suite, check-in, check-out, and language from the Active Bookings tab populated by Agent 1\. |
| Conversation state | Agent 3 only targets guests with state \= PREFERENCES\_COMPLETE. If Agent 1 has not finished, Agent 3 does not send. |
| Reply routing | Upsell replies arrive on the same 2Chat webhook as Agent 1 replies. Routing is state-based: if state is PREFERENCES\_COMPLETE and there is an active upsell offer pending, the reply is routed to Agent 3 logic. |
| Nighttime queue | Agent 3 reuses the same Pending Response columns (Q, R) and Scenario 6 (Pending Response Sender) from Agent 1\. |
| Yasmine persona | Agent 3 uses the same Yasmine persona and prompt style as Agent 1 for consistency. The guest should not notice a difference. |

---

## 4\. Data Model (Google Sheets)

### New Tab: Offer Catalog

A simple reference table maintained by the owner. Each row is one service that can be proposed to guests.

Key columns: offer ID, offer name (French), offer name (English), offer name (Arabic), description, price in MAD, timing rule (which day of stay to propose), active flag (yes/no to temporarily disable an offer without deleting it).

The timing rule column uses a simple format relative to check-in: "day \-1" means the evening before arrival, "day 2" means the morning of the second day, "day mid" means the middle of the stay (calculated), "day last" means the last evening.

### New Tab: Upsell Log

One row per upsell interaction. Populated automatically by the system.

Key columns: log ID, booking ID, guest name, suite, offer ID, offer name, price, message sent timestamp, guest response, response timestamp, outcome (accepted / declined / no response / question), manager notified (yes/no), notes.

### Modifications to Active Bookings Tab

Two new columns added:

- **Current Upsell Offer** (Column T): The offer ID of the most recently sent upsell. Used by the reply handler to know which offer the guest is responding to. Cleared after the guest responds or after a timeout.  
- **Upsell Phase** (Column U): Tracks where the guest is in the upsell schedule. Values: NOT\_STARTED, IN\_PROGRESS, COMPLETED, SKIPPED. Set to NOT\_STARTED when Agent 1 completes preferences. Set to COMPLETED when all applicable offers for the stay duration have been sent. Set to SKIPPED if the stay is too short or the guest is in an escalated state.

---

## 5\. Make.com Scenarios

Two new scenarios plus a modification to an existing one.

| \# | Scenario Name | Trigger | Schedule | Purpose |
| :---- | :---- | :---- | :---- | :---- |
| 7 | RLK \- Upsell Scheduler | Scheduled | Daily at 9:00 AM and 6:00 PM | Scans Active Bookings for guests currently staying (check-in passed, check-out not passed, state \= PREFERENCES\_COMPLETE). For each guest, checks the Offer Catalog against the current day of stay. If an offer matches today, generates a Yasmine message via Claude and sends it. Logs to Upsell Log. |
| 8 | RLK \- Upsell Reply Handler | Branch in Scenario 2 | Real-time | When a guest reply arrives and state \= PREFERENCES\_COMPLETE and Column T (Current Upsell Offer) is not empty, the reply is routed to Agent 3 logic instead of Agent 1\. Claude classifies the reply as acceptance, decline, or question. On acceptance: notify manager, log outcome. On decline: log, clear current offer. On question: answer and re-present. |

### Modification to Existing Scenario

**Scenario 2 (RLK \- Incoming Replies):** A new Router branch is added. After the state filter, before the existing 4-branch Router, a check is added: if state \= PREFERENCES\_COMPLETE AND Column T is not empty, route to Agent 3 upsell reply logic. Otherwise, continue to existing Agent 1 branches.

---

## 6\. Sequence Diagrams

### Case 1: Upsell Scheduler \- Morning Run (Happy Path)

The scheduler runs at 9 AM, finds a guest on Day 2 of their stay, and sends a breakfast offer.

![][image13]

### Case 2: Upsell Scheduler \- Evening Run

Same logic but runs at 6 PM. Targets offers with "day \-1" (evening before arrival) and "day last" (last evening) timing rules.

![][image14]

### Case 3: Guest Accepts an Offer

Guest replies "yes" to an upsell message. Manager is notified instantly.

![][image15]

### Case 4: Guest Declines an Offer

![][image16]

### Case 5: Guest Asks a Question About the Offer

![][image17]

### Case 6: Guest Does Not Reply to Offer

![][image18]

### Case 7: Short Stay Logic

![][image19]

### Case 8: Reply During Active Upsell vs No Active Upsell

This shows the routing logic inside Scenario 2 that distinguishes Agent 1 replies from Agent 3 replies.

![][image20]

---

## 7\. Offer Timing Logic

The scheduler calculates the "day of stay" for each guest and matches it against the timing rules in the Offer Catalog. The system runs twice daily (9 AM and 6 PM) to cover both morning and evening offers.

### Default Timing Schedule

| Timing Rule | When It Fires | Typical Offers | Minimum Stay to Qualify |
| :---- | :---- | :---- | :---- |
| day \-1 | 6 PM, the evening before check-in | Breakfast for first morning, airport transfer reminder | 1 night |
| day 1 | 9 AM, morning of arrival day (if check-in is before noon) | Welcome drink, spa introduction | 2 nights |
| day 2 | 9 AM, second morning | Hammam session, city excursion | 2 nights |
| day mid | 9 AM, calculated middle day of stay | Cooking class, desert excursion, special experience | 4 nights |
| day last | 6 PM, the evening before check-out | Special farewell dinner, late checkout | 2 nights |

### Calculation Rules

- Day of stay \= today minus check-in date plus 1 (check-in day \= day 1).  
- "day mid" \= floor((check-out minus check-in) / 2\) \+ 1\. For a 4-night stay: day mid \= day 3\. For a 7-night stay: day mid \= day 4\.  
- An offer is only eligible if the guest's total stay duration meets the minimum nights requirement.  
- Maximum one upsell message per guest per day. If multiple offers match the same day, prioritize by offer order in the catalog (first row wins).  
- Never send an offer that has already been sent to the same guest (check Upsell Log).  
- Never send an offer that the guest has already declined.

---

## 8\. Reply Mechanism

### 2Chat Connection Modes and Their Impact

2Chat supports two WhatsApp connection modes, and the choice directly affects how guests interact with upsell messages.

**WhatsApp Web (QR Code Connection)**

This is the mode described in the client's implementation guide. A regular WhatsApp number is linked to 2Chat by scanning a QR code. The send-message API only supports plain text and media attachments. There is no support for interactive buttons, quick reply buttons, list messages, or message templates. This means all guest replies to upsell offers are free-form text that Claude must classify.

Additional limitations: the connection drops if the linked phone goes offline for extended periods, WhatsApp may flag or ban the number for automated commercial messaging without using the official Business API, and there is no message template approval process (which means no proactive outbound messaging guarantee outside the 24-hour conversation window).

**WhatsApp Business API (WABA)**

This is the official Meta-certified path. 2Chat is a Meta-certified provider and supports WABA connections alongside the QR code method. WABA unlocks interactive message features including quick reply buttons (up to 3 per message), list messages (up to 10 options), message templates with button components, and stable server-to-server connections with no phone dependency.

With WABA, each upsell message can include structured reply buttons:

| \[Yasmine's warm message about breakfast\]\[ Yes please \]  \[ No thanks \]  \[ Tell me more \] |
| :---- |

The guest taps a button. The reply is unambiguous. No Claude classification needed for basic accept/decline. Claude is only called if the guest taps "Tell me more" or sends a free-text reply instead of using buttons. This eliminates misclassification, reduces Claude API costs, and provides a better guest experience.

### Recommendation

**We recommend using WABA for production.** The QR code connection is acceptable for initial development and testing, but the system should migrate to WABA before going live with real guests. The reasons are:

- Interactive buttons make the upsell flow significantly more reliable (no ambiguity in accept/decline).  
- Stable connection with no phone dependency (the QR code connection will eventually disconnect and break the entire system until someone re-scans).  
- Compliance with WhatsApp's commercial messaging policies (automated messaging from a QR-linked personal number violates WhatsApp Terms of Service and risks account ban).  
- Message templates allow proactive outbound messaging beyond the 24-hour window.  
- No provider change needed: 2Chat supports both modes, so the migration is a configuration change within 2Chat, not a platform switch.

The WABA setup requires Meta Business verification (1 to 2 weeks), a dedicated phone number, and template approval for outbound messages. This lead time should be factored into the project timeline.

### MVP Approach (QR Code Connection)

For development and initial testing, the system uses free-text replies only. Claude classifies every guest reply into one of six categories.

### Production Approach (WABA)

Once migrated to WABA, upsell messages include quick reply buttons. The classification logic remains as a fallback for cases where the guest types a free-text reply instead of tapping a button, or sends a message that goes beyond the button options (custom requests, questions, unrelated messages).

### Reply Classification: All Possible Cases

Regardless of connection mode, a guest reply to an upsell message falls into one of six categories. Claude must be able to distinguish all six.

| Classification | Example Replies | System Response |
| :---- | :---- | :---- |
| ACCEPTED | "Yes please", "Breakfast for two at 9am", "Sure\!", thumbs up emoji, or button tap: "Yes please" | Yasmine confirms. Manager notified instantly with details. Logged as accepted. |
| DECLINED | "No thank you", "We already have plans", "Not interested", or button tap: "No thanks" | Yasmine acknowledges gracefully. Logged as declined. Offer never re-sent. |
| QUESTION | "How much is it?", "What time?", "Is the hammam private?", or button tap: "Tell me more" | Yasmine answers the question using offer details from the catalog, then re-presents the offer. Column T stays set (offer still active). |
| DIFFERENT\_REQUEST | "Can I get a massage instead?", "Do you offer cooking classes?", "What about a guided tour?" | Yasmine acknowledges the interest. Manager notified as a custom request opportunity. If the requested service exists in the Offer Catalog, Yasmine can propose it. If not, forward to manager. |
| ACCEPT\_AND\_MORE | "Yes breakfast, and can you also book a hammam?" | Yasmine confirms the original offer. Manager notified for the accepted offer. The additional request is treated as a DIFFERENT\_REQUEST and either matched to the catalog or forwarded to manager. |
| UNRELATED | "What time is checkout?", "Where is the nearest pharmacy?", "Can you call us a taxi?" | Yasmine answers the question naturally. The upsell offer is not re-presented (the guest clearly moved on). Column T is cleared. No upsell outcome logged (treated as if the offer expired). |

### What Happens When the Guest Wants Something Not on the Menu

This is a revenue opportunity, not an edge case. If a guest asks for something that is not in the Offer Catalog, the system should:

1. Yasmine responds warmly: "Great idea\! Let me check with our team and get back to you."  
2. Manager receives an instant alert: "CUSTOM REQUEST from \[guest name\] in \[suite\]: \[what they asked for\]. Please respond directly."  
3. The request is logged in the Upsell Log with outcome \= "custom\_request".  
4. Column T is cleared (the original offer is no longer active).  
5. The manager handles the request manually and can fulfill it outside the automated system.

This ensures no buying intent is lost, even when the guest goes off-script.

---

## 9\. Claude Prompt Design

Two distinct Claude calls are used in Agent 3\.

**Upsell Message Generation (Scenario 7):** Claude receives the guest context (name, language, suite, day of stay, stay duration) and the offer details (name, description, price). It generates a warm, non-pushy Yasmine message in the guest's language. The prompt explicitly instructs Claude to never sound like an advertisement, to weave the offer naturally into a check-in on the guest's experience, and to keep messages under 200 characters. On WABA, Make.com appends interactive buttons to the message after Claude generates the body text.

**Reply Classification (Scenario 2, Agent 3 branch):** Claude receives the guest's reply, the current offer context, the full Offer Catalog (so it can match alternative requests), and conversation history. It returns a structured block:

| UPSELL*\_ACTION: ACCEPTED / DECLINED / QUESTION / DIFFERENT\_*REQUEST / ACCEPT*\_AND\_*MORE / UNRELATEDDETAILS: \[specifics extracted, e.g., "2 persons, 9am"\]ALTERNATIVE\_OFFER: \[offer ID from catalog if guest requested something else, or NONE\]CUSTOM\_REQUEST: \[free text description if guest asked for something not in catalog, or NONE\]RESPONSE\_MESSAGE: \[Yasmine's reply to the guest\] |
| :---- |

Same parsing approach as Agent 1 (structured block at the end of Claude's response, extracted via regex).

All prompts are maintained in the GitHub repository.

---

## 10\. Failure Modes

| Failure | Impact | Mitigation |
| :---- | :---- | :---- |
| Offer Catalog is empty or all offers inactive | Scheduler finds nothing to send | Scheduler logs a warning to System Log. No guest impact. |
| Guest replies to upsell but Column T was already cleared (timeout race condition) | Reply cannot be matched to an offer | Treat as a general message. Acknowledge politely and suggest contacting the manager directly. |
| Guest accepts but manager misses the notification | Service not prepared | Alert is sent to both manager and owner. If 2Chat delivery fails, error handler retries and logs to System Log. |
| Claude classifies a clear "yes" as QUESTION or vice versa | Wrong response sent to guest | Prompt design minimizes this. Upsell Log captures the raw reply for manual review. Manager can always check the log. |
| Scheduler runs but guest checked out between calculation and send | Message sent after checkout | Scheduler checks check-out date strictly: check\_out must be greater than today (not equal). Guest on checkout day does not receive morning offers. |
| Two offers match the same day | Guest receives two messages in one day | One-message-per-day rule enforced: scheduler sends only the first matching offer, skips the rest for the next day. |
| Guest declines all offers | Repeated messages feel pushy | Once all eligible offers for the stay are sent or declined, upsell\_phase is set to COMPLETED. No further messages. |
| Guest requests something not in catalog | Potential revenue lost if not captured | DIFFERENT\_REQUEST classification triggers manager alert with the request details. Logged as custom\_request for tracking. |
| Guest accepts and requests more in one message | Second request missed | ACCEPT\_AND\_MORE classification handles both: confirms the accepted offer and routes the additional request to manager or catalog match. |
| 2Chat does not support interactive buttons | Free-text ambiguity increases | Fall back to Option B (free-text classification). Claude handles all classification. Prompt must be robust against ambiguous replies like "sounds nice" or "maybe." |
| Guest sends reply in different language than upsell was sent in | Classification may fail | Claude prompt includes instruction to handle multilingual replies. Language detection from Agent 1 is used as context but Claude adapts to whatever language the reply is in. |

---

## 11\. Questions for Stakeholder

Please fill in the "Answer" column and return this document before development begins.

| \# | Question | Context | Answer |
| :---- | :---- | :---- | :---- |
| 1 | What services does Riad Larbi Khalis offer for upsell? | We need the complete list: name, description, and price in MAD for each. Examples: breakfast, hammam, airport transfer, city excursion, cooking class, dinner on the terrace, desert trip. This is a hard blocker. |  |
| 2 | Are prices fixed or do they vary by season? | If seasonal, we need a price per season or a mechanism for the owner to update prices in the Offer Catalog tab. |  |
| 3 | Can the owner add or remove offers without developer involvement? | The proposed design uses a Google Sheets tab as the catalog. The owner can edit it directly. Confirm this is acceptable. |  |
| 4 | What is the maximum number of upsell messages per guest per stay? | Proposed default: one message per day, maximum 4-5 per stay depending on duration. Confirm or adjust. |  |
| 5 | Should very short stays (1 night) receive any upsell at all? | Proposed: yes, but limited to 1 offer maximum (e.g., breakfast for the morning). Confirm or adjust. |  |
| 6 | Is the proposed timing schedule correct? | See Section 7 (Offer Timing Logic). The default is: evening before arrival, morning of day 2, mid-stay, last evening. Confirm or adjust. |  |
| 7 | What happens when a guest accepts? | Does the manager prepare the service manually, or is there a booking system that needs to be updated? This affects whether the manager notification alone is sufficient. |  |
| 8 | Should the owner also receive upsell acceptance notifications? | Agent 1 sends briefings to both manager and owner. Should upsell acceptances follow the same pattern? |  |
| 9 | Is there a breakfast time or hammam schedule the system should know about? | If Yasmine needs to suggest available time slots, she needs this information in the Offer Catalog or in the prompt. |  |
| 10 | Are there any offers that require advance booking (e.g., desert excursion needs 24h notice)? | Affects timing rules: an excursion that needs 24h notice cannot be offered on the last evening. |  |
| 11 | Should declined offers ever be re-offered later in the stay? | Proposed: no. Once declined, marked permanently for that guest. Confirm or adjust. |  |
| 12 | What language should upsell messages use for guests whose language was not detected? | Proposed: same default as Agent 1 (see Agent 1 proposal, Question 11). |  |
| 13 | What tone is acceptable for upsell? | Proposed: Yasmine weaves the offer into a natural check-in on the guest's experience. Never a sales pitch, never bullet points, never "limited offer." Confirm or provide tone guidance. |  |
| 14 | Is the client willing to migrate to 2Chat WABA (WhatsApp Business API) before go-live? | The current QR code connection does not support interactive buttons and risks account ban for automated messaging. WABA is the official path: stable connection, interactive buttons for upsell, message templates, and compliance with WhatsApp policies. Requires Meta Business verification (1 to 2 weeks). We recommend WABA for production. The QR code method is acceptable for development and testing only. |  |
| 15 | How should custom requests be handled? | When a guest asks for something not on the menu (e.g., "Can you arrange a private driver?"), the proposed behavior is: Yasmine says "Let me check with our team," manager receives the request instantly, manager fulfills manually. Is this acceptable, or should there be a different workflow? |  |
| 16 | Can a guest proactively request a service without being offered first? | For example, on day 1 the guest messages "Can we book the hammam?" before any upsell was sent. Should the system recognize this as an upsell opportunity and process it, or should it always go to the manager? |  |

# \[DRAFT\] Agent 7 & 8 \- Reporting

# E-Conciergerie \- Agent 7 \+ 8 (Monthly Report \+ Accounting): Development Proposal

**Version:** 1.0   
**Date:** March 24, 2026   
**Property:** Riad Larbi Khalis (4 suites)  
 **Status:** Draft \- Pending Stakeholder Input   
**Dependency:** Requires Agent 1 \+ 2 to be operational. Agent 3 (Upsell) should be operational for complete upsell revenue tracking. The system functions without Agent 3 but upsell metrics will be empty.

---

## 1\. Goal

Automate the financial record-keeping and reporting for Riad Larbi Khalis. Every financial event (booking revenue, upsell sales, and operational expenses) is logged in real time to a structured Google Sheets tab. The owner receives a weekly financial snapshot every Monday morning and a formatted Excel export every month. On the 1st of every month, the owner also receives a complete business performance summary covering bookings, revenue, upsell income, occupancy rate, and suite performance.

The owner should never need to open a spreadsheet to understand how the business is performing. Everything arrives on WhatsApp.

**Important scope note:** This system provides bookkeeping assistance (transaction logging, summaries, and formatted exports). It does not perform accounting functions such as reconciliation, tax calculation, VAT handling, or regulatory compliance. The monthly Excel export is designed to be forwarded to a professional accountant, not to replace one.

---

## 2\. Prerequisites

All Agent 1 prerequisites must already be in place. The following are additional requirements specific to Agent 7 \+ 8\.

| \# | Requirement | Purpose | Notes |
| :---- | :---- | :---- | :---- |
| 1 | Agent 1 \+ 2 fully operational | Booking data in the Active Bookings and Archived Bookings tabs is the primary source of revenue data. | Hard dependency. |
| 2 | Agent 3 (Upsell) operational or planned | Upsell revenue is read from the Upsell Log tab populated by Agent 3\. Without Agent 3, the upsell section of reports will be empty but the system still functions. | Soft dependency. |
| 3 | Expense categories defined by stakeholder | The manager needs a predefined list of valid expense categories to log against. Without this, expense data is unstructured and unusable for reporting. | See Questions for Stakeholder, Section 10\. |
| 4 | Accountant's preferred Excel format (if any) | If the accountant expects a specific layout, we need the template before building the export. | Optional. If none provided, we define a standard format. |
| 5 | No new tool accounts required | Agent 7 \+ 8 uses the same Make.com, 2Chat, Claude, and Google Sheets infrastructure as Agent 1\. |  |

---

## 3\. Scope

### In Scope

**Agent 8 (Accounting):**

- Automatically log booking revenue when Agent 1 creates or updates a booking (extracted from Lodgify booking data).  
- Automatically log upsell revenue when Agent 3 records an accepted upsell (read from Upsell Log tab).  
- Accept manual expense entries from the manager via WhatsApp message in a defined format.  
- Validate and categorize each expense entry before logging.  
- Send a confirmation to the manager after each expense is logged.  
- Send a weekly financial snapshot to the owner every Monday at 8 AM (total revenue, total expenses, net for the week).  
- Generate a formatted Excel file on the 1st of every month with all transactions from the previous month.  
- Send the Excel download link to the owner via WhatsApp.

**Agent 7 (Monthly Report):**

- Run on the 1st of every month at 8 AM.  
- Read from the Accounting tab, Upsell Log tab, Active Bookings tab, and Archived Bookings tab.  
- Calculate key metrics: total bookings, total revenue (gross), upsell revenue, total expenses, net revenue, average occupancy rate, best performing suite.  
- Format into a clean, readable WhatsApp message using Claude.  
- Send it to the owner.

### Out of Scope

- Tax calculation, VAT handling, or any regulatory compliance logic.  
- Bank reconciliation or payment verification.  
- Multi-currency conversion (all amounts logged in MAD).  
- Platform commission calculation (see Questions for Stakeholder).  
- Invoice generation.  
- Integration with external accounting software (QuickBooks, Xero, etc.).

### Dependency on Other Agents

| Agent | Dependency |
| :---- | :---- |
| Agent 1 \+ 2 | Provides booking data (guest name, suite, dates, booking source) that Agent 8 uses to log revenue entries. |
| Agent 3 (Upsell) | Populates the Upsell Log tab. Agent 8 reads accepted upsells to calculate upsell revenue. Without Agent 3, upsell metrics are zero. |
| Agent 9 (Orchestrator) | Will monitor Agent 7 \+ 8 scenarios for failures. The System Log entries written by Agent 7 \+ 8 support this future monitoring. |

---

## 4\. Data Model (Google Sheets)

### New Tab: Accounting

One row per financial transaction. Populated automatically (bookings, upsells) and manually (expenses via WhatsApp).

Key columns: transaction ID, date, type (booking\_revenue / upsell\_revenue / expense), category (accommodation, breakfast, hammam, excursion, cleaning, food, maintenance, staff, utilities, other), description, amount in MAD, suite (if applicable), booking ID (if applicable), source (lodgify / upsell\_log / manager\_input), recorded\_at timestamp.

### Existing Tab: Upsell Log

Already defined in Agent 3 proposal. Agent 8 reads rows where outcome \= "accepted" to extract upsell revenue. No modifications needed.

### Existing Tab: Active Bookings and Archived Bookings

Already defined in Agent 1 proposal. Agent 7 reads these to calculate occupancy and booking counts. No modifications needed.

### New Column in Active Bookings

- **Revenue Logged** (Column V): "yes" or "no". Tracks whether Agent 8 has already created an Accounting entry for this booking. Prevents duplicate revenue logging if the booking row is updated multiple times.

---

## 5\. Make.com Scenarios

Four new scenarios.

| \# | Scenario Name | Trigger | Schedule | Purpose |
| :---- | :---- | :---- | :---- | :---- |
| 9 | RLK \- Revenue Logger | Scheduled | Every 4 hours | Scans Active Bookings and Upsell Log for new entries not yet logged to the Accounting tab. Creates Accounting rows for new booking revenue and accepted upsells. Marks entries as logged to prevent duplicates. |
| 10 | RLK \- Expense Input | 2Chat webhook or branch in Scenario 2 | Real-time | Processes WhatsApp messages from the manager that match the expense format. Parses amount, category, and description. Validates and logs to Accounting tab. Sends confirmation back to manager. |
| 11 | RLK \- Weekly Snapshot | Scheduled | Every Monday at 8:00 AM | Reads Accounting tab for the past 7 days. Calculates total revenue, total expenses, and net. Formats summary via Claude. Sends to owner via WhatsApp. |
| 12 | RLK \- Monthly Report and Export | Scheduled | 1st of every month at 8:00 AM | Reads all data from previous month across Accounting, Upsell Log, Active Bookings, and Archived Bookings. Calculates all metrics. Generates formatted Excel file. Sends WhatsApp summary (Agent 7\) and Excel download link (Agent 8\) to owner. |

---

## 6\. Sequence Diagrams

### Case 1: Automatic Revenue Logging (Booking)

![][image21]

### Case 2: Automatic Revenue Logging (Upsell)

![][image22]

### Case 3: Manager Submits an Expense via WhatsApp

![][image23]

### Case 4: Manager Submits Expense with Wrong Format

![][image24]

### Case 5: Weekly Snapshot (Every Monday)

![][image25]

### Case 6: Monthly Report and Excel Export (1st of Month)

![][image26]

### Case 7: Monthly Report \- Occupancy Calculation Detail

![][image27]

---

## 7\. Expense Input Design

### Message Format

The manager sends expenses via WhatsApp to the riad number. The system recognizes expense messages by a keyword prefix.

**Recommended format:**

expense \[amount\] \[category\] \[description\]

**Examples:**

expense 1200 food weekly market supplies  
expense 350 cleaning laundry service  
expense 5000 staff monthly salary Fatima  
expense 800 maintenance plumber fixed bathroom leak  
expense 200 utilities water bill March

**Valid categories** (must be confirmed by stakeholder): cleaning, food, maintenance, staff, utilities, other

### Flexible Parsing

Claude is used to parse expense messages, not rigid regex. This means the manager does not need to follow the exact format perfectly. Claude can handle variations:

- "expense 500 food market" (standard format)  
- "paid 500 for market supplies" (natural language)  
- "dépense 1200 nourriture marche" (French)  
- "1200 MAD cleaning" (no keyword prefix, amount first)

Claude extracts the amount, infers the category from the description, and asks for confirmation when the intent is ambiguous. If Claude cannot determine the amount or the message is clearly not an expense, it replies with the format instructions.

### Confirmation and Correction

After logging an expense, the system sends a confirmation message. If Claude inferred the category (rather than matching it exactly), the confirmation includes "Is this correct? Reply NO to cancel." If the manager replies NO within 5 minutes, the entry is deleted from the Accounting tab.

### Routing

Expense messages arrive on the same 2Chat webhook as guest replies. The routing logic in Scenario 2 already filters by sender phone number. Since the manager's phone is a fixed known number, a new branch checks: if sender \= manager phone AND message is not a "DONE-" command AND message appears to be an expense entry, route to Scenario 10 (Expense Input) logic.

If the expense handler is built as a branch inside Scenario 2, no additional webhook is needed. If built as a standalone scenario, it requires either a separate webhook URL (depends on 2Chat plan) or a Make.com HTTP call from Scenario 2 to Scenario 10's webhook.

---

## 8\. Excel Export Design

The monthly Excel file contains four sheets:

**Sheet 1: Summary**

- Month and year  
- Total bookings count  
- Total revenue (gross)  
- Total upsell revenue  
- Total expenses  
- Net revenue  
- Occupancy rate  
- Revenue by suite (table)  
- Expenses by category (table)

**Sheet 2: Revenue**

- All booking\_revenue rows from Accounting tab for the month  
- Columns: date, guest name, suite, amount, booking ID, source

**Sheet 3: Upsell**

- All upsell\_revenue rows from Accounting tab for the month  
- Columns: date, guest name, suite, offer name, amount, booking ID

**Sheet 4: Expenses**

- All expense rows from Accounting tab for the month  
- Columns: date, category, description, amount, entered by

The file is named: `RLK-[YYYY]-[MM]-monthly-export.xlsx`

Make.com can generate Excel files using the Google Sheets "Export" module (export to xlsx) or using a dedicated Excel creation module. The simplest approach is to maintain a template Google Sheet that the scenario populates with the month's data, then exports as xlsx and sends the download link.

---

## 9\. Claude Prompt Design

Three distinct Claude calls are used in Agent 7 \+ 8\.

**Expense Parsing (Scenario 10):** Claude receives the manager's WhatsApp message and the list of valid expense categories. It returns a structured block:

EXPENSE\_VALID: YES / NO  
AMOUNT: \[number\]  
CATEGORY: \[category from allowed list\]  
DESCRIPTION: \[cleaned description\]  
INFERRED: YES / NO  
RESPONSE\_MESSAGE: \[confirmation or error message to send to manager\]  
If INFERRED \= YES, the confirmation message includes a correction prompt.

**Weekly Snapshot (Scenario 11):** Claude receives the raw numbers (revenue, expenses, net) and formats them into a clean, concise WhatsApp message. The prompt instructs Claude to keep it under 500 characters, highlight the most important number, and use a warm but professional tone.

**Monthly Report (Scenario 12):** Claude receives all calculated metrics and formats a comprehensive but readable WhatsApp summary. The prompt instructs Claude to organize by section (bookings, revenue, upsell, expenses, occupancy), highlight month-over-month trends if data is available, and keep the total message under 2000 characters.

All prompts are maintained in the GitHub repository.

---

## 10\. Failure Modes

| Failure | Impact | Mitigation |
| :---- | :---- | :---- |
| Booking has no price data from Lodgify | Revenue logged as zero or missing | Revenue Logger checks for empty amount. If empty, logs to System Log and skips. Does not create a zero-revenue entry. |
| Manager sends expense in wrong format | Expense not logged | Claude attempts flexible parsing. If it fails, sends format instructions back to manager with examples. No data is lost because nothing was written. |
| Manager sends expense with amount in wrong currency or with text mixed in ("1.200 MAD") | Amount parsed incorrectly | Claude is instructed to handle common number formats: comma as thousands separator (1.200 \= 1200 in Moroccan/European format), "MAD" suffix stripped, spaces ignored. |
| Duplicate revenue logging | Revenue double-counted | Revenue Logged column (V) in Active Bookings prevents duplicates. Upsell Log entries are matched by log ID to prevent re-logging. |
| Monthly export runs but Accounting tab is empty | Empty Excel sent to owner | Scenario checks row count before generating. If zero transactions, sends a message: "No transactions recorded for \[month\]. Please verify." |
| Scenario 12 runs on the 1st but some bookings span the month boundary | Revenue attributed to wrong month | Revenue is attributed by transaction date in the Accounting tab, not by booking check-in date. The Revenue Logger records revenue when the booking is created, which is when Lodgify confirms it. |
| Manager replies NO to cancel an expense but more than 5 minutes passed | Cancellation window expired | System informs manager the window has closed. Manager can manually delete the row in Google Sheets or ask for a correction entry. |
| Google Sheets export to xlsx fails | Owner does not receive monthly file | Error handler retries once. On second failure, sends raw data summary via WhatsApp and alerts to System Log. |
| Occupancy calculation includes cancelled bookings | Inflated occupancy rate | Occupancy query filters: only bookings where state is not CANCELLED. Cancelled bookings are excluded from room-night calculations. |

---

## 11\. Occupancy Rate Calculation

Occupancy rate is the single most scrutinized metric in the monthly report. The calculation must be precise and defensible.

**Formula:**

Occupancy Rate \= (Booked Room-Nights / Available Room-Nights) x 100

**Available Room-Nights:** Number of suites (4) multiplied by number of days in the month. For March: 4 x 31 \= 124 available room-nights.

**Booked Room-Nights:** For each non-cancelled booking whose stay overlaps with the month, count only the nights that fall within the month. A booking that checks in on March 28 and checks out on April 2 contributes 3 nights to March (March 28, 29, 30\) and 2 nights to April (March 31 is checkout day, not counted as a night).

**Exclusions:**

- Cancelled bookings are excluded.  
- Blocked dates (maintenance, owner personal use) reduce available room-nights if tracked. Currently not tracked in the data model. See stakeholder question.

**Edge case:** A suite blocked for renovation for 10 days in a 31-day month means that suite contributes 21 available nights, not 31\. Without tracking blocked dates, the occupancy rate will appear lower than reality during maintenance periods.

---

## 12\. Google Sheets vs. Database

The same principles from the Agent 1 proposal apply. The Accounting tab will grow continuously (approximately 50-100 rows per month for a 4-suite riad). At this rate the tab reaches 1,200 rows per year, which is well within Sheets limits.

However, the monthly report calculation (filtering by date range, aggregating by category, calculating occupancy across overlapping booking dates) becomes increasingly slow in Sheets as data accumulates. After 2-3 years (3,000+ rows), these calculations may start to hit performance limits in Make.com's Google Sheets modules.

The migration path remains the same: PostgreSQL/Supabase with SQL aggregation queries that replace the Sheets iteration logic.

---

## 13\. Questions for Stakeholder

Please fill in the "Answer" column and return this document before development begins.

| \# | Question | Context | Answer |
| :---- | :---- | :---- | :---- |
| 1 | What expense categories should the system support? | The manager will use these when logging expenses. Proposed default: cleaning, food, maintenance, staff, utilities, other. Confirm or provide the actual list. |  |
| 2 | In what language will the manager send expense messages? | Affects Claude's parsing prompt. French, Arabic, English, or mixed? |  |
| 3 | Are booking amounts in Lodgify gross or net of platform commission? | Airbnb takes approximately 3%, Booking.com takes 15-25%. If Lodgify stores the gross amount, the system logs gross. If the client wants net revenue, we need commission rates per platform. |  |
| 4 | Should platform commission be tracked as a separate expense or deducted from revenue? | Option A: Log gross revenue, log commission as an expense (cleaner audit trail). Option B: Calculate and log net revenue directly (simpler but less transparent). |  |
| 5 | Does the accountant have a preferred Excel template or format? | If yes, provide the template before development. If no, we will define a standard four-sheet format (summary, revenue, upsell, expenses). |  |
| 6 | What currency is used for all transactions? | Proposed: all amounts in MAD. Confirm. If multi-currency is needed, this adds significant complexity. |  |
| 7 | Should the weekly snapshot go to the owner only, or also to the manager? | The monthly report and Excel export go to the owner. The weekly snapshot could also help the manager track expenses. |  |
| 8 | Are there dates when suites are blocked (maintenance, personal use)? | Blocked dates reduce available room-nights and affect occupancy rate accuracy. If tracked in Lodgify, we can read them. If not, occupancy may appear artificially low during maintenance periods. |  |
| 9 | What defines "best performing suite"? | Options: highest total revenue, highest occupancy rate, highest average booking value, or highest upsell conversion. We need one definition. |  |
| 10 | Should the monthly report include month-over-month comparison? | Example: "Revenue up 12% vs February." This requires storing previous month summaries. Adds complexity but provides valuable context. |  |
| 11 | Who should receive the monthly report? | Owner only? Owner and manager? Owner and an accountant email? |  |
| 12 | Is there a maximum expense amount that should trigger an approval from the owner before logging? | For example: any expense above 5,000 MAD requires owner confirmation. This adds a review step but prevents large unauthorized entries. |  |
| 13 | Should the manager be able to log expenses in French? | Example: "depense 1200 nourriture provisions du marche." If yes, Claude handles French parsing. If the manager only uses one language, we can optimize the prompt. |  |

# \[DRAFT\] Agent 6 \- Cancellation

# E-Conciergerie \- Agent 6 (Cancellations): Development Proposal

**Version:** 1.0   
**Date:** March 24, 2026   
**Property:** Riad Larbi Khalis (4 suites)   
**Status:** Draft \- Pending Stakeholder Input   
**Dependency:** Requires Agent 1 \+ 2 to be operational. The Lodgify trigger and booking state infrastructure from Agent 1 are reused.

---

## 1\. Goal

When a guest cancels a booking at Riad Larbi Khalis, the system detects it within 15 minutes, alerts the manager and owner via WhatsApp with all relevant details, and generates a recovery action plan using Claude. The action plan includes suggested pricing adjustments, minimum stay changes, and platform-specific tactics to rebook the freed dates as fast as possible.

The owner and manager make all pricing and rebooking decisions manually. The system provides intelligence and speed, not automated price changes.

**Note on PriceLabs:** The original E-Conciergerie spec includes PriceLabs integration for automated repricing of cancelled dates. This proposal intentionally excludes PriceLabs to reduce scope and external dependencies. When PriceLabs is added in a future phase (Agent 4 \+ 6 combined), the repricing recommendations generated here can be replaced with automated price adjustments.

---

## 2\. Prerequisites

All Agent 1 prerequisites must already be in place. The following are additional requirements specific to Agent 6\.

| \# | Requirement | Purpose | Notes |
| :---- | :---- | :---- | :---- |
| 1 | Agent 1 \+ 2 fully operational | Agent 6 reuses the same Lodgify polling trigger infrastructure and Google Sheets booking data from Agent 1\. | Hard dependency. |
| 2 | Lodgify community connector supports booking status changes | The connector must return a status field that distinguishes active bookings from cancelled ones. Verify that the chosen connector exposes cancellation status. | Should already be verified during Agent 1 setup. |
| 3 | No new tool accounts required | Agent 6 uses the same Make.com, 2Chat, Claude, and Google Sheets infrastructure as Agent 1\. |  |

---

## 3\. Scope

### In Scope

- Detect booking cancellations from Lodgify within 15 minutes (same polling interval as Agent 1).  
- Send an immediate WhatsApp alert to the manager and owner with: guest name, suite, freed dates, original booking value, and days until the freed check-in.  
- Generate a Claude-powered recovery action plan with specific, actionable recommendations tailored to the freed dates (pricing suggestions, minimum stay adjustments, platform tactics).  
- Update the booking state in Active Bookings to CANCELLED.  
- Stop all automated messaging for the cancelled guest (Agent 1 follow-ups, Agent 3 upsells).  
- Log the cancellation event to the System Log tab.  
- Log the revenue loss to the Accounting tab (if Agent 8 is operational).  
- Track cancellation metrics: cancellation count, total lost revenue, average lead time (how far in advance the cancellation occurred).

### Out of Scope

- Automated repricing via PriceLabs or any external pricing tool.  
- Automated rebooking or promotional campaign triggers.  
- Guest communication post-cancellation (no automated "sorry to see you go" message).  
- Cancellation reason collection (Lodgify may or may not provide this).  
- Refund processing.

### Dependency on Other Agents

| Agent | Dependency |
| :---- | :---- |
| Agent 1 \+ 2 | Provides the Lodgify polling trigger, booking data, and Google Sheets infrastructure. Agent 6 adds cancellation detection to the same Lodgify data flow. |
| Agent 3 (Upsell) | When a booking is cancelled, any pending or scheduled upsell for that guest must stop. The state change to CANCELLED in Active Bookings causes Agent 3's scheduler to skip this guest automatically (it only targets PREFERENCES\_COMPLETE). |
| Agent 8 (Accounting) | If operational, Agent 6 writes a negative revenue entry (or a cancellation entry) to the Accounting tab so monthly reports reflect lost revenue. |
| Agent 4 (Dynamic Pricing) | Future integration. When Agent 4 is built with PriceLabs, Agent 6 will trigger automated repricing instead of manual recommendations. The recovery action plan generated here will be replaced by actual price changes. |

---

## 4\. Data Model (Google Sheets)

### Modifications to Active Bookings Tab

- **Conversation State** (Column L): The value CANCELLED is added to the existing state enum. When a cancellation is detected, this column is set to CANCELLED, which blocks all automated messaging from Agent 1 and Agent 3\.  
- **Cancellation Date** (Column W): Timestamp of when the cancellation was detected.  
- **Original Booking Value** (Column X): The booking amount at the time of cancellation, preserved for reporting even after the booking is no longer active.

### System Log Tab

A cancellation event is logged with: event type \= "cancellation", booking ID, guest name, suite, freed dates, original value, timestamp.

### Accounting Tab (if Agent 8 is operational)

A cancellation entry is logged with: type \= "cancellation", amount \= negative booking value (or a separate "lost\_revenue" type), booking ID, date.

---

## 5\. Make.com Scenarios

One new scenario plus a modification to an existing one.

| \# | Scenario Name | Trigger | Schedule | Purpose |
| :---- | :---- | :---- | :---- | :---- |
| 13 | RLK \- Cancellation Handler | Triggered from Scenario 1 | Real-time (within the 15-minute Lodgify polling cycle) | When Scenario 1 detects a booking status change to cancelled, it routes to this handler. Alerts manager and owner, generates recovery plan, updates state, logs event. |

### Modification to Existing Scenario

**Scenario 1 (RLK \- New Booking Flow):** Currently filters out cancelled bookings at intake. The modification adds a new branch: when Lodgify returns a booking that already exists in Active Bookings AND the status has changed to cancelled, route to the Cancellation Handler instead of ignoring it.

This can be implemented as either a new branch in Scenario 1's Router or as a standalone Scenario 13 triggered via webhook from Scenario 1\. A branch in Scenario 1 is simpler since the Lodgify data is already available.

---

## 6\. Sequence Diagrams

### Case 1: Cancellation Detected \- Full Flow

![][image28]

### Case 2: Cancellation with Imminent Check-in (Less Than 48 Hours)

sequenceDiagram

    participant S1 as Make.com\<br/\>(Scenario 1\)

    participant S as Google Sheets

    participant C as Claude API

    participant T as 2Chat

    participant B as Manager

    S1-\>\>S1: Cancellation detected\<br/\>Check-in is in 2 days

    S1-\>\>C: Generate URGENT recovery plan\<br/\>(context: very short window,\<br/\>last-minute pricing needed)

    C--\>\>S1: Urgent recommendations:\<br/\>- Drop price 20-30% immediately\<br/\>- Remove minimum stay restriction\<br/\>- Push on Booking.com Genius\<br/\>- Consider same-day OTA visibility

    S1-\>\>T: Alert Manager (fixed number):\<br/\>"URGENT CANCELLATION\<br/\>\[details\]\<br/\>CHECK-IN IN 2 DAYS\<br/\>Immediate action required.\<br/\>\<br/\>\[urgent recovery plan\]"

    S1-\>\>T: Alert Owner (fixed number)

### Case 3: Cancellation of a Long-Stay Booking

![][image29]

### Case 4: Cancellation of a Booking Still in Agent 1 Flow

![][image30]

### Case 5: Booking Already Cancelled (Duplicate Detection)

![][image31]

---

## 7\. Recovery Action Plan Design

Claude generates a contextual recovery plan based on the specific circumstances of each cancellation. The plan is not generic advice. It factors in:

**Inputs provided to Claude:**

- Suite name and capacity  
- Freed dates (check-in to check-out)  
- Number of freed nights  
- Days until the freed check-in date  
- Lost revenue amount in MAD  
- Current month and season (high/low season context for Marrakech)  
- Day of week for check-in (weekend vs weekday)  
- Any known local events near the freed dates (if available)

**Expected output structure:** Claude returns 3 to 5 specific, numbered recommendations. Each recommendation includes what to do, why, and the expected impact. The tone is direct and actionable, written for a property manager who needs to act quickly.

**Example output for a 3-night cancellation, 10 days out, in high season:**

Recovery plan for Suite Jasmine (Mar 15-18):

1\. Reduce nightly rate by 10-15% for these dates on all  
   platforms. High season demand means moderate discounts fill fast.  
2\. Lower minimum stay to 1 night for this window only.  
   A 1-night booking at reduced rate recovers more than 3 empty nights.  
3\. Activate "Flexible cancellation" on Booking.com for  
   these dates. Last-minute bookers prefer flexible policies.  
4\. If not filled within 5 days, increase discount to 20%  
   and enable Airbnb Smart Pricing for this window.

The full prompt template is maintained in the GitHub repository.

---

## 8\. Failure Modes

| Failure | Impact | Mitigation |
| :---- | :---- | :---- |
| Lodgify connector does not expose cancellation status | Cannot detect cancellations at all | This is a hard blocker. Must verify during Agent 1 setup that the Lodgify connector returns booking status changes. If it does not, cancellation detection requires a workaround: polling for bookings that previously existed but no longer appear, which is unreliable. |
| Cancellation detected but booking not found in Active Bookings | Cannot update state or calculate lost revenue | May happen if the booking was already archived or never processed by Agent 1\. Log to System Log. Send alert to manager with available Lodgify data only. |
| Claude generates unhelpful or generic recovery plan | Manager receives low-value recommendations | Prompt design is critical. The prompt includes concrete context (dates, amount, season, lead time) to force specific recommendations. Prompt is iterable in the GitHub repository without changing Make.com scenarios. |
| Two cancellations happen in the same 15-minute polling window | Both detected in one Scenario 1 run | Scenario 1 already uses an Iterator for multiple bookings. Each cancellation is processed independently. Both alerts are sent. |
| Cancellation happens during nighttime hours | Alert delayed until morning | Cancellation alerts are NOT queued for nighttime. They are sent immediately regardless of hour. Revenue loss is time-sensitive. The manager should know immediately. |
| Owner receives cancellation alert but does nothing | Freed dates remain empty | The system provides intelligence, not enforcement. If the owner does not act on recommendations, that is a business decision. Future Agent 4 integration would automate the pricing response. |
| Lodgify returns a booking as cancelled then re-activates it (reinstatement) | Booking was marked CANCELLED but is now active again | Scenario 1 already checks for booking updates. If a previously cancelled booking reappears as active, the Router branch for "existing booking update" (Branch 9B) would update the state. A specific check should be added: if current state \= CANCELLED and Lodgify status \= active, reset state to WAITING\_PREFERENCES and alert manager that a booking was reinstated. |

---

## 9\. Future PriceLabs Integration Path

When Agent 4 (Dynamic Pricing) is built, Agent 6 changes in two ways:

1. The recovery action plan becomes optional (Claude still generates recommendations, but automated repricing replaces the manual steps).  
2. A new step is added after the cancellation alert: Agent 6 triggers PriceLabs to reprice the freed dates using competitive market data.

The current architecture supports this addition cleanly. The cancellation detection, alerting, and state management do not change. PriceLabs integration is an additive step, not a rewrite.

---

## 10\. Questions for Stakeholder

Please fill in the "Answer" column and return this document before development begins.

| \# | Question | Context | Answer |
| :---- | :---- | :---- | :---- |
| 1 | Does the Lodgify connector expose booking cancellation status? | Agent 6 depends on detecting status changes in Lodgify polling results. If the connector only returns new bookings and not status updates, cancellation detection needs a different approach. |  |
| 2 | Should cancellation alerts be sent at any hour, including nighttime? | Proposed: yes, always immediate. A cancellation at 2 AM still means revenue is at risk. The manager can choose to act in the morning, but the information should arrive instantly. Confirm or adjust. |  |
| 3 | What is considered "high season" vs "low season" for Marrakech? | Affects Claude's recovery plan recommendations. Proposed: high season \= October through April, Ramadan period, national holidays, marathon week. Low season \= June through August. |  |
| 4 | Are there known annual events in Marrakech that affect demand? | Claude can factor these into recovery recommendations if provided. Examples: Marrakech Marathon, Ramadan, Eid, film festival, fashion week. Provide a list with approximate dates. |  |
| 5 | What is the typical pricing range per night for Riad Larbi Khalis? | Claude needs a baseline to suggest meaningful percentage discounts. Example: "Suite Jasmine is normally 1,200 MAD/night in high season, 800 MAD/night in low season." |  |
| 6 | What minimum stay policy does the riad normally apply? | If the riad normally requires a 2 or 3 night minimum stay, the recovery plan can suggest temporarily reducing it for cancelled dates. |  |
| 7 | On which platforms is Riad Larbi Khalis listed? | Affects platform-specific tactics in the recovery plan. Examples: Airbnb, Booking.com, Expedia, direct website. |  |
| 8 | Should the recovery plan include social media posting suggestions? | If Agent 5 (Content and Ads) is not built, Claude could still suggest the manager manually posts a "last-minute availability" message. Useful or not? |  |
| 9 | Should cancelled bookings be archived or kept in Active Bookings? | Proposed: keep in Active Bookings with state \= CANCELLED until the freed dates pass, then archive via Scenario 5 (Checkout Archive). This allows the monthly report to track cancellation metrics. |  |
| 10 | Should the system send a message to the cancelled guest? | The original spec does not include guest communication post-cancellation. Some riads send a "sorry to see you go" message. If desired, this is a small addition. |  |



---

also feedback : 

- i think we need 