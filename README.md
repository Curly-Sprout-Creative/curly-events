# Curly Events

Custom **events** post type with native meta boxes (date, 15-minute time select,
location, recurrence), a **virtual recurrence engine**, shortcodes, and an
Oxygen 6 Post Loop bridge. Migrated from the Fluent Snippets events engine.

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

## Features

- `events` CPT (public archive, REST-enabled).
- Native meta box: start date/time (15-min steps), flexible end-time text,
  location (tinyMCE), recurrence **none / weekly / monthly** (by day-of-month or
  nth weekday), recurrence end date, and exception dates.
- **Virtual recurrence engine** `get_virtual_event_occurrences( $start, $end )`:
  occurrences are derived on demand from the base `_event_start_date`; a
  recurring event whose first date is in the past keeps generating later
  occurrences. Hard-capped at a 3-year horizon. **Result is cached in a
  transient** keyed by the date window (12h) and flushed on any event save,
  trash, untrash, or delete — so the archive/loop expansion does not run a
  `posts_per_page=-1` query on every page load.
- Shortcodes: `[event_list limit="5"]`, `[event_calendar]`, `[event_pagination]`.
- **Loop bridge**: `the_posts` expansion → one virtual post per occurrence;
  `get_post_metadata` serves the reserved `_event_occurrence_*` keys. Opt out
  per-query with `virtual_events` => 'off'.
- Calendar REST endpoint with clamped month/year and cheap cached recurrence.

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
│   ├── admin.php                 # Meta box + save handler
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