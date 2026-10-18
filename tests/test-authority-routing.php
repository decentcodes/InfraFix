<?php
/**
 * Logic-level tests for InfraFix authority hierarchy selection.
 * This tests the selection algorithm with synthetic data; it does not connect to MySQL.
 */

function selectLowestAuthority(array $candidates, array $parentById): int
{
    if ($candidates === []) {
        throw new RuntimeException('NO_MATCH');
    }

    // Reject cycles encountered in any candidate's active parent chain.
    foreach ($candidates as $candidate) {
        $currentId = (int) $candidate['id'];
        $visited = [];

        while (array_key_exists($currentId, $parentById) && $parentById[$currentId] !== null) {
            if (isset($visited[$currentId])) {
                throw new RuntimeException('HIERARCHY_CYCLE');
            }
            $visited[$currentId] = true;
            $parentId = (int) $parentById[$currentId];
            if (isset($visited[$parentId])) {
                throw new RuntimeException('HIERARCHY_CYCLE');
            }
            if (!array_key_exists($parentId, $parentById)) {
                break; // Parent is inactive/not in the active-authority lookup.
            }
            $currentId = $parentId;
        }
    }

    $lowest = [];
    foreach ($candidates as $candidate) {
        $candidateId = (int) $candidate['id'];
        $hasApplicableDescendant = false;

        foreach ($candidates as $other) {
            $currentId = (int) $other['id'];
            if ($currentId === $candidateId) {
                continue;
            }

            $visited = [];
            while (array_key_exists($currentId, $parentById) && $parentById[$currentId] !== null) {
                if (isset($visited[$currentId])) {
                    throw new RuntimeException('HIERARCHY_CYCLE');
                }
                $visited[$currentId] = true;
                $parentId = (int) $parentById[$currentId];
                if ($parentId === $candidateId) {
                    $hasApplicableDescendant = true;
                    break;
                }
                if (!array_key_exists($parentId, $parentById)) {
                    break;
                }
                $currentId = $parentId;
            }

            if ($hasApplicableDescendant) {
                break;
            }
        }

        if (!$hasApplicableDescendant) {
            $lowest[] = $candidateId;
        }
    }

    if (count($lowest) !== 1) {
        throw new RuntimeException('AMBIGUOUS');
    }
    return $lowest[0];
}

$tests = [];
$tests[] = ['R1 child preferred', function () {
    return selectLowestAuthority(
        [['id' => 10], ['id' => 20]],
        [10 => null, 20 => 10]
    ) === 20;
}];
$tests[] = ['R2 parent fallback when child is not a candidate', function () {
    return selectLowestAuthority(
        [['id' => 10]],
        [10 => null, 20 => 10]
    ) === 10;
}];
$tests[] = ['R3 unrelated authority without category is excluded by candidate query', function () {
    // Candidate list represents SQL-filtered authorities: unrelated id 30 is not included.
    return selectLowestAuthority(
        [['id' => 10], ['id' => 20]],
        [10 => null, 20 => 10, 30 => null]
    ) === 20;
}];
$tests[] = ['R4 no applicable authority', function () {
    try {
        selectLowestAuthority([], []);
    } catch (RuntimeException $e) {
        return $e->getMessage() === 'NO_MATCH';
    }
    return false;
}];
$tests[] = ['R5 parent cycle rejected', function () {
    try {
        selectLowestAuthority(
            [['id' => 10], ['id' => 20]],
            [10 => 20, 20 => 10]
        );
    } catch (RuntimeException $e) {
        return $e->getMessage() === 'HIERARCHY_CYCLE';
    }
    return false;
}];
$tests[] = ['R6 unrelated applicable authorities rejected as ambiguous', function () {
    try {
        selectLowestAuthority(
            [['id' => 10], ['id' => 30]],
            [10 => null, 30 => null]
        );
    } catch (RuntimeException $e) {
        return $e->getMessage() === 'AMBIGUOUS';
    }
    return false;
}];

$failed = 0;
foreach ($tests as [$name, $test]) {
    $passed = $test();
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
echo sprintf("\n%d/%d tests passed.\n", count($tests) - $failed, count($tests));
exit($failed === 0 ? 0 : 1);
