<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\ValueFormatter;

use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\Escaper;

class SerializedArrayFormatter implements ParentAwareValueFormatterInterface
{
    private const MAX_LISTED_CHANGES = 3;

    private const MAX_CELL_LENGTH = 40;

    /** @var Escaper */
    private $escaper;

    public function __construct(Escaper $escaper)
    {
        $this->escaper = $escaper;
    }

    public function supports(Field $field): bool
    {
        return $this->isSerializedBackend($field);
    }

    /**
     * @param mixed $value
     */
    public function format($value, Field $field): string
    {
        $rows = $this->decodeRows($value);

        if ($rows === null) {
            return $this->escaper->escapeHtml((string) __('(serialized data differs)'));
        }

        return $this->escaper->escapeHtml((string) __('%1 rows configured', count($rows)));
    }

    /**
     * @param mixed $value
     * @param mixed $parentValue
     */
    public function formatAgainstParent($value, $parentValue, Field $field): string
    {
        $rows = $this->decodeRows($value);
        $parentRows = $this->decodeRows($parentValue);

        if ($rows === null || $parentRows === null) {
            return $this->format($value, $field);
        }

        // Row keys are generated per save, so the same row has a different key in each scope.
        $rows = array_values($rows);
        $parentRows = array_values($parentRows);
        $changes = [];

        for ($index = 0; $index < max(count($rows), count($parentRows)); $index++) {
            $position = $index + 1;

            if (!array_key_exists($index, $parentRows)) {
                $changes[] = (string) __('row %1 added', $position);
            } elseif (!array_key_exists($index, $rows)) {
                $changes[] = (string) __('row %1 removed', $position);
            } else {
                $changedCells = $this->describeChangedCells($rows[$index], $parentRows[$index]);
                if ($changedCells !== []) {
                    $changes[] = (string) __('row %1: %2', $position, implode(', ', $changedCells));
                }
            }
        }

        $rowCount = count($rows);

        // The scope still keeps its own copy, so later parent changes will not reach it.
        if ($changes === []) {
            return $this->escaper->escapeHtml(
                $rowCount === 1
                    ? (string) __('1 row, same as parent scope')
                    : (string) __('%1 rows, same as parent scope', $rowCount)
            );
        }

        $summary = $rowCount === 1 ? (string) __('1 row') : (string) __('%1 rows', $rowCount);
        $description = $summary . '; ' . implode('; ', array_slice($changes, 0, self::MAX_LISTED_CHANGES));

        if (count($changes) > self::MAX_LISTED_CHANGES) {
            $description .= '; ' . __('+%1 more', count($changes) - self::MAX_LISTED_CHANGES);
        }

        return $this->escaper->escapeHtml($description);
    }

    /**
     * @param mixed $value
     * @return array<int|string, mixed>|null
     */
    private function decodeRows($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Legacy PHP serialize format
        $unserialized = @unserialize($value, ['allowed_classes' => false]);

        return is_array($unserialized) ? $unserialized : null;
    }

    /**
     * @param mixed $row
     * @param mixed $parentRow
     * @return string[]
     */
    private function describeChangedCells($row, $parentRow): array
    {
        if (!is_array($row) || !is_array($parentRow)) {
            return $this->cellText($row) === $this->cellText($parentRow)
                ? []
                : [(string) __('“%1”', $this->truncate($this->cellText($row)))];
        }

        $changedCells = [];
        foreach ($row as $column => $cell) {
            if (array_key_exists($column, $parentRow)
                && $this->cellText($cell) === $this->cellText($parentRow[$column])
            ) {
                continue;
            }
            $changedCells[] = (string) __('%1 “%2”', $column, $this->truncate($this->cellText($cell)));
        }

        foreach (array_keys(array_diff_key($parentRow, $row)) as $column) {
            $changedCells[] = (string) __('%1 removed', $column);
        }

        return $changedCells;
    }

    /**
     * @param mixed $cell
     */
    private function cellText($cell): string
    {
        return is_scalar($cell) || $cell === null ? (string) $cell : (string) json_encode($cell);
    }

    private function truncate(string $text): string
    {
        return mb_strlen($text) > self::MAX_CELL_LENGTH
            ? mb_substr($text, 0, self::MAX_CELL_LENGTH) . '...'
            : $text;
    }

    private function isSerializedBackend(Field $field): bool
    {
        $backendModel = $field->getAttribute('backend_model');

        if (!is_string($backendModel) || $backendModel === '') {
            return false;
        }

        if (strpos($backendModel, 'Backend\\Serialized') !== false) {
            return true;
        }

        if (strpos($backendModel, 'FieldArray\\AbstractFieldArray') !== false) {
            return true;
        }

        return is_subclass_of($backendModel, \Magento\Config\Model\Config\Backend\Serialized::class);
    }
}
