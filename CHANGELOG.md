# Changelog

## 0.2.0
- Hidden fields that fill themselves: campaign parameters (utm_source/medium/campaign/term/content, gclid), the
  website the visitor came from and the first page of the visit (remembered for the session in sessionStorage, no
  cookie), or a fixed value (taken from the saved form, never from the browser). They are stored with the entry and
  listed under "Source of this entry" in the notification mail; a hidden field named utm_source etc. needs no setting.
- Pre-fill a field from the link: e.g. "type" → /contact/?type=demo selects the choice "Demo" (also matches the start
  of a choice, so ?type=proefperiode selects "Proefperiode van 30 dagen").
- The Form block also accepts "recipient" as the address to send entries to (same as "Send entries to").

## 0.1.0
- First version: Form and Form field blocks, server-side validation against the saved definition, spam checks
  (trap field, fill time, rate limit), entries with list/detail/CSV export and automatic deletion, privacy
  exporter/eraser, notification and confirmation mail, updates from GitHub Releases. Card evive #415.
