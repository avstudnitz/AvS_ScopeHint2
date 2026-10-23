<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Model\Data;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;

class OverrideResult implements OverrideResultInterface
{
    /** @var ScopeOverrideInterface[] */
    private $overrides;

    /**
     * @param ScopeOverrideInterface[] $overrides
     */
    public function __construct(array $overrides = [])
    {
        $this->overrides = array_values($overrides);
    }

    public function getOverrides(): array
    {
        return $this->overrides;
    }

    public function getOverridesByWebsite(): array
    {
        $grouped = [];
        foreach ($this->overrides as $override) {
            $websiteCode = $override->getWebsiteCode();
            if (!isset($grouped[$websiteCode])) {
                $grouped[$websiteCode] = [];
            }
            $grouped[$websiteCode][] = $override;
        }
        return $grouped;
    }

    public function count(): int
    {
        return count($this->overrides);
    }

    public function isEmpty(): bool
    {
        return $this->overrides === [];
    }
}
