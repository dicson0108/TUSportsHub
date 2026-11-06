<?php
if (PHP_SAPI !== 'cli' || !isset($scratch)) {
    throw new RuntimeException('Use tests/run.php test_portability.php');
}
$project = dirname(__DIR__);
$inherited = getenv();
unset($inherited['APP_BASE_PATH']);
$cases = [
    ['root', $project, [], ''],
    ['nested', dirname($project), [], '/' . basename($project)],
    ['CLI without document root', '', [], ''],
    ['configured prefix', $project, ['APP_BASE_PATH'=>'/custom/install/'], '/custom/install'],
    ['configured root', dirname($project), ['APP_BASE_PATH'=>'/'], ''],
    ['missing document root', $project . '/does-not-exist', [], ''],
];
foreach ($cases as [$label, $document_root, $environment, $expected]) {
    $code = '$_SERVER["DOCUMENT_ROOT"]=' . var_export($document_root, true) . ';'
        . '$_SERVER["SCRIPT_NAME"]="tests/run.php";'
        . 'require ' . var_export($project . '/config/app.php', true) . ';'
        . 'echo json_encode([APP_BASE_PATH,app_url("/login.php")]);';
    $process = proc_open([PHP_BINARY, '-r', $code], [1=>['pipe','w'], 2=>['pipe','w']], $pipes, $project, array_merge($inherited, $environment));
    if (!is_resource($process)) throw new RuntimeException('Cannot start PHP portability check');
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    assert(proc_close($process) === 0, $label . ': ' . $error);
    assert(json_decode($output, true, 512, JSON_THROW_ON_ERROR) === [$expected, $expected . '/login.php'], $label);
}
echo "PASS portability: root, nested, CLI and configured installation paths\n";

$environment = array_merge($inherited, ['DB_HOST'=>'db.example.invalid', 'DB_NAME'=>'example_sports', 'DB_USER'=>'example_user', 'DB_PASS'=>'0', 'DB_PORT'=>'8889']);
$code = 'require ' . var_export($project . '/config/db.php', true) . ';echo json_encode([DB_HOST,DB_NAME,DB_USER,DB_PASS,DB_PORT]);';
$process = proc_open([PHP_BINARY, '-r', $code], [1=>['pipe','w'], 2=>['pipe','w']], $pipes, $project, $environment);
if (!is_resource($process)) throw new RuntimeException('Cannot start DB configuration check');
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
assert(proc_close($process) === 0, $error);
assert(json_decode($output, true, 512, JSON_THROW_ON_ERROR) === ['db.example.invalid', 'example_sports', 'example_user', '0', 8889]);
echo "PASS portability: database environment overrides without connecting\n";
