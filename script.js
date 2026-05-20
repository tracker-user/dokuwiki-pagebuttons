/**
 * Page Buttons plugin — local fork.
 *
 * Wires up click handlers for the three PageMenu buttons:
 *   .plugin_pagebuttons_deletepage  → confirm dialog, then navigate to delete URL
 *   .plugin_pagebuttons_newpage     → prompt for name, build edit URL for new page
 *   .plugin_pagebuttons_newfolder   → prompt for name, build edit URL for new folder
 *
 * Local modifications vs. upstream (b8e3cec, 2022-01-26):
 *   1. Pagename sanitization (refined from upstream PR #19). User-typed
 *      names are cleaned client-side so the resulting URL doesn't break on
 *      URL-special characters (`? # & % + = #` etc.). Empty result after
 *      sanitization shows an alert instead of silently creating a page
 *      named "start" (upstream PR's behavior).
 *   2. Used `var sepchar` from JSINFO (added by action.php) to match
 *      DokuWiki's configured separator character.
 *   3. Removed the empty `if (page == null || page == '') {}` branches and
 *      replaced with explicit early-return on cancel/empty.
 *   4. Extracted the dialog construction into a helper so the three handlers
 *      don't repeat ~30 lines of jQuery UI boilerplate each.
 *
 * @copyright (c) 2020 Cody Ernesti
 * @license   GPLv2 or later (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @author    Cody Ernesti
 *
 * Originally modified from https://github.com/dregad/dokuwiki-plugin-deletepagebutton
 * @copyright (c) 2020 Damien Regad
 * @author    Damien Regad
 */
