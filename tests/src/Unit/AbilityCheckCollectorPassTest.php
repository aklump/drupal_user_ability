<?php

namespace Drupal\Tests\user_ability\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\user_ability\AbilityChecker;
use Drupal\user_ability\DependencyInjection\Compiler\AbilityCheckCollectorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @coversDefaultClass \Drupal\user_ability\DependencyInjection\Compiler\AbilityCheckCollectorPass
 * @group user_ability
 */
class AbilityCheckCollectorPassTest extends UnitTestCase {

  /**
   * Builds a container holding the checker and nothing else.
   */
  private function containerWithChecker(): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->register('user_ability.checker', AbilityChecker::class);

    return $container;
  }

  /**
   * Fully-qualified case reference, as a site writes it in services.yml.
   */
  private function key(CollectorFixtureAbility $ability): string {
    return CollectorFixtureAbility::class . '::' . $ability->name;
  }

  /**
   * @covers ::process
   */
  public function testProcessRegistersOneCheckPerTaggedService(): void {
    $container = $this->containerWithChecker();
    $container->register('check.one')
      ->addTag('ability_check', ['ability' => $this->key(CollectorFixtureAbility::One)]);
    $container->register('check.two')
      ->addTag('ability_check', ['ability' => $this->key(CollectorFixtureAbility::Two)]);

    (new AbilityCheckCollectorPass())->process($container);

    $calls = $container->getDefinition('user_ability.checker')->getMethodCalls();
    $this->assertEquals([
      ['addCheck', [$this->key(CollectorFixtureAbility::One), new Reference('check.one')]],
      ['addCheck', [$this->key(CollectorFixtureAbility::Two), new Reference('check.two')]],
    ], $calls);
  }

  /**
   * @covers ::process
   */
  public function testProcessThrowsWhenServiceIsTaggedTwice(): void {
    $container = $this->containerWithChecker();
    $container->register('check.greedy')
      ->addTag('ability_check', ['ability' => $this->key(CollectorFixtureAbility::One)])
      ->addTag('ability_check', ['ability' => $this->key(CollectorFixtureAbility::Two)]);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Service "check.greedy" is tagged "ability_check" 2 times');
    (new AbilityCheckCollectorPass())->process($container);
  }

  /**
   * @covers ::process
   */
  public function testProcessThrowsWhenTwoServicesClaimTheSameAbility(): void {
    $container = $this->containerWithChecker();
    $container->register('check.first')
      ->addTag('ability_check', ['ability' => $this->key(CollectorFixtureAbility::One)]);
    $container->register('check.second')
      ->addTag('ability_check', ['ability' => $this->key(CollectorFixtureAbility::One)]);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('has two checks: "check.first" and "check.second"');
    (new AbilityCheckCollectorPass())->process($container);
  }

}

/**
 * A throwaway ability, declared for this test only since consumers define.
 */
enum CollectorFixtureAbility {

  case One;
  case Two;

}
