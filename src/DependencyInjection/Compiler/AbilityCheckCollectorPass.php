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
    foreach ($container->findTaggedServiceIds('ability_check') as $id => $attributes) {
      $key = $attributes[0]['ability'] ?? NULL;
      if (!$key) {
        throw new \LogicException(sprintf('Service "%s" is tagged "ability_check" but declares no "ability" attribute.', $id));
      }
      if (!defined($key)) {
        throw new \LogicException(sprintf('Ability check "%s" references undefined ability "%s".', $id, $key));
      }
      $checker->addMethodCall('addCheck', [$key, new Reference($id)]);
    }
  }

}
