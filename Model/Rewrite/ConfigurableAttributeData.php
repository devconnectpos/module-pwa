<?php

namespace SM\PWA\Model\Rewrite;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;

class ConfigurableAttributeData extends \Magento\ConfigurableProduct\Model\ConfigurableAttributeData {

    protected $eavConfig;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    public function __construct(\Magento\Eav\Model\Config $eavConfig, \Magento\Framework\Registry $registry) {
        $this->eavConfig = $eavConfig;
        $this->registry  = $registry;
    }

    public function getAttributeOptionsData($attribute, $config) {
        $isConnectPOs = $this->registry->registry('is_connectpos');
        $attributeOptionsData = [];
        if ($isConnectPOs) {
            $this->registry->unregister('is_connectpos');
            $this->registry->register('is_connectpos', true);
            $attributeId = $attribute->getAttributeId();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $eavModel      = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
            $attr          = $eavModel->load($attributeId);
            $attributeCode = $eavModel->getAttributeCode();


            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);
            $options   = $attribute->getSource()->getAllOptions();

            foreach ($options as $attributeOption) {
                $optionId = $attributeOption['value'];
                if (isset($config[$attribute->getAttributeId()][$optionId])) {
                    $attributeOptionsData[] = [
                        'id'       => $optionId,
                        'label'    => $attributeOption['label'],
                        'products' => $config[$attribute->getAttributeId()][$optionId],
                    ];
                }
            }
        }else{
            foreach ($attribute->getOptions() as $attributeOption) {
                $optionId = $attributeOption['value_index'];
                $attributeOptionsData[] = [
                    'id' => $optionId,
                    'label' => $attributeOption['label'],
                    'products' => isset($config[$attribute->getAttributeId()][$optionId])
                        ? $config[$attribute->getAttributeId()][$optionId]
                        : [],
                ];
            }
        }
        return $attributeOptionsData;
    }

}