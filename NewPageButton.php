<?php
/**
 * Page Buttons plugin — local fork: New Page menu item.
 *
 * @copyright (c) 2020 Cody Ernesti
 * @license   GPLv2 or later (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @author    Cody Ernesti
 */

namespace dokuwiki\plugin\pagebuttons;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * Class NewPageButton — appears in DokuWiki's PageMenu, opens the new-page
 * dialog via the CSS hook class `plugin_pagebuttons_newpage` (see script.js).
 */
class NewPageButton extends AbstractItem
{
    /** @var string icon file */
    protected $svg = __DIR__ . '/images/file-plus-outline.svg';

    public function __construct($label)
    {
        parent::__construct();
        $this->label = $label;
        $this->params['sectok'] = getSecurityToken();
    }

    /**
     * Append our CSS hook class so script.js can bind a click handler.
     */
    public function getLinkAttributes($classprefix = 'menuitem ')
    {
        $attr = parent::getLinkAttributes($classprefix);
        $attr['class'] = ($attr['class'] ?? '') . ' plugin_pagebuttons_newpage ';
        return $attr;
    }
}
