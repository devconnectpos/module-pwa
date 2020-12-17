<?php

namespace SM\PWA\Repositories;

use Magento\Framework\DataObject;
use SM\Core\Api\Data\CustomerAddress;
use SM\Core\Api\Data\XOrder;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

class OrderManagement extends ServiceAbstract
{

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;
    /**
     * @var \SM\Customer\Helper\Data
     */
    protected $customerHelper;
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;
    /**
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    private $productMediaConfig;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;
    /**
     * @var \SM\Sales\Model\ResourceModel\OrderSyncError\CollectionFactory
     */
    private $orderErrorCollectionFactory;

    /**
     * @var \SM\XRetail\Helper\Data
     */
    private $retailHelper;
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * OrderManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface                        $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                                  $dataConfig
     * @param \SM\XRetail\Helper\Data                                        $retailHelper
     * @param \Magento\Store\Model\StoreManagerInterface                     $storeManager
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory     $collectionFactory
     * @param \SM\Customer\Helper\Data                                       $customerHelper
     * @param \SM\Integrate\Helper\Data                                      $integrateHelperData
     * @param \Magento\Catalog\Model\Product\Media\Config                    $productMediaConfig
     * @param \Magento\Customer\Model\CustomerFactory                        $customerFactory
     * @param \SM\Sales\Model\ResourceModel\OrderSyncError\CollectionFactory $orderErrorCollectionFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface                     $quoteRepository
     * @param \Magento\Sales\Model\OrderFactory                              $orderFactory
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $requestInterface,
        DataConfig $dataConfig,
        \SM\XRetail\Helper\Data $retailHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \SM\Customer\Helper\Data $customerHelper,
        \SM\Integrate\Helper\Data $integrateHelperData,
        \Magento\Catalog\Model\Product\Media\Config $productMediaConfig,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \SM\Sales\Model\ResourceModel\OrderSyncError\CollectionFactory $orderErrorCollectionFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->productMediaConfig          = $productMediaConfig;
        $this->customerHelper              = $customerHelper;
        $this->orderCollectionFactory      = $collectionFactory;
        $this->integrateHelperData         = $integrateHelperData;
        $this->customerFactory             = $customerFactory;
        $this->retailHelper                = $retailHelper;
        $this->orderErrorCollectionFactory = $orderErrorCollectionFactory;
        $this->quoteRepository             = $quoteRepository;
        $this->orderFactory                = $orderFactory;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    public function getOrderPWA()
    {
        $searchCriteria = $this->getSearchCriteria();

        if ($searchCriteria->getData('getErrorOrder') && intval($searchCriteria->getData('getErrorOrder')) === 1) {
            return $this->loadOrderError($searchCriteria);
        } else {
            return $this->loadOrders($searchCriteria);
        }
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function loadOrders(DataObject $searchCriteria)
    {
        $collection = $this->getOrderCollection($searchCriteria);

        $orders = [];
        if (1 < $searchCriteria->getData('currentPage')) {
        } else {
            $storeId = $searchCriteria->getData('storeId');

            /** @var \Magento\Sales\Model\Order $order */
            foreach ($collection as $order) {
                $order         = $this->orderFactory->create()->loadByIncrementId($order->getIncrementId());

                $customerPhone = "";
                $xOrder        = new XOrder($order->getData());
                $xOrder->setData('created_at', $this->retailHelper->convertTimeDBUsingTimeZone($order->getCreatedAt(), $storeId));
                $xOrder->setData('status', $order->getStatusLabel());
                if ($order->getCustomerId()) {
                    $customer = $this->customerFactory->create()->load($order->getCustomerId());
                    if ($customer->getData('retail_telephone')) {
                        $customerPhone = $customer->getData('retail_telephone');
                    } else {
                        $customerPhone = "";
                    }
                }
                $xOrder->setData(
                    'customer',
                    [
                        'id'    => $order->getCustomerId(),
                        'name'  => $order->getCustomerName(),
                        'email' => $order->getCustomerEmail(),
                        'phone' => $customerPhone,
                    ]
                );

                $xOrder->setData('items', $this->getOrderItemData($order->getItemsCollection()->getItems()));

                if ($billingAdd = $order->getBillingAddress()) {
                    $customerBillingAdd = new CustomerAddress($billingAdd->getData());
                    $xOrder->setData('billing_address', $customerBillingAdd);
                }
                if ($shippingAdd = $order->getShippingAddress()) {
                    $customerShippingAdd = new CustomerAddress($shippingAdd->getData());
                    $xOrder->setData('shipping_address', $customerShippingAdd);
                }
                if ($order->getShippingMethod() === 'smstorepickup_smstorepickup' && is_null($order->getData('retail_status'))) {
                    if (!$order->hasCreditmemos()) {
                        if ($order->canInvoice()) {
                            $xOrder->setData('retail_status', \SM\Sales\Repositories\OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING);
                        } elseif ($order->canShip()) {
                            $xOrder->setData('retail_status', \SM\Sales\Repositories\OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING);
                        }
                    } else {
                        if ($order->getState() == \Magento\Sales\Model\Order::STATE_CLOSED) {
                            $xOrder->setData('retail_status', \SM\Sales\Repositories\OrderManagement::RETAIL_ORDER_FULLY_REFUND);
                        } else {
                            if ($order->canShip()) {
                                $xOrder->setData('retail_status', \SM\Sales\Repositories\OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING);
                            }
                        }
                    }
                }
                if ($order->getPayment()->getMethod() == \SM\Payment\Model\RetailMultiple::PAYMENT_METHOD_RETAILMULTIPLE_CODE) {
                    $paymentData = json_decode($order->getPayment()->getAdditionalInformation('split_data'), true);
                    if (is_array($paymentData)) {
                        $paymentData = array_filter(
                            $paymentData,
                            function ($val) {
                                return is_array($val);
                            }
                        );
                        $xOrder->setData('payment', $paymentData);
                    }
                } else {
                    $xOrder->setData(
                        'payment',
                        [
                            [
                                'title'      => $order->getPayment()->getMethodInstance()->getTitle(),
                                'amount'     => $order->getTotalPaid(),
                                'created_at' => $order->getCreatedAt()
                            ]
                        ]
                    );
                }

                $xOrder->setData('can_creditmemo', $order->canCreditmemo());
                $xOrder->setData('can_ship', $order->canShip());
                $xOrder->setData('can_invoice', $order->canInvoice());
                $xOrder->setData('is_order_virtual', $order->getIsVirtual());

                $totals = [
                    'shipping_incl_tax'            => floatval($order->getShippingInclTax()),
                    'shipping'                     => floatval($order->getShippingAmount()),
                    'subtotal'                     => floatval($order->getSubtotal()),
                    'subtotal_incl_tax'            => floatval($order->getSubtotalInclTax()),
                    'tax'                          => floatval($order->getTaxAmount()),
                    'discount'                     => floatval($order->getDiscountAmount()),
                    'coupon_code'                  => $order->getData('coupon_code'),
                    'retail_discount_per_item'    => floatval($order->getData('discount_per_item')),
                    'grand_total'                  => floatval($order->getGrandTotal()),
                    'total_paid'                   => floatval($order->getTotalPaid()),
                    'total_refunded'               => floatval($order->getTotalRefunded()),
                    'reward_point_discount_amount' => null,
                    'reward_points_used'         => null,
                    'gift_card_discount_amount'    => null,
                    'reward_points_refund'    => null,
                    'reward_points_refund_amount'    => null,
                ];

                if ($this->integrateHelperData->isIntegrateRP()) {
                    $totals['reward_point_discount_amount'] = $order->getData('aw_reward_points_amount');
                    $totals['reward_points_used'] = $order->getData('aw_reward_points');
                    $totals['reward_points_refund'] = $order->getData('aw_reward_points_blnce_refund');
                    $totals['reward_points_refund_amount'] = $order->getData('aw_reward_points_refund');
                }

                if (($this->integrateHelperData->isIntegrateGC() ||
                     ($this->integrateHelperData->isIntegrateGCInPWA() && $order->getData('is_pwa') === '1')) &&
                     $this->integrateHelperData->isAHWGiftCardExist()) {
                    $orderGiftCards = [];
                    if ($order->getExtensionAttributes()) {
                        $orderGiftCards = $order->getExtensionAttributes()->getAwGiftcardCodes();
                    }
                    if (is_array($orderGiftCards) && count($orderGiftCards) > 0) {
                        $totals['gift_card'] = [];
                        foreach ($orderGiftCards as $giftcard) {
                            array_push(
                                $totals['gift_card'],
                                [
                                    'gift_code'   => $giftcard->getGiftcardCode(),
                                    'giftcard_amount' => floatval(abs($giftcard->getGiftcardAmount())),
                                    'base_giftcard_amount' => floatval(abs($giftcard->getBaseGiftcardAmount()))
                                ]
                            );
                        }
                    }

                    $totals['gift_card_discount_amount'] = -$order->getData('aw_giftcard_amount');
                }
                if ($this->integrateHelperData->isIntegrateGC() && $this->integrateHelperData->isGiftCardMagento2EE()) {
                    $orderGiftCards = [];
                    if ($order->getData('gift_cards')) {
                        $orderGiftCards = unserialize($order->getData('gift_cards'));
                    }
                    if (is_array($orderGiftCards) && count($orderGiftCards) > 0) {
                        $totals['gift_card'] = [];
                        foreach ($orderGiftCards as $giftCard) {
                            array_push(
                                $totals['gift_card'],
                                [
                                    'gift_code'   => $giftCard['c'],
                                    'giftcard_amount' => floatval(abs($giftCard['a'])),
                                    'base_giftcard_amount' => floatval(abs($giftCard['ba']))
                                ]
                            );
                        }
                    }

                    $totals['gift_card_discount_amount'] = -$order->getData('aw_giftcard_amount');
                }

                $xOrder->setData('totals', $totals);

                $orders[] = $xOrder;
            }
        }

        return $this->getSearchResult()
            ->setSearchCriteria($searchCriteria)
            ->setItems($orders)
            ->setTotalCount($collection->getTotalCount())
            ->setMessageError(\SM\Sales\Repositories\OrderManagement::$MESSAGE_ERROR)
            ->setLastPageNumber($collection->getLastPageNumber())
            ->getOutput();
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     * @throws \Exception
     */
    protected function getOrderCollection(DataObject $searchCriteria)
    {

        /** @var  \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->orderCollectionFactory->create();

        $collection
            ->setOrder('entity_id')
            ->setCurPage(is_nan($searchCriteria->getData('currentPage')) ? 1 : $searchCriteria->getData('currentPage'))
            ->setPageSize(
                is_nan($searchCriteria->getData('pageSize')) ? DataConfig::PAGE_SIZE_LOAD_DATA : $searchCriteria->getData('pageSize')
            );

        // $collection->addFieldToFilter('status', ["neq" => 'complete']);

        if ($customerId = $searchCriteria->getData('customerId')) {
            if ($customerId === 'guest' && $searchCriteria->getData('orderId')) {
                $collection->addFieldToFilter('entity_id', ["in" => explode(',', $searchCriteria->getData('orderId'))]);
            } else {
                $collection->getSelect()
                ->where('customer_id = ?', $customerId);
            }
        }

        // if ($is_pwa = $searchCriteria->getData('is_pwa')) {
        //     $collection->getSelect()
        //         ->where('is_pwa = ?', ($is_pwa) ? 1 : 0);
        // }

        if ($storeId = $searchCriteria->getData('storeId')) {
            $collection->getSelect()
                ->where('store_id = ?', $storeId);
        }

        return $collection;
    }

    public function loadOrderError(DataObject $searchCriteria)
    {
        $collection = $this->getOrderErrorCollection($searchCriteria);

        $orders = [];
        if (1 < $searchCriteria->getData('currentPage')) {
        } else {
            foreach ($collection as $order) {
                $orderData = json_decode($order['order_offline'], true);
                if (is_array($orderData)) {
                    if (isset($orderData['id'])) {
                        unset($orderData['id']);
                    }
                    $orders[] = $orderData;
                }
            }
        }

        return $this->getSearchResult()
            ->setSearchCriteria($searchCriteria)
            ->setItems($orders)
            ->getOutput();
    }

    protected function getOrderErrorCollection(DataObject $searchCriteria)
    {
        $collection = $this->orderErrorCollectionFactory->create();
        $storeId    = $searchCriteria->getData('storeId');
        if (is_null($storeId)) {
            throw new \Exception("Please define storeId when pull order");
        }

        $collection->addFieldToFilter('store_id', $storeId);

        if ($dateFrom = $searchCriteria->getData('dateFrom')) {
            $collection->getSelect()
                ->where('created_at >= ?', $dateFrom);
        }
        if ($dateTo = $searchCriteria->getData('dateTo')) {
            $collection->getSelect()
                ->where('created_at <= ?', $dateTo . ' 23:59:59');
        }

        return $collection;
    }

    /**
     * @param $items
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getOrderItemData($items)
    {
        $itemData = [];
        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $_item = new XOrder\XOrderItem($item->getData());
            $_item->setData('isChildrenCalculated', $item->isChildrenCalculated());
            if (!$item->getProduct() || is_null($item->getProduct()->getImage())
                || $item->getProduct()->getImage() == 'no_selection'
                || !$item->getProduct()->getImage()
            ) {
                $_item->setData('origin_image', null);
            } else {
                $_item->setData('origin_image', $this->productMediaConfig->getMediaUrl($item->getProduct()->getImage()));
            }

            $children = [];
            if ($item->getChildrenItems() && $item->getProductType() == 'bundle') {
                foreach ($item->getChildrenItems() as $childrenItem) {
                    $_child = new XOrder\XOrderItem($childrenItem->getData());
                    if (is_null($childrenItem->getProduct()->getImage())
                        || $childrenItem->getProduct()->getImage() == 'no_selection'
                        || !$childrenItem->getProduct()->getImage()
                    ) {
                        $_child->setData('origin_image', null);
                    } else {
                        $_child->setData('origin_image', $this->productMediaConfig->getMediaUrl($childrenItem->getProduct()->getImage()));
                    }
                    $children[] = $_child->getOutput();
                }
            } else {
            }

            $_item->setData('children', $children);
            $_item->setData('buy_request', $item->getBuyRequest()->getData());
            $_item->setData('price', $item->getPrice());
            $itemData[] = $_item->getOutput();
        }

        return $itemData;
    }
}
