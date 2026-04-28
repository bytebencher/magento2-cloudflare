<?php
/*
 * Copyright © 2022 ByteBencher. All rights reserved.
 * See LICENCE file for license details.
 */

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Plugin\Theme\Header;

use Magento\Theme\Block\Html\Header\Logo;
use ByteBencher\Cloudflare\Config\ModuleState;
use ByteBencher\Cloudflare\Helper\CloudflareUrlFormatHelper;

class GetLogoSrcPlugin
{
    private ModuleState $moduleState;
    private CloudflareUrlFormatHelper $urlFormatter;

    /**
     * @param ModuleState $moduleState
     * @param CloudflareUrlFormatHelper $urlFormatter
     */
    public function __construct(
        ModuleState $moduleState,
        CloudflareUrlFormatHelper $urlFormatter
    ) {
        $this->moduleState = $moduleState;
        $this->urlFormatter = $urlFormatter;
    }

    /**
     * AFTER Plugin
     * @see \Magento\Theme\Block\Html\Header\Logo::getLogoSrc
     *
     * @param Logo $subject
     * @param string $result
     * @return string
     */
    public function afterGetLogoSrc(Logo $subject, string $result): string
    {
        if ($this->moduleState->isActive()) {
            // NOTE: Module State activity validation was encapsulated into getFormattedUrl method
            // TODO: update this logic in the future
            $result = $this->urlFormatter->getFormattedUrl($result);
        }

        return $result;
    }
}
