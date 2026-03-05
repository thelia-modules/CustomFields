<?php

namespace CustomFields\Hook;

use CustomFields\CustomFields;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
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
                'url' => URL::getInstance()?->absoluteUrl('/admin/module/customfields'),
                'title' => $this->trans('Custom Fields', [], CustomFields::DOMAIN_NAME),
            ]
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
        ];
    }
}
