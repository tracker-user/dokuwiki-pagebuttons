<?php
/**
 * Page Buttons plugin — local fork: New Folder menu item.
 *
 * @copyright (c) 2020 Cody Ernesti
 * @license   GPLv2 or later (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @author    Cody Ernesti
 */

namespace dokuwiki\plugin\pagebuttons;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * Class NewFolderButton — appears in DokuWiki's PageMenu, opens the new-folder
 * dialog via the CSS hook class `plugin_pagebuttons_newfolder` (see script.js).
 */
class NewFolderButton extends AbstractItem
{
    /** @var string icon file */
    protected $svg = __DIR__ . '/images/folder-plus-outline.svg';

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
        $attr['class'] = ($attr['class'] ?? '') . ' plugin_pagebuttons_newfolder ';
        return $attr;
    }
}
