<?php

/**
 * @file
 */

use SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return function (ContainerConfigurator $containerConfigurator): void {
  $services = $containerConfigurator->services();
  /**
 * Every property should have @var annotation .*/
  $services->set(ReturnTypeHintSniff::class);

  $parameters = $containerConfigurator->parameters();
  // Ena Parallel run.
  $parameters->set(Option::PARALLEL, TRUE);
  $parameters->set(Option::SKIP, ['*/upgrade_status/tests/modules/*']);
  $parameters->set(Option::FILE_EXTENSIONS, ['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine']);

  $parameters->set(Option::SKIP, [
    // This part is needed, because `TypeHintDeclarationSniff` is actually mix of 7 rules we don't need
    // (they also delete code, so be sure to have this section here)
    'SlevomatCodingStandard\Sniffs\TypeHints\TypeHintDeclarationSniff.UselessDocComment' => NULL,
    'SlevomatCodingStandard\Sniffs\TypeHints\TypeHintDeclarationSniff.MissingTraversablePropertyTypeHintSpecification' => NULL,
    'SlevomatCodingStandard\Sniffs\TypeHints\TypeHintDeclarationSniff.MissingTraversableReturnTypeHintSpecification' => NULL,
    'SlevomatCodingStandard\Sniffs\TypeHints\TypeHintDeclarationSniff.MissingTraversableParameterTypeHintSpecification' => NULL,
    'SlevomatCodingStandard\Sniffs\TypeHints\TypeHintDeclarationSniff.MissingParameterTypeHint' => NULL,
    'SlevomatCodingStandard\Sniffs\TypeHints\TypeHintDeclarationSniff.MissingReturnTypeHint' => NULL,
  ]);
};
