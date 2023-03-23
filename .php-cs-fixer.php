<?php

use PhpCsFixer\Config;
use Symfony\Component\Finder\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new Config();

return $config->setRules(
    [
        '@PSR2' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => true,
        'blank_line_after_namespace' => true,
        'blank_line_before_statement' => true,
        'braces' => true,
        'class_definition' => true,
        'method_chaining_indentation' => true,
        'no_extra_blank_lines' => true,
        'multiline_whitespace_before_semicolons' => true,
        'no_spaces_around_offset' => true,
        'no_unused_imports' => true,
        'no_whitespace_before_comma_in_array' => true,
        'not_operator_with_successor_space' => true,
        'ordered_imports' => ['sort_algorithm' => 'length'],
        'trailing_comma_in_multiline' => true,
        'trim_array_spaces' => true,
        'single_quote' => true,
    ]
)
    ->setFinder($finder);
