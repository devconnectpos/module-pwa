<?php

namespace SM\PWA\Repositories;

use Exception;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\AccountManagement;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SecurityViolationException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use SM\Customer\Helper\Data;
use SM\Customer\Repositories\CustomerManagement;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Integrate\Model\RPIntegrateManagement;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use SM\Core\Api\Data\XCustomer;
use SM\Core\Api\Data\CustomerAddress;
use Magento\Framework\Exception\InvalidEmailOrPasswordException;
use Magento\Framework\Exception\State\UserLockedException;
use Magento\Framework\Stdlib\StringUtils as StringHelper;

/**
 * Class UserManagement
 *
 * @package SM\PWA\Repositories
 */
class UserManagement extends ServiceAbstract
{

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Customer\Api\GroupManagementInterface
     */
    protected $customerGroupManagement;

    /**
     * @var \SM\Customer\Repositories\CustomerManagement
     */
    protected $customerManagement;

    /**
     * @var \Magento\Customer\Model\CustomerExtractor
     */
    protected $customerExtractor;
    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    protected $accountManagement;
    /**
     * @var \SM\Customer\Helper\Data
     */
    protected $customerHelper;
    /**
     * @var \Magento\Newsletter\Model\SubscriberFactory
     */
    protected $subscriberFactory;
    /**
     * @var \Magento\Framework\Api\DataObjectHelper
     */
    protected $dataObjectHelper;
    /**
     * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
     */
    protected $customerInterfaceFactory;
    /**
     * @var \Magento\Customer\Model\CustomerRegistry
     */
    protected $customerRegistry;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \SM\Integrate\Helper\Data
     */
    private $integrateHelper;

    /**
     * @var \SM\Integrate\Model\RPIntegrateManagement
     */
    protected $rpIntegrateManagement;

    private $authentication;

    /**
     * @var \Magento\Customer\Model\Customer\CredentialsValidator
     */
    private $credentialsValidator;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var StringHelper
     */
    private $stringHelper;

