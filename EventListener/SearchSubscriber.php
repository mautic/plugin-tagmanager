<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTagManagerBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use MauticPlugin\MauticTagManagerBundle\Model\TagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;


    public function __construct(
        private TagModel $model,
        private CorePermissions $security,
        private Environment $twig
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::GLOBAL_SEARCH => ['onGlobalSearch', 0],
        ];
    }

    public function onGlobalSearch(GlobalSearchEvent $event): void
    {
        if (!$this->security->isGranted('tagManager:tagManager:view')) {
            return;
        }

        $searchString = $event->getSearchString();
        if (empty($searchString)) {
            return;
        }

        $items = $this->model->getEntities([
            'filter'           => $searchString,
            'start'            => 0,
            'limit'            => GlobalSearchEvent::RESULTS_LIMIT,
            'ignore_paginator' => true,
            'with_total_count' => true,
        ]);


        $this->addGlobalSearchResults(
            $this->twig,
            $event,
            $items,
            'mautic.tagmanager.tag.header.index',
            '@MauticTagManager/SubscribedEvents/Search/global.html.twig',
            ['canEdit' => $this->security->isGranted('tagManager:tagManager:edit')]
        );
    }
}
