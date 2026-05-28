<?php
/**
 * Page Buttons plugin — local fork: Delete Page menu item.
 *
 * @copyright (c) 2020 Cody Ernesti
 * @license   GPLv2 or later (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @author    Cody Ernesti
 *
 * Modified from: https://github.com/dregad/dokuwiki-plugin-deletepagebutton
 * @copyright (c) 2020 Damien Regad
 * @author    Damien Regad
 */

namespace dokuwiki\plugin\pagebuttons;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * Class DeletePageButton — appears in DokuWiki's PageMenu, opens the delete
 * confirmation dialog via CSS hook `plugin_pagebuttons_deletepage` (see script.js).
 *
 * The button's URL points at `?do=deletepagebutton`; action.php's hook on
 * ACTION_ACT_PREPROCESS catches that action and performs the deletion.
 */
class DeletePageButton extends AbstractItem
{
    /** @var string icon file */
    protected $svg = __DIR__ . '/images/trash-can-outline.svg';

    /**
     * @param string $label Menu item label from lang file.
     */
    public function __construct($label)
    {
        parent::__construct();
        $this->label = $label;
        $this->params['sectok'] = getSecurityToken();
    }

    /**
     * Append our CSS hook class so script.js can bind a click handler.
     *
     * @param  string $classprefix CSS class prefix passed to parent.
     * @return array
     */
    public function getLinkAttributes($classprefix = 'menuitem ')
    {
        $attr = parent::getLinkAttributes($classprefix);
        $attr['class'] = ($attr['class'] ?? '') . ' plugin_pagebuttons_deletepage ';
        return $attr;
    }
}
