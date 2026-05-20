<?php
/**
 * Page Buttons plugin — local fork: main action component.
 *
 * Adds three buttons to DokuWiki's PageMenu: Delete current page, New subpage,
 * New subfolder. Buttons can be hidden individually via config.
 *
 * Local modifications vs. upstream (SoarinFerret/dokuwiki-plugin-pagebuttons b8e3cec, 2022-01-26):
 *   1. Added `sepchar` to JSINFO so script.js can sanitize user-typed page
 *      names with DokuWiki's configured separator character (default '_').
 *      Pairs with the new sanitization in script.js — see README.md for the
 *      rationale (refined version of upstream PR #19).
 *   2. Added `public`/`protected` visibility modifiers on every method.
 *   3. Standardised on `[]` short array syntax throughout.
 *   4. `getLinkAttributes` in the button classes simplified — the `if empty
 *      class then assign empty` dance is replaced with `?? ''`.
 *   5. Removed redundant `getLabel()` overrides in the button classes (they
 *      duplicated AbstractItem's default behavior exactly).
 *   6. Removed stray `;` after `}` in actionPage().
 *   7. `plugin.info.txt` `date` set to `2077-01-26`.
 *
 * @copyright (c) 2020 Cody Ernesti
 * @license   GPLv2 or later (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @author    Cody Ernesti
 *
 * Originally modified from https://github.com/dregad/dokuwiki-plugin-deletepagebutton
 * @copyright (c) 2020 Damien Regad
 * @author    Damien Regad
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

use dokuwiki\plugin\pagebuttons\DeletePageButton;
use dokuwiki\plugin\pagebuttons\NewPageButton;
use dokuwiki\plugin\pagebuttons\NewFolderButton;

class action_plugin_pagebuttons extends DokuWiki_Action_Plugin
{
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('DOKUWIKI_STARTED',      'AFTER',  $this, 'addjsinfo');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY',   'AFTER',  $this, 'addNewPageButton');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY',   'AFTER',  $this, 'addNewFolderButton');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY',   'AFTER',  $this, 'addDeleteButton');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'actionPage');
    }

    /**
     * Push plugin/config bits into JSINFO so script.js can read them at runtime.
     *
     * `sepchar` is DokuWiki's configured separator character (default '_'),
     * used by the JS pagename sanitizer to substitute space and other
     * invalid characters before building the new-page URL.
     */
    public function addjsinfo(Doku_Event $event)
    {
        global $JSINFO, $conf;
        $JSINFO['plugin_pagebuttons'] = [
            'usePrompt' => $this->getConf('usePrompt'),
            'useslash'  => $conf['useslash'],
            'sepchar'   => $conf['sepchar'],
            'start'     => $conf['start'],
        ];
    }

    /**
     * Add 'Delete' button to DokuWiki's PageMenu.
     */
    public function addDeleteButton(Doku_Event $event)
    {
        global $ID;

        if (
            $event->data['view'] !== 'page'
            || $this->getConf('hideDelete')
            || !$this->canDelete($ID)
        ) {
            return;
        }

        array_splice(
            $event->data['items'],
            -1,
            0,
            [new DeletePageButton($this->getLang('delete_menu_item'))]
        );
    }

    /**
     * Add 'New Page' button to DokuWiki's PageMenu.
     */
    public function addNewPageButton(Doku_Event $event)
    {
        global $ID, $conf;

        if (
            $event->data['view'] !== 'page'
            || $this->getConf('hideNewPage')
            || !page_exists($ID)
            || ($this->getConf('onlyShowNewButtonsOnStart') && !$this->isStartPage($ID, $conf['start']))
        ) {
            return;
        }

        array_splice(
            $event->data['items'],
            -1,
            0,
            [new NewPageButton($this->getLang('newpage_menu_item'))]
        );
    }

    /**
     * Add 'New Folder' button to DokuWiki's PageMenu.
     */
    public function addNewFolderButton(Doku_Event $event)
    {
        global $ID, $conf;

        if (
            $event->data['view'] !== 'page'
            || $this->getConf('hideNewFolder')
            || !page_exists($ID)
            || ($this->getConf('onlyShowNewButtonsOnStart') && !$this->isStartPage($ID, $conf['start']))
        ) {
            return;
        }

        array_splice(
            $event->data['items'],
            -1,
            0,
            [new NewFolderButton($this->getLang('newfolder_menu_item'))]
        );
    }

    /**
     * Whether $id ends with `:<start>` — used to decide if the "new page" /
     * "new folder" buttons should appear when the corresponding config flag
     * is on.
     */
    protected function isStartPage($id, $start)
    {
        $suffix = ':' . $start;
        return substr_compare($id, $suffix, -strlen($suffix)) === 0;
    }

    /**
     * Whether the current user can delete the given page right now.
     */
    protected function canDelete($id)
    {
        global $ACT;

        return ($ACT === 'show' || empty($ACT))
            && page_exists($id)
            && auth_quickaclcheck($id) >= AUTH_EDIT
            && checklock($id) === false
            && !@file_exists(wikiLockFN($id));
    }

    /**
     * Hook for ACTION_ACT_PREPROCESS. When the "Delete" button submits its
     * URL, we recognise the custom action, delete the page (by saving empty
     * content), and redirect back to the page view.
     *
     * For the new-page/new-folder buttons the JS sends the user directly to
     * `&do=edit` so the action isn't 'newpagebutton' / 'newfolderbutton' in
     * practice — but the early-return below keeps the code path defensive
     * in case that ever changes.
     */
    public function actionPage(Doku_Event $event)
    {
        global $ID, $INFO, $lang;

        // Ignore actions other than our custom ones.
        if (
            $event->data !== 'deletepagebutton'
            && $event->data !== 'newfolderbutton'
            && $event->data !== 'newpagebutton'
        ) {
            return;
        }

        if (checkSecurityToken() && $INFO['exists']) {
            if ($event->data === 'deletepagebutton') {
                // Save the page with empty content to delete it (DokuWiki's
                // standard idiom — empty content is what "deleted" means here).
                saveWikiText($ID, null, $lang['deleted']);
                msg($this->getLang('deleted_ok'), 1);
            }
        }

        // Redirect to page view in all cases.
        $event->data = 'redirect';
    }
}
