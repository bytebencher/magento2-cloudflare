<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Controller\Adminhtml\Worker;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use ByteBencher\Cloudflare\Model\Worker\Deployer;

class Deploy extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ByteBencher_Cloudflare::bytebencher_cloudflare_settings';

    public function __construct(
        Action\Context $context,
        private readonly Deployer $deployer
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $websiteCode = (string) $this->getRequest()->getParam('website', '');
        $storeCode = (string) $this->getRequest()->getParam('store', '');

        try {
            $workerName = $this->deployer->deploy($websiteCode);
            $this->messageManager->addSuccessMessage(
                __('Cloudflare worker "%1" was deployed successfully.', $workerName)
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('Cloudflare worker deployment failed.'));
        }

        return $resultRedirect->setPath(
            'adminhtml/system_config/edit',
            array_filter([
                'section' => 'bytebencher_cloudflare',
                'website' => $websiteCode,
                'store' => $storeCode,
            ], static fn ($value): bool => $value !== '')
        );
    }
}
