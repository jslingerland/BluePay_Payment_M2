<?php
/**
 * Encrypted config field backend model
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace BluePay\Payment\Model\System\Config\Backend;

class Encrypted extends \Magento\Framework\App\Config\Value implements
    \Magento\Framework\App\Config\Data\ProcessorInterface
{
    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_encryptor = $encryptor;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Magic method called during class serialization
     *
     * @return string[]
     */
    public function __sleep()
    {
        $properties = parent::__sleep();
        return array_diff($properties, ['_encryptor']);
    }

    /**
     * Magic method called during class un-serialization
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();
        $this->_encryptor = \Magento\Framework\App\ObjectManager::getInstance()->get(
            'Magento\Framework\Encryption\EncryptorInterface'
        );
    }

    /**
     * Decrypt value after loading
     *
     * @return void
     */
    protected function _afterLoad()
    {
        $value = (string)$this->getValue();
        if (!empty($value) && ($decrypted = $this->_encryptor->decrypt($value))) {
            $this->setValue($decrypted);
        }
    }

    /**
     * Encrypt value before saving
     *
     * @return void
     */
    public function beforeSave()
    {
        $this->_dataSaveAllowed = false;
        $value = $this->getValue();
        // don't save value, if an obscured value was received. This indicates that data was not changed.
        if (!preg_match('/^\*+$/', $value) && !empty($value)) {
            if ($this->getPath() == "payment/bluepay_payment/account_id" && strlen($value) != 12) {
                $this->_dataSaveAllowed = false;
                throw new \Magento\Framework\Exception\LocalizedException(__("Error. Account ID must be 12 digits and begin with 100. Your settings have not been saved."));
            } else if ($this->getPath() == "payment/bluepay_payment/secret_key" && strlen($value) != 32) {
                $this->_dataSaveAllowed = false;
                throw new \Magento\Framework\Exception\LocalizedException(__("Error. Secret Key must be 32 digits. Your settings have not been saved."));
            }
            $this->_dataSaveAllowed = true;
            $encrypted = $this->_encryptor->encrypt($value);
            $this->setValue($encrypted);
        }
    }

    /**
     * Process config value
     *
     * @param string $value
     * @return string
     */
    public function processValue($value)
    {
        return $this->_encryptor->decrypt($value);
    }
}