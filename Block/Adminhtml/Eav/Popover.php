<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Block\Adminhtml\Eav;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Popover block for EAV (product / category) UI form fields.
 *
 * Renders the scope-override popover into the `additionalInfo` slot of a UI
 * form field. Uses `data-scopehint-popover` instead of `data-mage-init` so the
 * popover-bootstrap JS can bind it after Knockout injects the HTML into the DOM.
 */
class Popover extends Template
{
    /** @var OverrideResultInterface|null */
    private $overrides = null;

    /** @var string|null */
    private $fieldHtmlId = null;

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);

        $this->setTemplate('AvS_ScopeHint::eav/popover.phtml');
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
            $this->fieldHtmlId = 'scopehint-eav-panel-' . bin2hex(random_bytes(4));
        }
        return $this->fieldHtmlId;
    }

    public function setFieldHtmlId(string $id): self
    {
        $this->fieldHtmlId = $id;
        return $this;
    }
}
