<?php
/** Apply Adobe's version-specific hotfix after each Composer installation/update. */
$root = dirname(__DIR__);
chdir($root);
putenv('GIT_CEILING_DIRECTORIES=' . dirname($root));
$patch = $root . '/patches/VULN-39341_249.patch';
if (!is_file($root . '/vendor/magento/framework/View/Element/BlockFactory.php')) {
    fwrite(STDERR, "Install Magento dependencies before applying patches.\n");
    exit(1);
}
$quotedPatch = escapeshellarg($patch);
exec('git apply --reverse --check ' . $quotedPatch . ' 2>&1', $reverseOutput, $reverseStatus);
if ($reverseStatus === 0) {
    echo "Adobe VULN-39341 hotfix is already applied.\n";
    exit(0);
}
exec('git apply --check ' . $quotedPatch . ' 2>&1', $checkOutput, $checkStatus);
if ($checkStatus !== 0) {
    fwrite(STDERR, "Hotfix does not match installed files; review before continuing.\n" . implode("\n", $checkOutput) . "\n");
    exit(1);
}
passthru('git apply ' . $quotedPatch, $status);
if ($status !== 0) {
    exit($status);
}
echo "Applied Adobe VULN-39341 hotfix.\n";
