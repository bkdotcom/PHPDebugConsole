<?php

/**
 * https://stackoverflow.com/questions/68589782/how-to-get-a-simple-phpunit-codecoverage-summary-in-text-output
 */

list($util, $fileClover, $percentage) = $argv + array(null, null, 0);

if (!\file_exists($fileClover)) {
    throw new InvalidArgumentException('Invalid input file provided');
}

if (!\is_numeric($percentage)) {
    throw new InvalidArgumentException('An integer checked percentage must be given as second parameter');
}

$percentage = \min(
    100,
    \max(0, \round($percentage, 2))
);

$xml = new SimpleXMLElement(\file_get_contents($fileClover));

$fCoverage = function (SimpleXMLElement $xml) {
    $fMetrics = function ($s) use ($xml) {
        return \array_sum(\array_map(
            'intval',
            $xml->xpath('.//metrics/@' . $s . 'statements')
        ));
    };
    $total = $fMetrics('');
    $checked = $fMetrics('covered');
    return $total === $checked
        ? 100
        : $checked / $total * 100;
};

$coverage = \round($fCoverage($xml), 2);

if ($coverage >= $percentage) {
    \printf(
        "\e[92m✓\e[0m Code coverage is \e[33m%.2f%%\e[0m%s",
        $coverage,
        PHP_EOL
    );
    exit(0);
}

\printf(
    "\e[91m⦻\e[0m Code coverage is \e[33m%.2f%%\e[0m, which is below the accepted \e[33m%.2f%%\e[0m%s",
    $coverage,
    $percentage,
    PHP_EOL
);
exit(1);
