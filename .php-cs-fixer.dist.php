<?php

/**
 * Wicket's PHP CS Fixer Configuration
 * Type: almost disable all formatting.
 * Description: to be used on legacy repositories.
 *
 * @link https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
 * @link https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst
 */

$finder = PhpCsFixer\Finder::create()
    ->exclude([
        'node_modules',
        'vendor',
        '.ci',
        'dist',
    ])
    ->notName([
        'composer.lock',
    ])
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        'line_ending' => true,
    ])
    ->setFinder($finder)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
