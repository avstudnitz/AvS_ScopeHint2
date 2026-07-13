<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Plugin;

use Magento\Catalog\Ui\Component\ColumnFactory;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

/**
 * Use a dedicated cell template for scoped EAV columns. The template keeps the
 * original value in Knockout's text binding and renders only ScopeHint's
 * escaped companion markup as HTML, avoiding stored XSS and preserving the
 * column component's select/date label formatting.
 *
 * Plugged on ColumnFactory because EAV columns are added after getMeta() runs
 * (Catalog\Ui\Component\Listing\Columns::prepare() at column-prepare time).
 * The afterGetMeta hook on ProductDataProvider sees no EAV columns so it's the
 * wrong layer to flip bodyTmpl. ColumnFactory::create() is invoked once per EAV
 * column at construction; reliable hook for per-column config tweaks.
 */
class CatalogColumnFactoryPlugin
{
    /** @var array<string, true> */
    private static $skippedFrontendInputs = [
        'image'       => true,
        'media_image' => true,
        'gallery'     => true,
    ];

    /**
     * @param ColumnFactory $subject
     * @param mixed $column
     * @param AbstractAttribute|mixed $attribute
     * @param mixed $context
     * @param array $config
     * @return mixed
     */
    public function afterCreate(ColumnFactory $subject, $column, $attribute, $context, array $config = [])
    {
        if (!$attribute instanceof AbstractAttribute) {
            return $column;
        }

        $frontendInput = (string) $attribute->getFrontendInput();

        if (isset(self::$skippedFrontendInputs[$frontendInput])) {
            return $column;
        }

        $isGlobal = method_exists($attribute, 'getIsGlobal')
            ? (int) $attribute->getIsGlobal()
            : (int) $attribute->getData('is_global');

        if ($isGlobal === ScopedAttributeInterface::SCOPE_GLOBAL) {
            return $column;
        }

        $existing = (array) $column->getData('config');
        $column->setData(
            'config',
            array_replace($existing, ['bodyTmpl' => 'AvS_ScopeHint/ui/grid/cells/scopehint'])
        );

        return $column;
    }
}
