<?php

declare(strict_types=1);

namespace SR\Cloudflare\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\ButtonFactory;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\FormKey;

class DeployWorkerButton extends Field
{
    protected $_template = 'SR_Cloudflare::system/config/deploy_worker_button.phtml';

    public function __construct(
        Context $context,
        private readonly FormKey $formKey,
        private readonly ButtonFactory $buttonFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getActionUrl(): string
    {
        return $this->getUrl('srcloudflare/worker/deploy', ['_current' => true]);
    }

    public function getButtonHtml(): string
    {
        return $this->buttonFactory->create([
            'data' => [
                'id' => 'srcloudflare_deploy_worker',
                'label' => __('Deploy FPC Worker'),
                'class' => 'secondary',
                'type' => 'button',
                'onclick' => 'srCloudflareDeployWorker(); return false;',
            ],
        ])->toHtml();
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }
}
