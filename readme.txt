=== Zeko Mentor ===
Contributors: zeko
Tags: mentorship, mentoring, coaching, learning, sessions
Requires at least: 5.8
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mentorship platform with smart matching, session booking, video calls, and integration with the Zeko ecosystem.

== Description ==

Zeko Mentor connects mentors with mentees through a scoring-based matcher, session booking, and integrated video calls. 30 AJAX actions and 13 REST routes under `zeko-mentor/v1` power the public, mentor, and admin experiences.

**Key Features:**

* **Mentor Profiles** — Expertise areas, bio, hourly rates, availability, verification badges
* **Smart Matching** — Matching based on skills, availability, rating, and budget
* **Session Booking** — Availability, one-click booking, calendar integration
* **Video Calls** — Built-in Jitsi Meet integration for free video sessions
* **Reviews & Ratings** — Post-session reviews with 1-5 star ratings
* **Goals & Progress** — Mentee goal tracking with mentor feedback
* **Programs** — Group mentoring cohorts with shared goals
* **Dashboard** — Dedicated views for both mentors and mentees
* **Ecosystem Integration** — Dashboard tab, activity feed, profile cards, Zeko Pay billing

**Ecosystem Integration:**

* Hooks into Zeko Theme dashboard, activity feed, and profile pages
* Integrates with Zeko Pay for session payments and mentor payouts
* Works with Zeko Core helpers (activity logging, avatars, profile URLs)

**REST API:**

* `GET /wp-json/zeko-mentor/v1/mentors` — mentor directory with filters
* `GET /wp-json/zeko-mentor/v1/sessions` — session bookings for the current user
* `GET /wp-json/zeko-mentor/v1/goals` — mentee goals with progress
* `GET /wp-json/zeko-mentor/v1/programs` — program catalog
* `GET /wp-json/zeko-mentor/v1/matches` — mentor matches for the current user

== Installation ==

1. Upload the `zeko-mentor` folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu in WordPress
3. Ensure Zeko Core and Zeko Pay plugins are active
4. Create pages with shortcodes: `[zeko_mentor_browse]`, `[zeko_mentor_dashboard]`, `[zeko_mentor_programs]`

== Shortcodes ==

* `[zeko_mentor_browse]` — Mentor directory with filters
* `[zeko_mentor_profile id="123"]` — Single mentor profile
* `[zeko_mentor_dashboard]` — Mentee/Mentor dashboard
* `[zeko_mentor_programs]` — Browse mentorship programs

== Frequently Asked Questions ==

= How do video calls work? =

Sessions use built-in Jitsi Meet rooms so mentors and mentees meet in-browser without installing anything. The room link is generated when a session is booked and shared through the dashboard.

= How are sessions paid for? =

Booking a paid session charges the mentee's Zeko Pay wallet and credits the mentor through the Zeko Pay ledger, including the platform's payout workflow.

= What are programs? =

Programs are group mentoring cohorts with shared goals and progress tracking. Mentees enroll from the program catalog and mentors manage the cohort from their dashboard.

= How does matching work? =

The smart matching algorithm scores candidates on skills, availability, rate, rating, verification, and current load, then presents ranked matches to mentees.

== Dependencies ==

Zeko Core and Zeko Pay plugins are recommended and should be active for dashboard integration and session payments. Zeko Core powers activity logging, avatars, and profile URLs; Zeko Pay handles session charges and mentor payouts.

== Privacy ==

Data stored includes mentor profiles, session bookings, goals and progress, program cohorts, reviews, and payment references. AJAX actions are rate-limited using the caller's IP address when not logged in. Meeting URLs point to Jitsi Meet rooms (see External Services).

== External Services ==

* **Jitsi Meet** — Sessions generate a `https://meet.jit.si/` room URL that is
  stored and shown to the participants. Jitsi is loaded by the visitor's
  browser when they join a call; the plugin itself sends no outbound audio/video
  data. Room creation only happens when a session is booked.

Endpoint and data-field detail (with file:line citations) is in
`REMOTE-SERVICES.txt` in this plugin folder.

== Third-party resources ==

Zeko Mentor bundles no third-party fonts, scripts, icon libraries, or images
(icons are inline SVG; the only image is the screenshot). All bundled code is
original Zeko work under GPL-2.0-or-later.

== Uninstall ==

Deleting the plugin drops all Zeko Mentor tables, removes the shortcode pages and nav items it created, deletes its options, and clears mentor-scoped user meta (`zeko_mentor_%` only — never a blanket `zeko_%` wipe).

== Troubleshooting ==

* Video call won't start? Confirm the Jitsi room URL is present on the booked session and that `meet.jit.si` is reachable in the browser.
* Payment not credited? Verify Zeko Pay is active and the session's `payment_status` reflects the charge.
* Matching looks wrong? The score uses skills, availability, rate, rating, verification, and current load — review mentor availability slots first.

== Screenshots ==

1. Mentorship dashboard with sessions and goals


== Changelog ==

= 1.3.1 =
* Session rescheduling now actually updates the session date/time (was a silent no-op)
* Fixed uninstall: removed a blanket `zeko_%` usermeta wipe that deleted every other Zeko plugin's user data
* Goal update / progress logging are now ownership-checked (goal owner or linked mentor only)
* DB schema version reconciled to 1.4.0

= 1.0.0 =
* Initial release
* 11 custom database tables
* Mentor profiles with expertise, rates, availability
* Smart matching algorithm (skills, rating, rate, verification, load)
* Session booking with Jitsi video integration
* Reviews and ratings system
* Goals and progress tracking
* Mentorship programs (group cohorts)
* Dashboard tabs for mentors and mentees
* Activity feed integration
* Profile section and stats integration
* Email notifications (booking, completion, cancellation, reviews, goals)
* Admin management pages (mentors, sessions, programs, settings)
* Rate limiting on all AJAX actions
* Object caching on all queries
* Full Zeko Pay integration for session payments

== Upgrade Notice ==

= 1.3.1 =
Session rescheduling fixed, goal/progress ownership checks added, and uninstall scoped to Zeko Mentor data only. Upgrade recommended. Fresh translations for the 1.3.x line are welcome.

= 1.0.0 =
Initial release of Zeko Mentor.
