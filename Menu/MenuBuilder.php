<?php

/**
 * Copyright 2014 Jonathan Bouzekri. All rights reserved.
 *
 * @copyright Copyright 2014 Jonathan Bouzekri <jonathan.bouzekri@gmail.com>
 * @license https://github.com/jbouzekri/ConfigKnpMenuBundle/blob/master/LICENSE
 * @link https://github.com/jbouzekri/ConfigKnpMenuBundle
 */

/**
 * @namespace
 */
namespace Jb\Bundle\ConfigKnpMenuBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Jb\Bundle\ConfigKnpMenuBundle\Event\ConfigureMenuEvent;

/**
 * Menu Builder
 *
 * Use it in services definition with the factory configuration
 */
class MenuBuilder
{
    /**
     * the knp menu factory
     *
     * @var \Knp\Menu\FactoryInterface
     */
    private $factory;

    /**
     * the event dispatcher
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * An array of menu configuration
     *
     * @var array
     */
    private $configuration;

    /**
     * Constructor
     *
     * @param \Knp\Menu\FactoryInterface $factory the knp menu factory
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher the event dispatcher
     * @param array $configuration An array of menu configuration
     */
    public function __construct(
        FactoryInterface $factory,
        EventDispatcherInterface $dispatcher,
        $configuration = array()
    ) {
        $this->factory = $factory;
        $this->dispatcher = $dispatcher;
        $this->configuration = $configuration;
    }

    /**
     * Load configuration of menus
     *
     * @param array $configuration An array of menu configuration
     */
    public function setConfiguration(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Create a menu from the configuration loaded
     *
     * @param string $type the type of menu to load. It must match a key in the first level of configuration array
     *
     * @return \Knp\Menu\ItemInterface
     *
     * @throws \Jb\Bundle\ConfigKnpMenuBundle\Menu\Exception\MenuConfigurationNotFoundException
     */
    public function createMenu($type)
    {
        // Check if the menu type asked by the service has a configuration
        if (empty($this->configuration[$type])) {
            throw new Exception\MenuConfigurationNotFoundException($type." configuration not found");
        }

        // Create menu root item
        $menu = $this->factory->createItem($type);

        // Sort first level of items
        $this->sortItems($this->configuration[$type]['tree']);

        // Append item recursively to root
        foreach ($this->configuration[$type]['tree'] as $name => $childConfiguration) {
            $this->createItem($menu, $name, $childConfiguration);
        }

        $this->dispatcher->dispatch(ConfigureMenuEvent::CONFIGURE, new ConfigureMenuEvent($this->factory, $menu));

        return $menu;
    }

    /**
     * Add item to the menu
     * WARNING : recursive function. Is executed while there are children to the item
     *
     * @param \Knp\Menu\ItemInterface $parentItem the parent item
     * @param string $name the name of the new item
     * @param array $configuration the configuration for the new item
     */
    protected function createItem($parentItem, $name, $configuration)
    {
        // Manage routing options
        $options = array();
        if (!empty($configuration['route'])) {
            $options['route'] = $configuration['route'];
            if (!empty($configuration['routeParameters'])) {
                $options['routeParameters'] = $configuration['routeParameters'];
            }
        }

        $item = $parentItem->addChild($name, $options);

        // Set label
        if (!empty($configuration['label'])) {
            $item->setLabel($configuration['label']);
        }

        // Set uri
        if (!empty($configuration['uri'])) {
            $item->setUri($configuration['uri']);
        }

        // set attributes
        if (!empty($configuration['attributes'])) {
            $item->setAttributes($configuration['attributes']);
        }

        // set linkAttributes
        if (!empty($configuration['linkAttributes'])) {
            $item->setLinkAttributes($configuration['linkAttributes']);
        }

        // set childrenAttributes
        if (!empty($configuration['childrenAttributes'])) {
            $item->setChildrenAttributes($configuration['childrenAttributes']);
        }

        // set display
        if (isset($configuration['display'])) {
            $item->setDisplay($configuration['display']);
        }

        // set displayChildren
        if (isset($configuration['displayChildren'])) {
            $item->setDisplayChildren($configuration['displayChildren']);
        }

        // Recursive loop for appending children menu items
        if (!empty($configuration['children'])) {
            $this->sortItems($configuration['children']);
            foreach ($configuration['children'] as $childName => $childConfiguration) {
                $this->createItem($item, $childName, $childConfiguration);
            }
        }
    }

    /**
     * Sort items according to the order key value
     *
     * @param array $items an array of items
     */
    protected function sortItems(&$items)
    {
        uasort($items, function ($item1, $item2) {
            if (empty($item1['order']) || empty($item2['order']) || $item1['order'] == $item2['order']) {
                return 0;
            }

            return ($item1['order'] < $item2['order']) ? -1 : 1;
        });
    }
}
