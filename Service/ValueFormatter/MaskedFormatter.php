<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Config\Model\Config\TypePool;
use Magento\Framework\ObjectManager\ConfigInterface as DiConfig;

class MaskedFormatter implements ValueFormatterInterface
{
    /** @var TypePool */
    private $typePool;

    /** @var DiConfig */
    private $diConfig;

    public function __construct(
        TypePool $typePool,
        DiConfig $diConfig
    ) {
        $this->typePool = $typePool;
        $this->diConfig = $diConfig;
    }

    public function supports(Field $field): bool
    {
        $type = $field->getType();
        if ($type === 'password' || $type === 'obscure') {
            return true;
        }

        $path = (string) ($field->getConfigPath() ?: $field->getPath());
        if ($path !== '' && $this->typePool->isPresent($path, TypePool::TYPE_SENSITIVE)) {
            return true;
        }

        $backendModel = $field->getAttribute('backend_model');
        if (!is_string($backendModel) || $backendModel === '') {
            return false;
        }

        // Resolve virtual types to their real class before checking
        $backendClass = $this->diConfig->getInstanceType(ltrim($backendModel, '\\'));

        return is_a($backendClass, Encrypted::class, true);
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return '●●●●●●';
    }
}
