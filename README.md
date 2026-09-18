# Curly Events

Custom **events** post type with an **event categories** taxonomy, native meta
boxes (date, 15-minute time select, location, recurrence), a **virtual
recurrence engine**, shortcodes, and an Oxygen 6 Post Loop bridge. Migrated from
the Fluent Snippets events engine.

## Requires Oxygen 6 (built a specific way)

This plugin drives the events **content and query layer**. The visible layouts
must be built in Oxygen 6 per the wiring documented in the project notes
(`oxygen6/AGENTS.md` → "Events → Post Loop bridge"):

- **Events Index / archive template** with an O6 **Post Loop Builder** whose
  custom query is `post_type=events`. The loop must bind card fields to the
  reserved occurrence meta keys served by this plugin:
  - `_event_occurrence_date`, `_event_occurrence_time`,
    `_event_occurrence_end_time`, `_event_occurrence_location`,
    `_event_occurrence_time_range` (per occurrence)
  - `_event_next_occurrence_date`, `_event_recurrence_text` (single pages)
- **Optional calendar toggle**: a `.calendar-toggle` button and a
  `.calendar-toggle-wrap` container on the index (the widget lazy-loads via
  `GET /wp-json/curly-events/v1/calendar`).
- **`[event_pagination]`** below the loop for numbered pages (O6's built-in
  pagination doesn't work for custom-query loops on archives).
- **Single Event template** for the `events` post type using the same reserved
  meta keys.

Without that builder wiring the CPT and shortcodes still work, but the intended
loop-based card grid won't render.

## Event categories → topic pages

Tag events with **Event Categories** (e.g. *Physical Culture*, *Manual
Therapy*), then display a category on a topic page with an O6 **Post Loop
Builder**. Because the loop bridge expands an events query into one item per
upcoming occurrence, use the loop's raw **query-string** mode to render each
class once:

```
post_type=events&event_category=physical-culture&virtual_events=off
```

- `event_category=<term-slug>` filters the loop to that term.
- `virtual_events=off` disables occurrence expansion, so each event renders
  once (title, content, …) instead of repeating for every date. The reserved
  `_event_occurrence_*` keys still resolve (they fall back to the stored event
  meta), so date/time/location bindings keep working.
- Drop `virtual_events=off` if you want the upcoming occurrence dates listed
  instead of a single card per class.

## Features

- `events` CPT (public archive, REST-enabled).
- **Event categories** (`event_category` taxonomy, hierarchical): group events
  for topic pages. Separate from core `category` (used by blog posts). Registered
  with `show_ui` + `show_in_rest` + `show_admin_column`, `public` but with
  `rewrite` disabled (no front-end term archives). Assign terms on the event edit
  screen; query them in an O6 Post Loop (see below).
- Native meta box: start date/time (15-min steps), flexible end-time text,
  location (tinyMCE with a Visual/**Code** source tab), recurrence
  **none / weekly / monthly** (by day-of-month or nth weekday), recurrence end
  date, and exception dates.
- **Virtual recurrence engine** `get_virtual_event_occurrences( $start, $end )`:
  occurrences are derived on demand from the base `_event_start_date`; a
  recurring event whose first date is in the past keeps generating later
  occurrences. Hard-capped at a 3-year horizon. **Result is cached in a
  transient** keyed by the date window (12h) and flushed on any event save,
  trash, untrash, or delete — so the archive/loop expansion does not run a
  `posts_per_page=-1` query on every page load.
- Shortcodes: `[event_list limit="5"]`, `[event_calendar]`, `[event_pagination]`.
- **Responsive mobile agenda**: under 768px the month grid is replaced by a
  stacked, day-by-day agenda showing only days with events (never blank days).
  The current month rolls `today → +30 days` (spilling into the following
  month); any other month shows its own event days. Prev/Next keep navigating
  months in both views.
- **Empty-month notice**: a month with no events shows a notice above the grid
  with a jump button to the nearest month that has events (ahead preferred, else
  behind), mirroring the Linseed calendar. When a month has nothing upcoming at
  all, the message links to the contact URL set under **Events > Settings**
  (defaults to `/contact/`). The mobile agenda does the same for an empty window,
  linking to the next (or most recent) event.
- **Settings (Events > Settings):** contact link URL + link text, and a
  "open the calendar on page load" switch (default on). The calendar toggle
  itself is an Oxygen element (`.calendar-toggle` / `.calendar-toggle-wrap`);
  the plugin drives its open/close state and lazy month loading.
- **Loop bridge**: `the_posts` expansion → one virtual post per occurrence;
  `get_post_metadata` serves the reserved `_event_occurrence_*` keys. Opt out
  per-query with `virtual_events` => 'off'.
- Calendar REST endpoint with clamped month/year and cheap cached recurrence.

## Settings

**Events > Settings** (Administrators and Editors — `edit_pages`):

| Setting | Option | Notes |
|---|---|---|
| Contact link | `curly_events_contact_url` | Used in the "no upcoming events" notice. Blank → `home_url('/contact/')`. |
| Contact link text | `curly_events_contact_text` | Anchor text; blank → `sign up for our email list on the Contact page`. |
| Open the calendar on page load | `curly_events_calendar_open` | Boolean, default on. When on, `events.js` opens `.calendar-toggle-wrap` (adds `is-open`) and loads the month on `DOMContentLoaded`. |

Both contact values are also overridable in code via the
`curly_events_contact_url` / `curly_events_contact_text` filters.

## Changelog

### 1.3.0
- **Events > Settings page** (`edit_pages`, so admins and editors): contact link
  URL + link text (replaces the hard-coded `/contact/` and phrase), and a
  calendar "open on page load" switch (default on).
- **Calendar default state:** when enabled, `events.js` auto-opens the calendar
  toggle on load. The `curlyEvents` config is now emitted as real JSON via
  `wp_add_inline_script` so `calendarOpen` is a boolean (`wp_localize_script`
  stringifies booleans).
- Contact link/text remain filterable (`curly_events_contact_url`,
  `curly_events_contact_text`).

### 1.2.0
- **Event categories:** new hierarchical `event_category` taxonomy for the
  `events` post type ("Event Categories" admin UI, REST-enabled, admin column).
  Kept separate from core `category` so event tagging doesn't collide with blog
  categories. Rewrite is disabled — no front-end term archives, no rewrite flush
  needed on update.
- **Location source view:** the Location Details editor now offers a
  Visual/**Code** tab (`quicktags` enabled) for editing the location HTML
  directly.
- No change to the archive/calendar shortcodes, loop bridge behavior, or the
  reserved occurrence meta keys.

### 1.1.0
- **Mobile agenda (under 768px):** the calendar grid is replaced by a stacked,
  day-by-day agenda. Only days with events are shown. The current month rolls
  `today → +30 days`; other months show their own event days. Prev/Next month
  navigation is retained in the agenda view.
- **Empty-month notice:** months with no events show a notice with a
  **Jump to {Month}** button to the nearest month with events (ahead preferred,
  else behind); when nothing is upcoming, the notice links to `/contact/`
  (filterable via `curly_events_contact_url`). Empty mobile-agenda windows link
  to the next, or most recent, event.
- Desktop grid, AJAX month swap, `[event_list]`, and the loop bridge are
  unchanged; no JavaScript changes were required.

## Structure

```
curly-events/
├── curly-events.php              # Headers + PUC + load order
├── uninstall.php                 # Clears occurrence transients only (content preserved)
├── assets/
│   ├── css/events.css            # List / calendar / pagination styles
│   └── js/events.js              # Calendar widget + hide-empty-card-meta
├── includes/
│   ├── constants.php             # Horizon + cache config, cache flush hooks
│   ├── cpt.php                   # "events" post type
│   ├── taxonomy.php              # "event_category" taxonomy
│   ├── admin.php                 # Meta box + save handler
│   ├── settings.php             # Events > Settings (contact link, calendar default)
│   ├── recurrence.php            # Virtual recurrence engine (transient-cached)
│   ├── shortcodes.php            # [event_list] [event_calendar] [event_pagination]
│   ├── loop-bridge.php           # the_posts / pre_get_posts / get_post_metadata
│   └── rest.php                  # Calendar REST endpoint + asset enqueue
└── vendor/plugin-update-checker/ # YahnisElsts/plugin-update-checker v5.7
```

## Updates

Distributed through **GitHub Releases** via plugin-update-checker (public repo —
no tokens). Bump `Version:` in `curly-events.php`, then
`git tag v1.0.1 && git push origin v1.0.1`. The Action builds and attaches the
ZIP; installed sites show the update in wp-admin.

## Replacing Fluent Snippets

If the site previously ran this as the Fluent `12-events-post-type-and.php`
snippet, deactivate/delete that snippet when activating the plugin so the CPT,
shortcodes, and loop bridge don't register twice. Event content is stored in
post meta, so it carries over unchanged.