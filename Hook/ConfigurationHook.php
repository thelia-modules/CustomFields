<?php

namespace CustomFields\Hook;

use CustomFields\CustomFields;
use CustomFields\Model\CustomFieldOptionPageQuery;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

class ConfigurationHook extends BaseHook
{
    public function onTopMenuTools(HookRenderBlockEvent $event): void
    {
        $event->add(
            [
                'id' => 'tools_menu_header_customfields',
                'class' => '',
                'url' => URL::getInstance()?->absoluteUrl('/admin/module/customfields/list'),
                'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            ]
        );
    }

    public function onMainInTopMenuItems(HookRenderEvent $event): void
    {
        $optionPages = CustomFieldOptionPageQuery::create()->orderByTitle()->find();

        if ($optionPages->count() === 0) {
            return;
        }

        $event->add(
            $this->render('includes/option-pages-menu.html', [
                'option_pages' => $optionPages,
            ])
        );
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-tools' => [
                [
                    'type' => 'back',
                    'method' => 'onTopMenuTools',
                ],
            ],
            'main.in-top-menu-items' => [
                [
                    'type' => 'back',
                    'method' => 'onMainInTopMenuItems',
                ],
            ],
        ];
    }
}
