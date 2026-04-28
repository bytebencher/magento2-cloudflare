<?php
/*
 * Copyright © 2022 ByteBencher. All rights reserved.
 * See LICENCE file for license details.
 */

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Model\System\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class ImageFit implements OptionSourceInterface
{
    public const NONE = 'none';
    public const CONTAIN = 'contain';
    public const COVER = 'cover';
    public const CROP = 'crop';
    public const PAD = 'pad';
    public const SCALE_DOWN = 'scale-down';

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::NONE, 'label' => new Phrase('None')],
            ['value' => self::CONTAIN, 'label' => new Phrase('Contain')],
            ['value' => self::COVER, 'label' => new Phrase('Cover')],
            ['value' => self::CROP, 'label' => new Phrase('Crop')],
            ['value' => self::PAD, 'label' => new Phrase('Pad')],
            ['value' => self::SCALE_DOWN, 'label' => new Phrase('Scale Down')],
        ];
    }

    /**
     * Returns options in "key-value" format
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::NONE => new Phrase('None'),
            self::CONTAIN => new Phrase('Contain'),
            self::COVER => new Phrase('Cover'),
            self::CROP => new Phrase('Crop'),
            self::PAD => new Phrase('Pad'),
            self::SCALE_DOWN => new Phrase('Scale Down'),
        ];
    }
}
