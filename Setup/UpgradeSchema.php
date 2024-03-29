<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace SM\PWA\Setup;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    public function __construct(
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\App\State $state
    ) {
        $this->orderFactory = $orderFactory;
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->state->emulateAreaCode(
                Area::AREA_FRONTEND, function (SchemaSetupInterface $setup, ModuleContextInterface $context) {
                if (version_compare($context->getVersion(), '0.0.2', '<')) {
                    $this->addPwaDataToOrder($setup);
                }
                if (version_compare($context->getVersion(), '0.0.6', '<')) {
                    $this->dummySetting($setup);
                }
            }, [$setup, $context]
            );
        } catch (\Throwable $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('Psr\Log\LoggerInterface');
            $logger->critical("====> [CPOS] Failed to upgrade PWA schema: {$e->getMessage()}");
            $logger->critical($e->getTraceAsString());
        }
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param OutputInterface      $output
     */
    public function execute(SchemaSetupInterface $setup, OutputInterface $output)
    {
        $output->writeln('  |__ Initialize PWA configuration data');
        $this->dummySetting($setup);
        $output->writeln('  |__ Add PWA data column to quote, sales_order and sales_order_grid tables');
        $this->addPwaDataToOrder($setup);
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function dummySetting(SchemaSetupInterface $setup)
    {
        $setup->startSetup();
        $configData = $setup->getTable('core_config_data');
        $data = [
            [
                'path'     => "pwa/logo/pwa_logo",
                'value'    => null,
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/brand_name/pwa_brand_active",
                'value'    => "ConnectPOS",
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/integrate/pwa_integrate_reward_points",
                'value'    => 'false',
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/integrate/pwa_integrate_gift_card",
                'value'    => 'false',
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/banner/pwa_banner_active",
                'value'    => 5,
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/product_category/pwa_show_product_visibility",
                'value'    => "1,2,3,4",
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/product_category/pwa_show_disable_categories",
                'value'    => "no",
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/product_category/pwa_show_disable_products",
                'value'    => "no",
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/product_category/pwa_show_out_of_stock_products",
                'value'    => "no",
                'scope'    => "default",
                'scope_id' => 0,
            ],
            [
                'path'     => "pwa/color_picker/pwa_theme_color",
                'value'    => "#2db9b0",
                'scope'    => "default",
                'scope_id' => 0,
            ],
        ];

        foreach ($data as $datum) {
            $setup->getConnection()->insertOnDuplicate($configData, $datum, ['value']);
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function addPwaDataToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();
        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'is_pwa')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'is_pwa',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                        'default' => '0',
                        'comment' => 'Is enable PWA',
                    ]
                );
            }
        }

        $setup->endSetup();
    }
}
