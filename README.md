# Page Buttons plugin for DokuWiki — local fork

Adds three buttons to DokuWiki's PageMenu — **Delete page**, **New page**, **New folder** — for one-click creation/deletion without having to type wiki link syntax.

Useful for users who don't know DokuWiki's URL conventions, or as a companion to dynamic indexes like `indexmenu`.

![screenshot](images/screenshot.png)

Configuration (Admin → Configuration Settings):

| Setting | Effect |
| --- | --- |
| `hideDelete` | Hide the Delete button |
| `hideNewPage` | Hide the New page button |
| `hideNewFolder` | Hide the New folder button |
| `usePrompt` | Use `window.prompt`/`window.confirm` instead of jQuery UI modals (some templates don't play nice with modals) |
| `onlyShowNewButtonsOnStart` | Only show New page/folder buttons when viewing a `start` page in a namespace |

Original plugin: [github.com/SoarinFerret/dokuwiki-plugin-pagebuttons](https://github.com/SoarinFerret/dokuwiki-plugin-pagebuttons). This is a local fork tracking upstream `b8e3cec` (2022-01-26).

## What changed in the local fork

### Functional change: pagename sanitization

The most user-visible change is **client-side sanitization of user-typed page and folder names**.

This refines upstream's [PR #19 "Sanitize pagename for prevent URL-related errors"](https://github.com/SoarinFerret/dokuwiki-plugin-pagebuttons/pull/19) which the author hasn't merged.

The problem PR-19 addresses is real: the original code built URLs by raw concatenation —

```js
var submit_url = pre_url + urlSeparator + newpage + "&do=edit";
window.location.href = submit_url;
```

— so if a user typed `My New Page?` into the new-page prompt, the resulting URL became `...:My New Page?&do=edit`, where `?` started the query string and the rest was nonsense.

Same goes for `#`, `&`, `%`, `+`, `=`. The wiki would either land on the wrong page or display an error.

**This fork applies PR-19's idea with two refinements over the upstream proposal:**

| Refinement | Why |
| --- | --- |
| Slightly different character set in the regex | Covers `!` and `/` in addition to PR-19's list. `!` is fine in URLs but breaks some other DokuWiki plugin patterns; `/` would confuse the URL separator choice. |
| **Empty result after sanitization → alert + cancel**, not silently fall back to `"start"` | PR-19 returned the literal string `"start"` when sanitization stripped everything, which would silently create a page called *start* in the current namespace. A user who typed only invalid characters almost certainly did not want that. The fork shows a localised "Page name is empty or contains only invalid characters" alert and cancels the operation. |

The sanitizer is intentionally *not* a full re-implementation of DokuWiki's server-side `cleanID()`. It only needs to make the URL well-formed — `cleanID()` runs on the server when the request lands and does the final pass (deaccent, lowercase, etc.) before the page is created.

`$conf['sepchar']` is forwarded through JSINFO so the JS uses DokuWiki's configured separator character (default `_`) when replacing invalid characters.

A new lang key `invalid_name` was added for the alert message. Only English is provided — DokuWiki falls back to English automatically when a key is missing from another language file. The other language files (de, fr, pt-br, sk) are unchanged.

### Code modernization

| Change | Where |
| --- | --- |
| Added `public`/`protected` visibility modifiers on every method | `action.php`, `*Button.php` |
| Standardised on `[]` short array syntax | all PHP files |
| Removed redundant `getLabel()` overrides | `NewPageButton.php`, `NewFolderButton.php`, `DeletePageButton.php` (they duplicated `AbstractItem::getLabel()` exactly) |
| Simplified `getLinkAttributes` — `$attr['class'] = ($attr['class'] ?? '') . ' ...'` instead of the if-empty-then-empty-string dance | all three button classes |
| Extracted `isStartPage()` helper instead of inlining the same `substr_compare` twice | `action.php` |
| Removed stray `;` after `}` in `actionPage` | `action.php` |
| JS handlers rewritten in strict mode; the per-handler ~30-line jQuery UI dialog boilerplate factored into a single `showDialog()` helper | `script.js` |
| Empty `if (page == null || page == '') {}` branches replaced with explicit early-return on cancel | `script.js` |
| `.click(fn)` → `.on('click', fn)` (jQuery 3 best practice) | `script.js` |
| Fixed PHP 8.3: `saveWikiText($ID, null, ...)` → `saveWikiText($ID, '', ...)` — `null` reached `strlen()`/`trim()` in `PageFile::saveWikiText()`, triggering PHP 8.1+ deprecation notices | `action.php` |
| Removed `@` suppression from `file_exists()` | `action.php` |
| `isStartPage()` body simplified to `str_ends_with()` (PHP 8.0+) | `action.php` |
| `conf/metadata.php` migrated to `[]` array syntax | `conf/metadata.php` |
| Added `@param`/`@return` docblocks to `__construct()` and `getLinkAttributes()` | `*Button.php` |
| Fixed German typo: `Deatkviere` → `Deaktiviere` | `lang/de/settings.php` |
| Added missing `invalid_name` JS key to de, fr, pt-br, sk lang files | `lang/*/lang.php` |
| Added Russian and Japanese translations | `lang/ru/`, `lang/ja/` |

### Update suppression

`plugin.info.txt` `date` set to `2077-05-28` (year bumped to 2077). The Extension Manager's `isUpdateAvailable()` returns false against any plausible upstream date, so the Update button never appears. Matches the convention used across the rest of our forked plugins.

## What did NOT change

- All four configuration settings still exist with the same names — existing configs are preserved
- Wiki page URLs/structure — no schema changes
- Language file structure — added one new optional key, didn't change existing strings
- The three menu items still register on `MENU_ITEMS_ASSEMBLY` with `AFTER` priority — they appear in the same position
- SVG icons unchanged
- The `?do=deletepagebutton` URL action and its security token handling are unchanged

## Install

Drop the folder into `lib/plugins/pagebuttons/`, or use Admin → Extension Manager → Manual Install to upload the zip.

After install:
1. Reload any open wiki page to see the new buttons in the page menu
2. Click "New page" → type a name with special characters (`Test? Page!`) → confirm the URL is built correctly and the page is created with a clean name
3. Try typing only special characters (`???`) → confirm you get the "invalid characters" alert instead of being navigated to a "start" page

## License

GPL 2, matching the original plugin.
