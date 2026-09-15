<?php
$files = [
    'src/Identity/Domain/DTO/CreateEntityDTO.php',
    'src/Identity/Domain/DTO/UpdateEntityDTO.php',
    'src/Identity/Repository/PlanningEntityRepositoryInterface.php',
    'src/Identity/Repository/PlanningEntityRepository.php',
    'src/Identity/Service/EntityManagementServiceInterface.php',
    'src/Identity/Service/EntityManagementService.php',
    'src/Presentation/Controller/AdminEntityViewController.php',
    'core/App.php',
    'views/partials/sidebar.php',
    'views/admin/entities/index.php',
    'tests/AdminEntityManagementTest.php',
    'tests/ApprovalLifecycleGovernanceVerificationTest.php',
    'tests/FinalEndToEndGovernanceAcceptanceTest.php',
    'scratch/run_all_tests.php'
];

$allPassed = true;
foreach ($files as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    $cmd = 'C:\\xampp\\php\\php.exe -l ' . escapeshellarg($fullPath) . ' 2>&1';
    $output = shell_exec($cmd);
    if (strpos($output, 'No syntax errors detected') !== false) {
        echo "✓ PASS: {$file}\n";
    } else {
        echo "✗ FAIL: {$file}\n{$output}\n";
        $allPassed = false;
    }
}

exit($allPassed ? 0 : 1);
