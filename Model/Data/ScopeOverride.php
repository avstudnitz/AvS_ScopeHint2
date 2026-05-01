<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Model\Data;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;

class ScopeOverride implements ScopeOverrideInterface
{
    /** @var string */
    private $scopeType;

    /** @var string */
    private $scopeCode;

    /** @var string */
    private $scopeName;

    /** @var int */
    private $scopeId;

    /** @var string */
    private $websiteCode;

    /** @var mixed */
    private $rawValue;

    /** @var string */
    private $displayValue;

    /**
     * @param mixed $rawValue
     */
    public function __construct(
        string $scopeType,
        string $scopeCode,
        string $scopeName,
        int $scopeId,
        string $websiteCode,
        $rawValue,
        string $displayValue
    ) {
        $this->scopeType = $scopeType;
        $this->scopeCode = $scopeCode;
        $this->scopeName = $scopeName;
        $this->scopeId = $scopeId;
        $this->websiteCode = $websiteCode;
        $this->rawValue = $rawValue;
        $this->displayValue = $displayValue;
    }

    public function getScopeType(): string
    {
        return $this->scopeType;
    }

    public function getScopeCode(): string
    {
        return $this->scopeCode;
    }

    public function getScopeName(): string
    {
        return $this->scopeName;
    }

    public function getScopeId(): int
    {
        return $this->scopeId;
    }

    public function getWebsiteCode(): string
    {
        return $this->websiteCode;
    }

    public function getRawValue()
    {
        return $this->rawValue;
    }

    public function getDisplayValue(): string
    {
        return $this->displayValue;
    }
}
