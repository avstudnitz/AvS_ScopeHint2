<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Block\Adminhtml\System\Config;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Popover extends Template
{
    /** @var OverrideResultInterface|null */
    private $overrides = null;

    /** @var string|null */
    private $fieldHtmlId = null;

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);

        $this->setTemplate('AvS_ScopeHint::system/config/popover.phtml');
    }

    public function setOverrides(OverrideResultInterface $result): self
    {
        $this->overrides = $result;
        return $this;
    }

    public function getOverrides(): ?OverrideResultInterface
    {
        return $this->overrides;
    }

    public function getTotalCount(): int
    {
        if ($this->overrides === null) {
            return 0;
        }
        return $this->overrides->count();
    }

    public function shouldExpandAll(): bool
    {
        return $this->getTotalCount() <= 3;
    }

    public function shouldShowSearch(): bool
    {
        return $this->getTotalCount() > 5;
    }

    public function getFieldHtmlId(): string
    {
        if ($this->fieldHtmlId === null) {
            $this->fieldHtmlId = 'scopehint-panel-' . substr(md5((string) mt_rand()), 0, 8);
        }
        return $this->fieldHtmlId;
    }

    public function setFieldHtmlId(string $id): self
    {
        $this->fieldHtmlId = $id;
        return $this;
    }
}
