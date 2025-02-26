<?php

/*
 * This file is part of Monsieur Biz' No Commerce plugin for Sylius.
 *
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MonsieurBiz\SyliusNoCommercePlugin\Routing;

use BadMethodCallException;
use MonsieurBiz\SyliusNoCommercePlugin\Model\ConfigInterface;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RoutingConfigurator as BaseRouteCollectionBuilder;

class RouteCollectionBuilder extends BaseRouteCollectionBuilder
{
    private ?LoaderInterface $loader;

    private ConfigInterface $config;

    /** @phpstan-ignore-next-line */
    private array $resources = [];

    private array $routesToRemove = [
        // Customers & Account & Users
        'customer' => [
            'sylius_admin_partial_customer',
            'sylius_admin_customer',
            'api_customer',
            'sylius_shop_log',
            'sylius_shop_register',
            'sylius_shop_request_password_reset_token',
            'sylius_shop_password_reset',
            'sylius_shop_user_request_verification_token',
            'sylius_shop_user_verification',
            'sylius_shop_account',
            'api_register_shop_users_post_collection',
            'sylius_api_shop_authentication_token',
            'sylius_shop_ajax_user_check_action',
        ],

        // Products
        'product' => [
            'sylius_admin_product',
            'sylius_admin_api_product',
            'sylius_admin_ajax_product',
            'sylius_shop_partial_product',
            'sylius_shop_product',
            'sylius_admin_partial_product',
            'sylius_admin_ajax_generate_product_slug',
            'api_product',
        ],

        // Taxons
        'taxon' => [
            'sylius_admin_partial_taxon',
            'sylius_admin_ajax_taxon',
            'sylius_admin_taxon',
            'sylius_admin_api_taxon',
            'sylius_shop_partial_taxon',
            'sylius_admin_ajax_generate_taxon_slug',
            'sylius_shop_partial_channel_menu_taxon_index',
            'api_taxon',
        ],

        // Checkout
        'checkout' => [
            'sylius_admin_api_checkout',
            'sylius_shop_checkout',
            'sylius_shop_register_after_checkout',
        ],

        // Addresses
        'address' => [
            'sylius_shop_account_address',
            'sylius_admin_partial_address',
        ],

        // Orders
        'order' => [
            'sylius_admin_order',
            'sylius_admin_api_order',
            'sylius_shop_account_order',
            'sylius_shop_order',
            'sylius_admin_partial_order',
            'sylius_admin_customer_order',
            'sylius_admin_api_customer_order',
            'api_order',
        ],

        // Adjustments
        'adjustment' => [
            'sylius_admin_api_adjustment',
            'sylius_shop_ajax_render_province_form',
            'api_adjustment',
        ],

        // Promotions
        'promotion' => [
            'sylius_admin_partial_promotion',
            'sylius_admin_promotion',
            'sylius_admin_api_promotion',
            'api_promo',
        ],

        // Shipping and Shipments
        'shipment' => [
            'sylius_admin_partial_shipment',
            'sylius_admin_ship',
            'sylius_admin_api_ship',
            'api_ship',
        ],

        // Inventory
        'inventory' => [
            'sylius_admin_inventory',
        ],

        // Attributes
        'attribute' => [
            'sylius_admin_get_attribute_types',
            'sylius_admin_get_product_attributes',
            'sylius_admin_render_attribute_forms',
        ],

        // Payments
        'payment' => [
            'sylius_admin_payment',
            'sylius_admin_get_payment',
            'payum_',
            'sylius_admin_api_payment',
            'api_pay',
        ],

        // PayPal
        'paypal' => [
            'sylius_paypal',
        ],

        // Taxes
        'tax' => [
            'sylius_admin_tax_',
            'sylius_admin_api_tax_',
            'api_tax',
        ],

        // Currencies
        'currency' => [
            'sylius_admin_currency',
            'sylius_admin_api_currency',
            'sylius_shop_switch_currency',
            'api_currencies',
        ],

        // Exchange rates
        'exchange' => [
            'sylius_admin_exchange',
            'sylius_admin_api_exchange',
            'api_exchange',
        ],

        // Zones
        'zone' => [
            'sylius_admin_zone',
            'sylius_admin_api_zone',
            'api_zone',
        ],

        // Countries
        'country' => [
            'sylius_admin_country',
            'sylius_admin_api_country',
            'api_countries',
        ],

        // Provinces
        'province' => [
            'sylius_admin_api_province',
            'sylius_admin_ajax_render_province_form',
            'api_province',
        ],

        // Carts
        'cart' => [
            'sylius_admin_api_cart',
            'sylius_shop_ajax_cart',
            'sylius_shop_partial_cart',
            'sylius_shop_cart',
            'api_cart',
        ],

        // Dashboard
        'dashboard' => [
            'sylius_admin_dashboard_statistics',
        ],

        // Others
        'other' => [
            'api_shop_billing',
            'api_channels_shop',
        ],
    ];

    /**
     * RouteCollectionBuilder constructor.
     */
    public function __construct(ConfigInterface $config, LoaderInterface $loader = null)
    {
        parent::__construct($loader);
        $this->config = $config;
        $this->loader = $loader;
    }

    /**
     * @param mixed $resource
     * @param string $prefix
     * @param string|null $type
     *
     * @throws LoaderLoadException
     *
     * @return RouteCollectionBuilder|BaseRouteCollectionBuilder
     */
    public function import($resource, $prefix = '/', $type = null)
    {
        /** @var RouteCollection[] $collections */
        $collections = $this->load($resource, $type);

        // create a builder from the RouteCollection
        $builder = $this->createBuilder();
        $routesToRemove = $this->getRoutesToRemove();

        foreach ($collections as $collection) {
            $this->processDisableRouteOnRoutCollection($collection, $builder, $routesToRemove);

            foreach ($collection->getResources() as $routeResource) {
                $builder->addResource($routeResource);
            }
        }

        // mount into this builder
        $this->mount($prefix, $builder);

        return $builder;
    }

    private function processDisableRouteOnRoutCollection(
        RouteCollection $routeCollection,
        self $builder,
        array $routesToRemove
    ): void {
        foreach ($routeCollection->all() as $name => $route) {
            foreach ($routesToRemove as $routeToRemove) {
                if (false !== strpos($name, $routeToRemove)) {
                    $route->setCondition('1 == 0');
                }
            }
            $builder->addRoute($route, $name);
        }
    }

    /**
     * Returns a RouteCollectionBuilder that can be configured and then added with mount().
     *
     * @return RouteCollectionBuilder
     */
    public function createBuilder()
    {
        return new self($this->config, $this->loader);
    }

    /**
     * Adds a resource for this collection.
     *
     * @return $this
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function addResource(ResourceInterface $resource): self
    {
        $this->resources[] = $resource;

        return $this;
    }

    /**
     * Finds a loader able to load an imported resource and loads it.
     *
     * @param mixed $resource A resource
     * @param string|null $type The resource type or null if unknown
     *
     * @throws LoaderLoadException If no loader is found
     *
     * @return RouteCollection[]
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function load($resource, string $type = null): array
    {
        if (null === $this->loader) {
            throw new BadMethodCallException('Cannot import other routing resources: you must pass a LoaderInterface when constructing RouteCollectionBuilder.');
        }

        if ($this->loader->supports($resource, $type)) {
            $collections = $this->loader->load($resource, $type);

            return \is_array($collections) ? $collections : [$collections];
        }

        $resolver = $this->loader->getResolver();
        if (false === $loader = $resolver->resolve($resource, $type)) {
            /** @phpstan-ignore-next-line */
            throw new LoaderLoadException($resource, null, 0, null, $type);
        }

        $collections = $loader->load($resource, $type);

        return \is_array($collections) ? $collections : [$collections];
    }

    /**
     * Retrieve all routes to remove depending on config.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getRoutesToRemove(): array
    {
        $routesToRemove = [];

        if ($this->config->areCustomersAllowed()) {
            unset($this->routesToRemove['customer']);
        }

        if ($this->config->areZonesAllowed()) {
            unset($this->routesToRemove['zone']);
        }

        if ($this->config->areZonesAllowed() || $this->config->areCountriesAllowed()) {
            unset($this->routesToRemove['country']);
        }

        foreach ($this->routesToRemove as $routes) {
            $routesToRemove = array_merge($routesToRemove, $routes);
        }

        return $routesToRemove;
    }
}
