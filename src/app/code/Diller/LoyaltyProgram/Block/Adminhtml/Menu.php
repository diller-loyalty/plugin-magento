<?php
/**
 * Copyright © DILLER AS. All rights reserved.
 */

namespace Diller\LoyaltyProgram\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Menu extends Template {
    /**
     * @var array
     */
    protected array $pool;

    /**
     * @var AbstractMenu
     */
    protected AbstractMenu $activeMenu;

    /**
     * @param Context $context
     * @param array   $menu
     */
    public function __construct(Context $context, array $menu = []) {
        $this->pool = $menu;
        parent::__construct($context);
    }

    /**
     * @return AbstractMenu
     */
    public function getActiveMenu(): AbstractMenu
    {
        if (!$this->activeMenu) {
            /** @var AbstractMenu $menu */
            foreach ($this->pool as $menu) {
                if ($menu->isVisible()) {
                    $menu->build();
                    $this->activeMenu = $menu;
                    break;
                }
            }
        }

        return $this->activeMenu;
    }

    /**
     * @return string
     */
    public function getActiveTitle(): string
    {
        if ($this->getActiveMenu()) {
            return $this->getActiveMenu()->getActiveTitle();
        }

        return '';
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        if ($this->getActiveMenu()) {
            return $this->getActiveMenu()->getItems();
        }

        return [];
    }

    /**
     * @param string $moduleName
     * @return array
     */
    public function getItemsByModuleName(string $moduleName): array
    {
        $classPrefix = str_replace('_', '\\', $moduleName);

        /** @var AbstractMenu $menu */
        foreach ($this->pool as $menu) {
            if (strpos(get_class($menu), $classPrefix) !== false) {
                $menu->build(true);

                return $menu->getItems();
            }
        }

        return [];
    }

    /**
     * @return bool|string
     */
    protected function _toHtml(): bool|string
    {
        if ($this->getActiveMenu()) {
            return parent::_toHtml();
        }

        return false;
    }
}