    /**
     * UserManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface             $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                       $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface          $storeManager
     * @param \Magento\Customer\Model\CustomerFactory             $customerFactory
     * @param \Magento\Framework\Encryption\EncryptorInterface    $encryptor
     * @param \Magento\Customer\Api\CustomerRepositoryInterface   $customerRepository
     * @param \Magento\Customer\Api\GroupManagementInterface      $customerGroupManagement
     * @param \SM\Customer\Repositories\CustomerManagement        $customerManagement
     * @param \Magento\Customer\Model\CustomerExtractor           $customerExtractor
     * @param \Magento\Customer\Api\AccountManagementInterface    $accountManagement
     * @param \SM\Customer\Helper\Data                            $customerHelper
     * @param \Magento\Newsletter\Model\SubscriberFactory         $subscriberFactory
     * @param \Magento\Framework\Api\DataObjectHelper             $dataObjectHelper
     * @param \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerInterfaceFactory
     * @param \SM\Integrate\Helper\Data                           $integrateHelperData
     * @param \SM\Integrate\Model\RPIntegrateManagement           $RPIntegrateManagement
     * @param \Magento\Customer\Model\CustomerRegistry            $customerRegistry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface  $scopeConfig
     * @param \Magento\Framework\Stdlib\StringUtils               $stringHelper
     * @param \Magento\Framework\ObjectManagerInterface           $objectManager
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory,
        EncryptorInterface $encryptor,
        CustomerRepositoryInterface $customerRepository,
        GroupManagementInterface $customerGroupManagement,
        CustomerManagement $customerManagement,
        CustomerExtractor $customerExtractor,
        AccountManagementInterface $accountManagement,
        Data $customerHelper,
        SubscriberFactory $subscriberFactory,
        DataObjectHelper $dataObjectHelper,
        CustomerInterfaceFactory $customerInterfaceFactory,
        IntegrateHelper $integrateHelperData,
        RPIntegrateManagement $RPIntegrateManagement,
        CustomerRegistry $customerRegistry,
        ScopeConfigInterface $scopeConfig,
        StringHelper $stringHelper,
        ObjectManagerInterface $objectManager
    ) {
        $this->_encryptor               = $encryptor;
        $this->customerFactory          = $customerFactory;
        $this->customerRepository       = $customerRepository;
        $this->customerGroupManagement  = $customerGroupManagement;
        $this->customerManagement       = $customerManagement;
        $this->customerExtractor        = $customerExtractor;
        $this->accountManagement        = $accountManagement;
        $this->customerHelper           = $customerHelper;
        $this->subscriberFactory        = $subscriberFactory;
        $this->dataObjectHelper         = $dataObjectHelper;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        $this->integrateHelper          = $integrateHelperData;
        $this->rpIntegrateManagement    = $RPIntegrateManagement;
        $this->customerRegistry         = $customerRegistry;
        $this->scopeConfig              = $scopeConfig;
        $this->stringHelper             = $stringHelper;
        $this->objectManager            = $objectManager;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    public function LoginToPWA()
    {
        $data     = $this->getRequestData();
        $username = base64_decode($data['p2']);
        $password = base64_decode($data['p1']);
        $store    = $data['storeId'];

        if (is_null($store)) {
            throw  new Exception("Must have param storeId");
        }

        /** @var \Magento\Customer\Model\CustomerFactory $customerFactory */
        $customerFactory = $this->customerFactory->create();
        $customerFactory->setWebsiteId($this->storeManager->getStore($store)->getWebsiteId());
        $canLogin = $customerFactory->authenticate($username, $password);
        if ($canLogin) {
            $newTokenKey = md5(uniqid(rand(), true));

            $customer = $customerFactory->loadByEmail($username);


            $searchCriteria = new DataObject(
                [
                    'storeId'   => $store,
                    'entity_id' => $customer->getData('entity_id'),
                    'email'     => $username
                ]);

            return $this->loadDataCustomer($searchCriteria)->getOutput();
        } else {
            throw new Exception("Invalid email or password. Please try again!");
        }
    }

    public function getRetailGuestCustomer()
    {
        $store = $this->getSearchCriteria()->getData('storeId');
        $customerId = $this->getSearchCriteria()->getData('customerId');

        if ($store === null) {
            throw  new Exception("Must have param storeId");
        }

        $this->storeManager->setCurrentStore($store);

        $guestCustomer = ($customerId !== null) ? $customerId : $this->getDefaultCustomerId($store);

        if (!!$this->getSearchCriteria()->getData('customerId')) {
            $guestCustomer = $this->getSearchCriteria()->getData('customerId');
        }
        $searchCriteria = new DataObject(
            [
                'storeId'   => $store,
                'entity_id' => $guestCustomer
            ]);

        return !!$this->loadDataCustomer($searchCriteria)->getOutput()
            ? $this->loadDataCustomer($searchCriteria)->getOutput()
            : $this->customerManagement->loadCustomers($searchCriteria)->getOutput();

    }

    private function getDefaultCustomerId($storeId)
    {
        try {
            $customer = $this->customerRepository->get(
                Data::DEFAULT_CUSTOMER_RETAIL_EMAIL,
                $this->storeManager->getStore($storeId)->getWebsiteId());
        } catch (Exception $e) {
            $customer = null;
        }
        if (!is_null($customer) && $customer->getId()) {
            return $customer->getId();
        } else {
            $data = [
                "group_id"    => $this->customerGroupManagement->getDefaultGroup($storeId)->getId(),
                "email"       => Data::DEFAULT_CUSTOMER_RETAIL_EMAIL,
                "first_name"  => "Guest",
                "last_name"   => "Customer",
                "middle_name" => "",
                "prefix"      => "",
                "suffix"      => "",
                "gender"      => 0,
                "storeId"     => $storeId,
            ];

            return $this->customerManagement->create(['customer' => $data, 'storeId' => $storeId]);
        }
    }


    /**
     * @return array
     * @throws LocalizedException
     */
    public function createCustomerAccountViaPWA()
    {
        $customerData = $this->getRequest()->getParam('customer_data');
        $storeId      = $this->getRequest()->getParam('storeId');
        //check email existed
        try {
            $checkCustomer = $this->customerRepository->get($customerData['email']);
            $websiteId     = $checkCustomer->getWebsiteId();

            if ($this->customerHelper->isCustomerInStore($websiteId, $storeId)) {
                throw new Exception(__('A customer with the same email already exists in an associated website.'));
            }
        } catch (Exception $e) {
            // CustomerRepository will throw exception if can't not find customer with email
        }

        try {
            $customerCreateData = [
                'firstname' => $customerData['firstname'],
                'lastname'  => $customerData['lastname'],
                'email'     => $customerData['email'],
                'gender'    => $customerData['gender'],
                'dob'       => isset($customerData['dob']) ? $customerData['dob'] : null
            ];

            $customerDataObject = $this->customerInterfaceFactory->create();
            $this->dataObjectHelper->populateWithArray(
                $customerDataObject,
                $customerCreateData,
                CustomerInterface::class
            );

            $store = $this->storeManager->getStore($storeId);

            $customerDataObject->setGroupId(
                $this->customerGroupManagement->getDefaultGroup($store->getId())->getId()
            );

            $customerDataObject->setWebsiteId($store->getWebsiteId());
            $customerDataObject->setStoreId($store->getId());

            //check password confirmation
            $password              = $customerData['password'];
            $password_confirmation = $customerData['password_confirmation'];
            $this->checkPasswordConfirmation($password, $password_confirmation);

            $customer = $this->accountManagement
                ->createAccount($customerDataObject, $password);

            //subscription
            if (isset($customerData['is_subscribed']) && $customer->getId()) {
                if ($customerData['is_subscribed']) {
                    $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
                } else {
                    $this->subscriberFactory->create()->unsubscribeCustomerById($customer->getId());
                }
            }

            //email confirmation
            $confirmation_required = false;
            $confirmationStatus    = $this->accountManagement->getConfirmationStatus($customer->getId());
            if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                $confirmation_required = true;
            }

            $searchCriteria = new DataObject(
                [
                    'storeId'               => $storeId,
                    'entity_id'             => $customer->getId(),
                    'confirmation_required' => $confirmation_required,
                    'email'                 => $customerData['email'],
                ]);

            return $this->loadDataCustomer($searchCriteria)->getOutput();

        } catch (LocalizedException $e) {
            throw $e;
        }


    }

    /**
     * @param $password
     * @param $confirmation
     *
     * @throws InputException
     */
    protected function checkPasswordConfirmation($password, $confirmation)
    {
        if ($password != $confirmation) {
            throw new InputException(__('Please make sure your passwords match.'));
        }
    }

    /**
     * @param $searchCriteria
     *
     * @return \SM\Core\Api\SearchResult
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function loadDataCustomer($searchCriteria)
    {

        $websiteId = $this->storeManager->getStore($searchCriteria['storeId'])->getWebsiteId();
        $customer  = $this->customerRegistry->retrieve($searchCriteria['entity_id'], $websiteId);

        $customers = [];

        /** @var $customerModel \Magento\Customer\Model\Customer */
        $xCustomer = new XCustomer();
        $xCustomer->addData($customer->getData());
        $xCustomer->setData('tax_class_id', $customer->getTaxClassId());

        $xCustomer->setData('address', $this->getCustomerAddress($customer));

        $checkSubscriber = $this->subscriberFactory->create()->loadByCustomerId($customer->getId());
        if ($checkSubscriber->isSubscribed()) {
            $xCustomer->setData('subscription', true);
        } else {
            $xCustomer->setData('subscription', false);
        }

        if ($this->integrateHelper->isIntegrateRP() && $this->integrateHelper->isAHWRewardPoints()) {
            $xCustomer->setData(
                'reward_point',
                $this->integrateHelper->getRpIntegrateManagement()
                                      ->getCurrentIntegrateModel()
                                      ->getCurrentPointBalance(
                                          $customer->getEntityId(),
                                          $this->storeManager->getStore($searchCriteria['storeId'])->getWebsiteId()));
        }

        $customers[] = $xCustomer;

        return $this->getSearchResult()
                    ->setSearchCriteria($searchCriteria)
                    ->setItems($customers)
                    ->setLastPageNumber(1)
                    ->setTotalCount(1);
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     *
     * @return array
     */
    protected function getCustomerAddress(Customer $customer)
    {
        $customerAdd = [];

        foreach ($customer->getAddresses() as $address) {
            /** @var \Magento\Customer\Model\Address $address */
            $customerAdd[] = $this->getAddressData($address);
        }

        return $customerAdd;
    }

    /**
     * Get customer address base on api
     *
     * @param \Magento\Customer\Model\Address $address
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function getAddressData(Address $address)
    {
        $addData           = $address->getData();
        $addData['street'] = $address->getStreet();
        $_customerAdd      = new CustomerAddress($addData);

        return $_customerAdd->getOutput();
    }


    /**
     * @return array|bool
     * @throws SecurityViolationException
     * @throws \Exception
     */
    public function resetPasswordViaPWA()
    {
        $email = (string)$this->getRequest()->getParam('email');

        try {
            $this->accountManagement->initiatePasswordReset(
                $email,
                AccountManagement::EMAIL_RESET
            );

        } catch (NoSuchEntityException $exception) {
            // Do nothing, we don't want anyone to use this action to determine which email accounts are registered.
        } catch (SecurityViolationException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw $exception;
        }

        $items   = [];
        $items[] = ['success' => true];

        return $this->getSearchResult()
                    ->setItems($items)
                    ->setLastPageNumber(1)
                    ->setTotalCount(1)
                    ->getOutput();
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function saveCustomerDetails()
    {
        $data = $this->getRequest()->getParams();

        try {
            $customer = $this->customerRepository->getById($data['customer_data']['id']);
            $customer->setEmail($data['customer_data']['email']);
            $customer->setFirstname($data['customer_data']['firstname']);
            $customer->setLastname($data['customer_data']['lastname']);
            $customer->setGender($data['customer_data']['gender']);

            $this->customerRepository->save($customer);
        } catch (Exception $exception) {
            throw $exception;
        }

        $searchCriteria = new DataObject(
            [
                'storeId'   => $data['storeId'],
                'entity_id' => $customer->getId(),
                'email'     => $customer->getEmail(),
            ]);

        return $this->loadDataCustomer($searchCriteria)->getOutput();

    }


    /**
     * @return array
     * @throws \Exception
     */
    public function changeCustomerPassword()
    {
        try {
            //get request data
            $data     = $this->getRequest()->getParams();
            $email    = $data['customer_data']['email'];
            $currPass = $data['customer_data']['current_password'];
            $newPass  = $data['customer_data']['password'];
            $confPass = $data['customer_data']['password_confirmation'];
            if ($newPass != $confPass) {
                throw new InputException(__('Password confirmation doesn\'t match entered password.'));
            }

            //get customer
            $storeId   = $data['storeId'];
            $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
            try {
                $customer = $this->customerRepository->get($email, $websiteId);
            } catch (NoSuchEntityException $e) {
                throw new InvalidEmailOrPasswordException(__('The password doesn\'t match this account.'));
            }

            // authenticate
            try {
                $customerSecure = $this->customerRegistry->retrieveSecureData($customer->getId());
                $hash           = $customerSecure->getPasswordHash();
                if (!$this->_encryptor->validateHash($currPass, $hash)) {
                    $this->getAuthentication()->processAuthenticationFailure($customer->getId());
                    if ($this->isLocked($customer->getId())) {
                        throw new UserLockedException(__('The account is locked.'));
                    }
                    throw new InvalidEmailOrPasswordException(__('The password doesn\'t match this account.'));
                }
            } catch (InvalidEmailOrPasswordException $e) {
                throw new InvalidEmailOrPasswordException(__('The password doesn\'t match this account.'));
            }

            //change password for customer

            $customerEmail = $customer->getEmail();
            if ($this->getCredentialValidator()) {
                $this->getCredentialValidator()->checkPasswordDifferentFromEmail($customerEmail, $newPass);
            }
            $customerSecure = $this->customerRegistry->retrieveSecureData($customer->getId());
            $customerSecure->setRpToken(null);
            $customerSecure->setRpTokenCreatedAt(null);
            $this->checkPasswordStrength($newPass);
            $customerSecure->setPasswordHash($this->createPasswordHash($newPass));
            $this->customerRepository->save($customer);

            //$this->accountManagement->changePassword($email, $currPass, $newPass);

            $items   = [];
            $items[] = ['success' => true];

            return $this->getSearchResult()
                        ->setItems($items)
                        ->setLastPageNumber(1)
                        ->setTotalCount(1)
                        ->getOutput();

        } catch (Exception $exception) {
            throw $exception;
        }

    }

    /**
     * @param $customerId
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isLocked($customerId)
    {
        $currentCustomer = $this->customerRegistry->retrieve($customerId);

        return $currentCustomer->isCustomerLocked();
    }

    private function getAuthentication()
    {
        if (!($this->authentication instanceof AuthenticationInterface)) {
            return ObjectManager::getInstance()->get(
                AuthenticationInterface::class
            );
        } else {
            return $this->authentication;
        }
    }

    /**
     * Make sure that password complies with minimum security requirements.
     *
     * @param string $password
     *
     * @return void
     * @throws InputException
     */
    protected function checkPasswordStrength($password)
    {
        $length = $this->stringHelper->strlen($password);
        if ($length > AccountManagement::MAX_PASSWORD_LENGTH) {
            throw new InputException(
                __(
                    'Please enter a password with at most %1 characters.',
                    AccountManagement::MAX_PASSWORD_LENGTH
                )
            );
        }
        $configMinPasswordLength = $this->getMinPasswordLength();
        if ($length < $configMinPasswordLength) {
            throw new InputException(
                __(
                    'Please enter a password with at least %1 characters.',
                    $configMinPasswordLength
                )
            );
        }
        if ($this->stringHelper->strlen(trim($password)) != $length) {
            throw new InputException(__('The password can\'t begin or end with a space.'));
        }

        $requiredCharactersCheck = $this->makeRequiredCharactersCheck($password);
        if ($requiredCharactersCheck !== 0) {
            throw new InputException(
                __(
                    'Minimum of different classes of characters in password is %1.' .
                    ' Classes of characters: Lower Case, Upper Case, Digits, Special Characters.',
                    $requiredCharactersCheck
                )
            );
        }
    }

    /**
     * Check password for presence of required character sets
     *
     * @param string $password
     *
     * @return int
     */
    protected function makeRequiredCharactersCheck($password)
    {
        $counter        = 0;
        $requiredNumber = $this->scopeConfig->getValue(AccountManagement::XML_PATH_REQUIRED_CHARACTER_CLASSES_NUMBER);
        $return         = 0;

        if (preg_match('/[0-9]+/', $password)) {
            $counter++;
        }
        if (preg_match('/[A-Z]+/', $password)) {
            $counter++;
        }
        if (preg_match('/[a-z]+/', $password)) {
            $counter++;
        }
        if (preg_match('/[^a-zA-Z0-9]+/', $password)) {
            $counter++;
        }

        if ($counter < $requiredNumber) {
            $return = $requiredNumber;
        }

        return $return;
    }

    /**
     * Retrieve minimum password length
     *
     * @return int
     */
    protected function getMinPasswordLength()
    {
        return $this->scopeConfig->getValue(AccountManagement::XML_PATH_MINIMUM_PASSWORD_LENGTH);
    }

    /**
     * Create a hash for the given password
     *
     * @param string $password
     *
     * @return string
     */
    protected function createPasswordHash($password)
    {
        return $this->_encryptor->getHash($password, true);
    }

    /**
     * @return \Magento\Customer\Model\Customer\CredentialsValidator|null
     */
    protected function getCredentialValidator()
    {
        if ($this->credentialsValidator === null) {
            $this->credentialsValidator = $this->objectManager->create(\Magento\Customer\Model\Customer\CredentialsValidator::class);
        }
        return $this->credentialsValidator;
    }
}
