<?php

declare(strict_types=1);

$suites = [
    'FoundationTest.php' => 42,
    'PlanningDomainTest.php' => 57,
    'PlanningPersistenceTest.php' => 109,
    'PlanningServiceTest.php' => 89,
    'ExecutionPersistenceTest.php' => 103,
    'RequisitionServiceTest.php' => 59,
    'RequisitionWorkflowServiceTest.php' => 122,
    'AuthenticationServiceTest.php' => 38,
    'Phase25HardeningTest.php' => 95,
    'RequisitionWebControllerTest.php' => 6,
    'ProcurementPlanWebControllerTest.php' => 24,
    'AdminUserManagementTest.php' => 10,
    'AdminEntityManagementTest.php' => 77,
    'ApprovalLifecycleGovernanceVerificationTest.php' => 41,
    'FinalEndToEndGovernanceAcceptanceTest.php' => 41,
    'RoleBasedDashboardQueueTest.php' => 55,
];

echo "===============================================================\n";
echo " PROMIS FULL REGRESSION & PHASE 2.5 HARDENING TEST SUITES\n";
echo " University of Skills Training and Entrepreneurial Development\n";
echo "===============================================================\n\n";

$totalPassed = 0;
$totalExpected = 0;
$failures = [];

foreach ($suites as $suite => $expected) {
    $totalExpected += $expected;
    $cmd = "C:\\xampp\\php\\php.exe tests/{$suite} 2>&1";
    $output = [];
    $retCode = 0;
    exec($cmd, $output, $retCode);

    $outText = implode("\n", $output);

    // Find "Passed: X / Y" or "X / Y Passed"
    if (preg_match('/(?:Passed:\s*(\d+)\s*\/\s*(\d+)|(\d+)\s*\/\s*(\d+)\s*Passed)/i', $outText, $m)) {
        $passed = (int)($m[1] !== '' ? $m[1] : $m[3]);
        $totalPassed += $passed;
        if ($retCode === 0 && $passed >= $expected) {
            printf(" [PASS] %-35s : %d / %d assertions\n", $suite, $passed, $expected);
        } else {
            printf(" [FAIL] %-35s : %d / %d assertions (Exit %d)\n", $suite, $passed, $expected, $retCode);
            $failures[] = $suite;
        }
    } else {
        printf(" [ERROR] %-34s : Could not parse test output (Exit %d)\n", $suite, $retCode);
        echo $outText . "\n";
        $failures[] = $suite;
    }
}

echo "\n===============================================================\n";
echo " GRAND TOTAL SUMMARY\n";
echo " Total Assertions Passed: {$totalPassed} / {$totalExpected}\n";
echo " Suites Passing: " . (count($suites) - count($failures)) . " / " . count($suites) . "\n";
echo " Failures: " . count($failures) . "\n";
echo "===============================================================\n";

if (empty($failures)) {
    echo "\n>>> 100% SUCCESS: ALL REGRESSION AND AUTHENTICATION TESTS PASSED <<<\n";
} else {
    echo "\n>>> TESTS FAILED IN: " . implode(', ', $failures) . " <<<\n";
    exit(1);
}
