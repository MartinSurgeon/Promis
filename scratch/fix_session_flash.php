<?php
$file = __DIR__ . '/../src/Presentation/Controller/ProcurementPlanViewController.php';
$content = file_get_contents($file);

$content = str_replace(
    "use Promis\Core\Security\Csrf;",
    "use Promis\Core\Security\Csrf;\nuse Promis\Core\Security\Session;",
    $content
);

$content = str_replace('$request->session()->flash(', 'Session::flash(', $content);

file_put_contents($file, $content);
echo "ProcurementPlanViewController session flash replacement complete.\n";
