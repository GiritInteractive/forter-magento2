<?php

namespace Forter\Forter\Observer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;

/**
 * Class ConfigObserver
 * @package Forter\Forter\Observer
 */
class ConfigObserver implements \Magento\Framework\Event\ObserverInterface
{
    const Test_Api = "https://api.forter-secure.com/credentials/test";
    const SETTINGS_API_ENDPOINT = 'https://api.forter-secure.com/ext/settings/';
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * ConfigObserver constructor.
     * @param AbstractApi $abstractApi
     * @param Config $forterConfig
     */
    public function __construct(
        WriterInterface $writeInterface,
        AbstractApi $abstractApi,
        Config $forterConfig
    ) {
        $this->writeInterface = $writeInterface;
        $this->abstractApi = $abstractApi;
        $this->forterConfig = $forterConfig;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->validateCredentials()) {
                return false;
            }
            $json = [
              "general" => [
                "active" => $this->forterConfig->isEnabled(),
                "site_id" => $this->forterConfig->getSiteId(),
                "secret_key" => $this->forterConfig->getSecretKey(),
                "module_version" => $this->forterConfig->getModuleVersion(),
                "api_version" => $this->forterConfig->getApiVersion(),
                "debug_mode" => $this->forterConfig->isDebugEnabled(),
                "sandbox_mode" => $this->forterConfig->isSandboxMode(),
                "log_mode" => $this->forterConfig->isLogging()
              ],
              "pre_post_decision" => [
                "pre_post_Select" => $this->forterConfig->getPrePostDecisionMsg('pre_post_Select'),
                "pre_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_pre'),
                "pre_thanks_msg" => $this->forterConfig->getPreThanksMsg(),
                "post_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_post'),
                "post_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_post'),
                "post_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_post'),
                "post_thanks_msg" => $this->forterConfig->getPostThanksMsg()
              ],
              "store" => [
                "storeId" => $this->forterConfig->getStoreId()
              ],
              "connection_information" => $this->forterConfig->getTimeOutSettings(),
              "eventTime" => time()
            ];

            $url = self::SETTINGS_API_ENDPOINT;
            $this->abstractApi->sendApiRequest($url, json_encode($json));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }

    private function validateCredentials()
    {
        if (!$this->forterConfig->isEnabled()) {
            throw new \Exception('Active extension to save details');
            return false;
        }

        $url = self::Test_Api;
        $response = $this->abstractApi->sendApiRequest($url, null, 'get');
        $response = json_decode($response);
        if ($response->status == 'failed') {
            $this->writeInterface->save(
                'forter/settings/enabled',
                false,
                $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $scopeId = 0
            );
            throw new \Exception('Alexandre genral error message on credentials come here');
            return false;
        }

        return true;
    }
}
