<?php

namespace Drupal\user_ability\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects ability_check-tagged services onto the user_ability.checker.
 *
 * Modeled on core's RegisterAccessChecksPass, which collects access_check
 * services for AccessManager the same way.
 */
class AbilityCheckCollectorPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    if (!$container->hasDefinition('user_ability.checker')) {
      return;
    }

    $checker = $container->getDefinition('user_ability.checker');
    $registered = [];
    foreach ($container->findTaggedServiceIds('ability_check') as $id => $attributes) {
      // AbilityCheckInterface::abilityAccess() is never told which ability it
      // is deciding, and getBusinessRule() states a single rule, so a check
      // can only ever stand for one ability.
      if (count($attributes) > 1) {
        throw new \LogicException(sprintf('Service "%s" is tagged "ability_check" %d times; a check decides exactly one ability.', $id, count($attributes)));
      }
      $key = $attributes[0]['ability'] ?? NULL;
      if (!$key) {
        throw new \LogicException(sprintf('Service "%s" is tagged "ability_check" but declares no "ability" attribute.', $id));
      }
      if (str_starts_with($key, '\\')) {
        throw new \LogicException(sprintf('Ability check "%s" references ability key that starts with a backslash.', $id));
      }
      if (!defined($key)) {
        throw new \LogicException(sprintf('Ability check "%s" references undefined ability "%s".', $id, $key));
      }
      if (isset($registered[$key])) {
        throw new \LogicException(sprintf('Ability "%s" has two checks: "%s" and "%s".', $key, $registered[$key], $id));
      }
      $registered[$key] = $id;
      $checker->addMethodCall('addCheck', [$key, new Reference($id)]);
    }
  }

}