jQuery(function () {
    'use strict';

    // --- Config from JSINFO ----------------------------------------------
    var cfg = (window.JSINFO && JSINFO.plugin_pagebuttons) || {};
    var usePrompt    = cfg.usePrompt || 0;
    var sepchar      = cfg.sepchar  || '_';
    var startPage    = cfg.start    || 'start';
    var useSlash     = cfg.useslash || 0;
    var urlSeparator = useSlash ? '/' : ':';

    // --- Pagename sanitizer ----------------------------------------------
    /**
     * Clean a user-typed page or folder name so it survives URL embedding
     * and roughly matches DokuWiki's server-side cleanID() rules. Server
     * still applies its own cleanID after the request lands, so this only
     * needs to make the URL well-formed — not fully canonical.
     *
     * Returns '' if nothing usable remains; callers should treat that as
     * "user typed only invalid chars, cancel the operation".
     */
    function sanitizePagename(name) {
        if (typeof name !== 'string') return '';

        name = name.trim().toLowerCase();
        if (name === '') return '';

        // Replace URL-breaking and ID-problematic characters with sepchar.
        // List intentionally covers: URL separators (? # & % + = $), path
        // characters (space, /, \), shell/regex metacharacters that
        // confuse other DokuWiki plugins (. [ ] < > ' " ` | ~ , !).
        name = name.replace(/[\s?#&%+=$/\\.\[\]<>'"`|~,!]+/g, sepchar);

        // Collapse multiple namespace separators.
        name = name.replace(/:+/g, ':');

        // Collapse runs of sepchar (e.g. "a___b" -> "a_b") using a dynamic
        // regex since sepchar is configurable.
        var sepEsc = sepchar.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');
        name = name.replace(new RegExp(sepEsc + '+', 'g'), sepchar);

        // Strip leading/trailing separators (both : and sepchar).
        name = name.replace(new RegExp('^[:' + sepEsc + ']+'), '');
        name = name.replace(new RegExp('[:' + sepEsc + ']+$'), '');

        // Convert remaining namespace colons to whichever URL separator
        // DokuWiki is configured for.
        name = name.replace(/:/g, urlSeparator);

        return name;
    }

    /**
     * Show an invalid-name alert. Tries the localised string first, falls
     * back to English if the lang key isn't set.
     */
    function invalidNameAlert() {
        var msg = (LANG && LANG.plugins && LANG.plugins.pagebuttons &&
                   LANG.plugins.pagebuttons.invalid_name) ||
                  'Page name is empty or contains only invalid characters.';
        window.alert(msg);
    }

    // --- Build the destination URL prefix for the current namespace -----
    /**
     * The URL up to and including the current namespace, with the trailing
     * page id stripped. Used as the base for new-page/new-folder URLs.
     */
    function namespaceUrlPrefix() {
        var here = window.location.href;
        var id   = JSINFO.id;
        var ns   = JSINFO.namespace;
        if (useSlash) {
            return here.substring(0, here.indexOf(id.replace(/:/g, '/'))) + ns.replace(/:/g, '/');
        }
        return here.substring(0, here.indexOf(id)) + ns;
    }

    // --- A small jQuery UI dialog factory --------------------------------
    /**
     * Build and show a jQuery UI modal with OK/Cancel. onSubmit is called
     * with the (already-trimmed) input value; if there's no input element
     * (delete-confirm case), onSubmit is called with null.
     *
     * @param {object} opts  title, body (html), inputName (or null for confirm-only)
     * @param {function(string|null)} onSubmit
     */
    function showDialog(opts, onSubmit) {
        var $dialog = jQuery('<div></div>').html(opts.body);
        $dialog.dialog({
            title:     opts.title,
            resizable: true,
            width:     'auto',
            height:    'auto',
            modal:     true,
            buttons: [
                {
                    text: LANG.plugins.pagebuttons.btn_ok,
                    click: function () {
                        var val = null;
                        if (opts.inputName) {
                            var el = document.getElementsByName(opts.inputName)[0];
                            val = el ? el.value : '';
                        }
                        $dialog.dialog('close');
                        onSubmit(val);
                    }
                },
                {
                    text: LANG.plugins.pagebuttons.btn_cancel,
                    click: function () { $dialog.dialog('close'); }
                }
            ],
            close: function () {
                jQuery(this).remove();
                // Buttons retain focus after click + preventDefault, so we
                // manually blur to release them.
                if (document.activeElement && document.activeElement.blur) {
                    document.activeElement.blur();
                }
            }
        });
    }

    // --- Delete handler --------------------------------------------------
    jQuery('.plugin_pagebuttons_deletepage').on('click', function (ev) {
        ev.preventDefault();
        var submitUrl = this.href;

        if (usePrompt) {
            if (window.confirm(LANG.plugins.pagebuttons.delete_confirm)) {
                window.location.href = submitUrl;
            }
            return;
        }

        showDialog({
            title: LANG.plugins.pagebuttons.delete_title,
            body:  '<span>' + LANG.plugins.pagebuttons.delete_confirm + '</span>',
            inputName: null
        }, function () {
            window.location.href = submitUrl;
        });
    });

    // --- New-folder handler ---------------------------------------------
    jQuery('.plugin_pagebuttons_newfolder').on('click', function (ev) {
        ev.preventDefault();
        var preUrl = namespaceUrlPrefix();

        function go(rawName) {
            if (rawName == null) return;          // cancelled
            var clean = sanitizePagename(rawName);
            if (clean === '') { invalidNameAlert(); return; }
            window.location.href = preUrl + urlSeparator + clean + urlSeparator + startPage + '&do=edit';
        }

        if (usePrompt) {
            go(window.prompt(LANG.plugins.pagebuttons.newfolder_prompt));
            return;
        }

        showDialog({
            title: LANG.plugins.pagebuttons.newfolder_title,
            body:  '<span>' + LANG.plugins.pagebuttons.newfolder_prompt +
                   '<br /><input type="text" style="z-index:10000" name="new_folder_name"><br /></span>',
            inputName: 'new_folder_name'
        }, go);
    });

    // --- New-page handler ------------------------------------------------
    jQuery('.plugin_pagebuttons_newpage').on('click', function (ev) {
        ev.preventDefault();
        var preUrl = namespaceUrlPrefix();

        function go(rawName) {
            if (rawName == null) return;          // cancelled
            var clean = sanitizePagename(rawName);
            if (clean === '') { invalidNameAlert(); return; }
            window.location.href = preUrl + urlSeparator + clean + '&do=edit';
        }

        if (usePrompt) {
            go(window.prompt(LANG.plugins.pagebuttons.newpage_prompt));
            return;
        }

        showDialog({
            title: LANG.plugins.pagebuttons.newpage_title,
            body:  '<span>' + LANG.plugins.pagebuttons.newpage_prompt +
                   '<br /><input type="text" style="z-index:10000" name="new_page_name"><br /></span>',
            inputName: 'new_page_name'
        }, go);
    });
});
