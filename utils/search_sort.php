<?php
/**
 * Manual searching and sorting helpers for ReadZone.
 *
 * Why this file exists:
 * - Replaces SQL LIKE / ORDER BY driven browsing logic with explicit algorithms
 * - Makes the project implementation demonstrably algorithmic for academic reporting
 *
 * Algorithms used:
 * 1. Linear Search (case-insensitive substring matching)
 *    Best fit for this project because:
 *    - book titles, authors, usernames, and categories are variable-length text
 *    - the dataset size is relatively small to medium in a college project
 *    - partial matching is required, which fits sequential scanning naturally
 *
 * 2. Merge Sort
 *    Best fit for this project because:
 *    - stable O(n log n) performance
 *    - suitable for arrays of associative records fetched from the database
 *    - easy to reuse for date, number, and string fields
 */

function rz_normalize_text($value)
{
    return strtolower(trim((string) $value));
}

function rz_contains_text($haystack, $needle)
{
    $haystack = rz_normalize_text($haystack);
    $needle = rz_normalize_text($needle);

    if ($needle === '') {
        return true;
    }

    return strpos($haystack, $needle) !== false;
}

function rz_linear_search_exact(array $records, $field, $target)
{
    foreach ($records as $record) {
        if (isset($record[$field]) && (string) $record[$field] === (string) $target) {
            return $record;
        }
    }
    return null;
}

function rz_linear_search_multi(array $records, array $fields, $needle)
{
    $needle = rz_normalize_text($needle);
    if ($needle === '') {
        return $records;
    }

    $matches = [];
    foreach ($records as $record) {
        foreach ($fields as $field) {
            if (isset($record[$field]) && rz_contains_text($record[$field], $needle)) {
                $matches[] = $record;
                break;
            }
        }
    }

    return $matches;
}

function rz_linear_search_field(array $records, $field, $needle)
{
    $needle = rz_normalize_text($needle);
    if ($needle === '') {
        return $records;
    }

    $matches = [];
    foreach ($records as $record) {
        if (isset($record[$field]) && rz_contains_text($record[$field], $needle)) {
            $matches[] = $record;
        }
    }

    return $matches;
}

function rz_value_for_compare($value, $type)
{
    if ($type === 'number') {
        return (float) $value;
    }

    if ($type === 'datetime') {
        $time = strtotime((string) $value);
        return $time !== false ? $time : 0;
    }

    return rz_normalize_text($value);
}

function rz_compare_records(array $left, array $right, $field, $direction = 'asc', $type = 'string')
{
    $a = rz_value_for_compare($left[$field] ?? '', $type);
    $b = rz_value_for_compare($right[$field] ?? '', $type);

    if ($a == $b) {
        return 0;
    }

    $result = ($a < $b) ? -1 : 1;
    return strtolower($direction) === 'desc' ? -$result : $result;
}

function rz_merge_sort(array $records, $field, $direction = 'asc', $type = 'string')
{
    $count = count($records);
    if ($count <= 1) {
        return $records;
    }

    $mid = intdiv($count, 2);
    $left = array_slice($records, 0, $mid);
    $right = array_slice($records, $mid);

    $left = rz_merge_sort($left, $field, $direction, $type);
    $right = rz_merge_sort($right, $field, $direction, $type);

    return rz_merge($left, $right, $field, $direction, $type);
}

function rz_merge(array $left, array $right, $field, $direction = 'asc', $type = 'string')
{
    $merged = [];
    $i = 0;
    $j = 0;

    while ($i < count($left) && $j < count($right)) {
        if (rz_compare_records($left[$i], $right[$j], $field, $direction, $type) <= 0) {
            $merged[] = $left[$i++];
        } else {
            $merged[] = $right[$j++];
        }
    }

    while ($i < count($left)) {
        $merged[] = $left[$i++];
    }

    while ($j < count($right)) {
        $merged[] = $right[$j++];
    }

    return $merged;
}
