# Extending AvS_ScopeHint2 — custom override sources

`OverrideSourceInterface` lets third-party modules plug scope-override detection
for synthetic UI form fields into the existing popover pipeline.

## Interface

```php
namespace AvS\ScopeHint\Api;

interface OverrideSourceInterface
{
    // Return true when this source can detect overrides for the given field.
    // Inspect $fieldMeta (the arguments.data.config slice) — no DB calls here.
    public function supports(string $entityType, array $fieldMeta): bool;

    // Return all store-level overrides for the field.
    // Called only when supports() returned true for the same arguments.
    public function getOverrides(
        string $entityType,
        int    $entityId,
        array  $fieldMeta
    ): \AvS\ScopeHint\Api\Data\OverrideResultInterface;
}
```

## Worked example — Hyva SeoSuite synthetic fields

SeoSuite emits product fields (`product_price_override`, `product_availability_override`,
`product_offer_count_override`) backed by the `hyva_seo_metadata` table
`(entity_type, entity_id, store_id)`. No EAV rows exist, so
`EavProductOverrideSource::supports()` returns false — these fields need a
dedicated source.

### 1. Implement the source

```php
// app/code/Hyva/SeoSuite/ScopeHint/SeoMetadataOverrideSource.php
namespace Hyva\SeoSuite\ScopeHint;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\OverrideSourceInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use Hyva\SeoSuite\Api\SeoMetadataRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class SeoMetadataOverrideSource implements OverrideSourceInterface
{
    private const SUPPORTED_FIELDS = [
        'product_price_override',
        'product_availability_override',
        'product_offer_count_override',
    ];

    private $repository;
    private $storeManager;

    public function __construct(
        SeoMetadataRepositoryInterface $repository,
        StoreManagerInterface $storeManager
    ) {
        $this->repository   = $repository;
        $this->storeManager = $storeManager;
    }

    public function supports(string $entityType, array $fieldMeta): bool
    {
        if ($entityType !== 'product') {
            return false;
        }
        return in_array($fieldMeta['dataScope'] ?? '', self::SUPPORTED_FIELDS, true);
    }

    public function getOverrides(
        string $entityType,
        int    $entityId,
        array  $fieldMeta
    ): OverrideResultInterface {
        $field        = $fieldMeta['dataScope'];
        $defaultValue = $this->repository->getForStore($entityId, $field, 0);
        $overrides    = [];

        foreach ($this->storeManager->getStores(false) as $store) {
            $storeId    = (int) $store->getId();
            $storeValue = $this->repository->getForStore($entityId, $field, $storeId);

            if ((string) $storeValue === (string) $defaultValue) {
                continue;
            }

            $website  = $this->storeManager->getWebsite($store->getWebsiteId());
            $overrides[] = new ScopeOverride(
                ScopeOverrideInterface::SCOPE_TYPE_STORES,
                (string) $store->getCode(),
                (string) $store->getName(),
                $storeId,
                (string) $website->getCode(),
                $storeValue,
                (string) $storeValue
            );
        }

        return new OverrideResult($overrides);
    }
}
```

### 2. Register via DI

```xml
<!-- app/code/Hyva/SeoSuite/etc/adminhtml/di.xml -->
<type name="AvS\ScopeHint\Service\OverrideSourcePool">
    <arguments>
        <argument name="sources" xsi:type="array">
            <item name="hyva_seo_metadata"
                  xsi:type="object"
                  sortOrder="50">Hyva\SeoSuite\ScopeHint\SeoMetadataOverrideSource</item>
        </argument>
    </arguments>
</type>
```

`sortOrder="50"` places this source ahead of the default EAV sources
(`eav_product` at 100, `eav_category` at 200). The pool uses first-match-wins,
so setting a lower sortOrder ensures SeoSuite fields are claimed before the EAV
source can return false for them.
