# evive Forms (WordPress plugin)

Light, solid forms of the evive family, as an alternative to Contact Form 7 and WPForms. Free, no external service.

- **A form is a block.** Add the "Form" block and place "Form field" blocks inside it: text, email, phone, message,
  choice (dropdown), checkbox and consent. Fields can also go in columns or groups. The block settings hold the form
  name, the recipient(s), the button text, the message after sending and an optional confirmation to the sender.
- **Validated on the server.** When a form is shown, its definition (fields and settings) is taken from the saved
  blocks and kept on the server under a hash; the page carries only that hash. A submission is checked against that
  definition, not against what the browser sends, and the recipient address never appears in the page.
- **Spam without puzzles:** a hidden trap field, a minimum fill time (3 seconds) and at most 5 submissions per
  10 minutes per visitor (the IP is only used as a salted hash, never stored).
- **Works with and without JavaScript.** With JavaScript the form is sent without a reload and errors appear next to
  the fields; without it, it is a plain form post. No nonce, so cached pages keep working.
- **Entries** under "Form entries" (administrators only): list with filter per form, detail view, CSV export,
  deleted for good after the retention period (default 90 days). Tools → Export/Erase Personal Data find entries by
  the sender's email.
- **Mail** via `wp_mail()`: a notification with Reply-To set to the sender, and optionally a confirmation to the
  sender. For reliable delivery use an SMTP plugin such as FluentSMTP.
- **Nothing on other pages:** the script (2 KB) and stylesheet load only where a form is shown.
- **For other code:** the action `evive_forms_submitted( $entry_id, $values, $definition, $page )` after every
  valid submission (newsletter services, webhooks), and the filter `evive_forms_capability` (default
  `manage_options`) for who may see entries.

## Settings → evive Forms
- **Send entries to:** one or more addresses; empty means the site's admin email. A form can override this.
- **Keep entries for:** days; 0 = only send the email, keep nothing.

## Updates
Sites update from the GitHub Releases of `evive-works/evive-forms` (asset `evive-forms.zip`), through the bundled
Plugin Update Checker.
